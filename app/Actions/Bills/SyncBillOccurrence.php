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

        $dueDate = $this->resolveCurrentDueDate($bill, $calendar);

        if ($dueDate === null) {
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
        $dueDate = $this->resolveCurrentDueDate($bill, $calendar);

        if ($dueDate === null) {
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
            $dueDate = $this->calculator->nextOccurrence($bill->due_day_of_month, $calendar, $after);

            if ($dueDate->gt($horizon)) {
                break;
            }

            BillOccurrence::query()->firstOrCreate([
                'bill_id' => $bill->id,
                'due_date' => $dueDate->toDateString(),
            ]);

            $after = $dueDate->copy()->addDay();
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
}
