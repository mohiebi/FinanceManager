<?php

namespace App\Jobs;

use App\Enums\BillRecurrenceType;
use App\Models\Bill;
use App\Models\BillOccurrence;
use App\Notifications\BillDueNotification;
use App\Support\BillDueDateCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BillReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    public function handle(BillDueDateCalculator $calculator): void
    {
        Bill::query()
            ->where('is_active', true)
            ->with(['user', 'occurrences' => fn ($query) => $query->whereNull('paid_at')])
            ->chunkById(100, function ($bills) use ($calculator): void {
                foreach ($bills as $bill) {
                    if (! $bill->user) {
                        continue;
                    }

                    // Loaded once per bill and kept in sync below, instead of
                    // re-querying for "has pending" and then again for "due
                    // today/tomorrow" — was 2 extra queries per bill.
                    $unpaid = $bill->occurrences;

                    $unpaid = $this->ensureUpcomingOccurrence($bill, $calculator, $unpaid);
                    $this->sendDueReminders($bill, $unpaid);
                }
            });
    }

    /**
     * @param  Collection<int, BillOccurrence>  $unpaid
     * @return Collection<int, BillOccurrence>
     */
    private function ensureUpcomingOccurrence(Bill $bill, BillDueDateCalculator $calculator, Collection $unpaid): Collection
    {
        if ($bill->recurrence_type === BillRecurrenceType::OneTime) {
            if ($bill->due_date === null) {
                return $unpaid;
            }

            return $this->appendIfCreated($unpaid, BillOccurrence::query()->firstOrCreate([
                'bill_id' => $bill->id,
                'due_date' => $bill->due_date->toDateString(),
            ]));
        }

        if ($bill->due_day_of_month === null) {
            return $unpaid;
        }

        $hasPending = $unpaid->contains(
            fn (BillOccurrence $occurrence) => $occurrence->due_date->gte(Carbon::yesterday()),
        );

        if ($hasPending) {
            return $unpaid;
        }

        $calendar = $bill->user->calendar ?? 'gregorian';
        $nextDue = $calculator->nextOccurrence($bill->due_day_of_month, $calendar);

        return $this->appendIfCreated($unpaid, BillOccurrence::query()->firstOrCreate([
            'bill_id' => $bill->id,
            'due_date' => $nextDue->toDateString(),
        ]));
    }

    /**
     * firstOrCreate() searches all occurrences (not just unpaid ones), so it
     * can return an existing *paid* occurrence that happens to share the
     * computed due date. Only track it locally if it's genuinely unpaid —
     * otherwise it would be wrongly eligible for a reminder below.
     *
     * @param  Collection<int, BillOccurrence>  $unpaid
     * @return Collection<int, BillOccurrence>
     */
    private function appendIfCreated(Collection $unpaid, BillOccurrence $occurrence): Collection
    {
        if ($occurrence->isPaid() || $unpaid->contains('id', $occurrence->id)) {
            return $unpaid;
        }

        return $unpaid->push($occurrence);
    }

    /**
     * @param  Collection<int, BillOccurrence>  $unpaid
     */
    private function sendDueReminders(Bill $bill, Collection $unpaid): void
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        $occurrences = $unpaid->filter(
            fn (BillOccurrence $occurrence) => $occurrence->due_date->isSameDay($today) || $occurrence->due_date->isSameDay($tomorrow),
        );

        foreach ($occurrences as $occurrence) {
            if ($occurrence->due_date->isSameDay($tomorrow) && ! $occurrence->reminder_day_before_sent_at) {
                $bill->user->notify(new BillDueNotification($bill, $occurrence, 'day_before'));
                $occurrence->forceFill(['reminder_day_before_sent_at' => now()])->save();
            }

            if ($occurrence->due_date->isSameDay($today) && ! $occurrence->reminder_due_day_sent_at) {
                $bill->user->notify(new BillDueNotification($bill, $occurrence, 'due_day'));
                $occurrence->forceFill(['reminder_due_day_sent_at' => now()])->save();
            }
        }
    }
}
