<?php

namespace App\Actions\Bills;

use App\Enums\BillRecurrenceType;
use App\Models\Bill;
use App\Models\BillOccurrence;
use App\Support\BillDueDateCalculator;

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
    public function ensureInitial(Bill $bill): void
    {
        if ($bill->occurrences()->whereNull('paid_at')->exists()) {
            return;
        }

        $dueDate = $this->resolveCurrentDueDate($bill);

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
    public function syncPending(Bill $bill): void
    {
        $dueDate = $this->resolveCurrentDueDate($bill);

        if ($dueDate === null) {
            return;
        }

        $pending = $bill->occurrences()->whereNull('paid_at')->orderBy('due_date')->first();

        if (! $pending) {
            BillOccurrence::query()->create([
                'bill_id' => $bill->id,
                'due_date' => $dueDate,
            ]);

            return;
        }

        if ($pending->due_date->toDateString() === $dueDate) {
            return;
        }

        $pending->forceFill([
            'due_date' => $dueDate,
            'reminder_day_before_sent_at' => null,
            'reminder_due_day_sent_at' => null,
        ])->save();
    }

    private function resolveCurrentDueDate(Bill $bill): ?string
    {
        if ($bill->recurrence_type === BillRecurrenceType::OneTime) {
            return $bill->due_date?->toDateString();
        }

        if ($bill->due_day_of_month === null) {
            return null;
        }

        $calendar = $bill->user->calendar ?? 'gregorian';

        return $this->calculator->nextOccurrence($bill->due_day_of_month, $calendar)->toDateString();
    }
}
