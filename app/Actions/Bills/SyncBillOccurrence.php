<?php

namespace App\Actions\Bills;

use App\Enums\BillRecurrenceType;
use App\Models\Bill;
use App\Models\BillOccurrence;
use App\Support\BillDueDateCalculator;
use Illuminate\Support\Carbon;

/**
 * Keeps a bill's pending (unpaid) occurrence in sync with its current
 * recurrence settings. Shared by the web Bills page and the Telegram bot so
 * both surfaces compute "next due" identically.
 */
class SyncBillOccurrence
{
    public function __construct(private readonly BillDueDateCalculator $calculator) {}

    /**
     * Creates the first occurrence for a brand-new bill, if one doesn't
     * already exist.
     */
    public function ensureInitial(Bill $bill, ?string $calendar = null): void
    {
        if ($bill->occurrences()->whereNull('paid_at')->exists()) {
            return;
        }

        if (! $this->hasRemainingPaymentSlot($bill)) {
            return;
        }

        $dueDate = $this->resolveCurrentDueDate($bill, $calendar);

        if ($dueDate === null || ! $this->isWithinEndDate($bill, $dueDate)) {
            return;
        }

        BillOccurrence::query()->firstOrCreate([
            'bill_id' => $bill->id,
            'due_date' => $dueDate,
        ]);
    }

    /**
     * Recomputes the pending occurrence after a bill's recurrence settings
     * change (e.g. due day edited). Resets reminder-sent flags when the due
     * date actually moves, so reminders correctly re-fire for the new date.
     */
    public function syncPending(Bill $bill, ?string $calendar = null): void
    {
        $this->trimToRecurrenceLimit($bill);
        $dueDate = $this->resolveCurrentDueDate($bill, $calendar);

        if ($dueDate === null || ! $this->isWithinEndDate($bill, $dueDate)) {
            return;
        }

        if ($bill->recurrence_type === BillRecurrenceType::Monthly) {
            $calendar ??= $bill->user->calendar ?? 'gregorian';

            while ($bill->occurrences()
                ->where('due_date', $dueDate)
                ->whereNotNull('paid_at')
                ->exists()) {
                $dueDate = $this->calculator->nextOccurrence(
                    (int) $bill->due_day_of_month,
                    $calendar,
                    Carbon::parse($dueDate)->addDay(),
                )->toDateString();
            }
        }

        $pending = $bill->occurrences()->whereNull('paid_at')->orderBy('due_date')->first();

        if (! $pending) {
            if (! $this->hasRemainingPaymentSlot($bill)) {
                return;
            }

            // firstOrCreate (not create) — another occurrence (e.g. already paid)
            // may already exist on this exact date and would collide with the
            // unique(bill_id, due_date) constraint otherwise.
            BillOccurrence::query()->firstOrCreate([
                'bill_id' => $bill->id,
                'due_date' => $dueDate,
            ]);

            return;
        }

        if ($pending->due_date->toDateString() === $dueDate) {
            return;
        }

        // Recomputing the due date could collide with another occurrence already
        // sitting on that date (e.g. a previously paid one). Skip the update
        // rather than crash on the unique constraint — the existing pending
        // occurrence stays as-is until it no longer conflicts.
        $conflicts = $bill->occurrences()
            ->where('due_date', $dueDate)
            ->whereKeyNot($pending->id)
            ->exists();

        if ($conflicts) {
            return;
        }

        $pending->forceFill([
            'due_date' => $dueDate,
            'reminder_day_before_sent_at' => null,
            'reminder_due_day_sent_at' => null,
        ])->save();
    }

    /**
     * Generates occurrences for the next $months monthly cycles from today.
     * Starts after the bill's latest existing occurrence to avoid duplicating
     * months that are already represented. Safe to call repeatedly — uses
     * firstOrCreate to avoid unique(bill_id, due_date) constraint violations.
     *
     * @param  string|null  $calendar  Pre-resolved calendar ('gregorian'|'jalali'). If omitted, resolved from $bill->user.
     * @param  string|null  $latestDueDate  Pre-fetched max due_date (avoids a per-bill query when calling in a loop).
     */
    public function lookahead(Bill $bill, int $months = 3, ?string $calendar = null, ?string $latestDueDate = null): void
    {
        if ($bill->recurrence_type !== BillRecurrenceType::Monthly || $bill->due_day_of_month === null) {
            return;
        }

        $calendar = $calendar ?? ($bill->user->calendar ?? 'gregorian');
        $horizon = Carbon::today()->addDays(90);
        $this->trimToRecurrenceLimit($bill);
        $occurrenceCount = $bill->occurrences()->count();

        // Start generating after the latest occurrence already on record.
        // $latestDueDate may be pre-fetched by the caller (withMax) to avoid N+1.
        if ($latestDueDate === null) {
            $latest = $bill->occurrences()->orderByDesc('due_date')->first();
            $latestDueDate = $latest?->due_date->toDateString();
        }

        $after = $latestDueDate !== null
            ? Carbon::parse($latestDueDate)->addDay()
            : Carbon::today();

        for ($i = 0; $i < $months; $i++) {
            if ($bill->recurrence_count !== null && $occurrenceCount >= $bill->recurrence_count) {
                break;
            }

            $dueDate = $this->calculator->nextOccurrence($bill->due_day_of_month, $calendar, $after);

            if ($dueDate->gt($horizon) || ! $this->isWithinEndDate($bill, $dueDate->toDateString())) {
                break;
            }

            $occurrence = BillOccurrence::query()->firstOrCreate([
                'bill_id' => $bill->id,
                'due_date' => $dueDate->toDateString(),
            ]);

            if ($occurrence->wasRecentlyCreated) {
                $occurrenceCount++;
            }

            $after = $dueDate->copy()->addDay();
        }
    }

    /**
     * Remove only unpaid occurrences outside a newly shortened plan. Paid
     * history is immutable and SaveBill prevents a limit below that history.
     */
    public function trimToRecurrenceLimit(Bill $bill): void
    {
        if ($bill->recurrence_type !== BillRecurrenceType::Monthly) {
            return;
        }

        $occurrences = $bill->occurrences()
            ->orderBy('due_date')
            ->orderBy('id')
            ->get(['id', 'due_date', 'paid_at']);

        $extraIds = collect();

        if ($bill->recurrence_count !== null) {
            $extraIds = $extraIds->merge(
                $occurrences->slice($bill->recurrence_count)
                    ->whereNull('paid_at')
                    ->pluck('id'),
            );
        }

        if ($bill->recurrence_end_date !== null) {
            $extraIds = $extraIds->merge(
                $occurrences
                    ->filter(fn (BillOccurrence $occurrence): bool => $occurrence->paid_at === null
                        && $occurrence->due_date->gt($bill->recurrence_end_date))
                    ->pluck('id'),
            );
        }

        if ($extraIds->isNotEmpty()) {
            $bill->occurrences()->whereIn('id', $extraIds->unique()->values()->all())->delete();
        }
    }

    private function resolveCurrentDueDate(Bill $bill, ?string $calendar = null): ?string
    {
        if ($bill->recurrence_type === BillRecurrenceType::OneTime) {
            return $bill->due_date?->toDateString();
        }

        if ($bill->due_day_of_month === null) {
            return null;
        }

        $calendar ??= $bill->user->calendar ?? 'gregorian';

        return $this->calculator->nextOccurrence($bill->due_day_of_month, $calendar)->toDateString();
    }

    private function hasRemainingPaymentSlot(Bill $bill): bool
    {
        return $bill->recurrence_count === null
            || $bill->occurrences()->count() < $bill->recurrence_count;
    }

    private function isWithinEndDate(Bill $bill, string $dueDate): bool
    {
        return $bill->recurrence_end_date === null
            || Carbon::parse($dueDate)->lte($bill->recurrence_end_date);
    }
}
