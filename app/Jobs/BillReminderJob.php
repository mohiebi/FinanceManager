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
            ->with('user')
            ->chunkById(100, function ($bills) use ($calculator): void {
                foreach ($bills as $bill) {
                    if (! $bill->user) {
                        continue;
                    }

                    $this->ensureUpcomingOccurrence($bill, $calculator);
                    $this->sendDueReminders($bill);
                }
            });
    }

    private function ensureUpcomingOccurrence(Bill $bill, BillDueDateCalculator $calculator): void
    {
        if ($bill->recurrence_type === BillRecurrenceType::OneTime) {
            if ($bill->due_date === null) {
                return;
            }

            BillOccurrence::query()->firstOrCreate([
                'bill_id' => $bill->id,
                'due_date' => $bill->due_date->toDateString(),
            ]);

            return;
        }

        if ($bill->due_day_of_month === null) {
            return;
        }

        $hasPending = $bill->occurrences()
            ->whereNull('paid_at')
            ->where('due_date', '>=', Carbon::yesterday()->toDateString())
            ->exists();

        if ($hasPending) {
            return;
        }

        $calendar = $bill->user->calendar ?? 'gregorian';
        $nextDue = $calculator->nextOccurrence($bill->due_day_of_month, $calendar);

        BillOccurrence::query()->firstOrCreate([
            'bill_id' => $bill->id,
            'due_date' => $nextDue->toDateString(),
        ]);
    }

    private function sendDueReminders(Bill $bill): void
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        $occurrences = $bill->occurrences()
            ->whereNull('paid_at')
            ->whereIn('due_date', [$today->toDateString(), $tomorrow->toDateString()])
            ->get();

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
