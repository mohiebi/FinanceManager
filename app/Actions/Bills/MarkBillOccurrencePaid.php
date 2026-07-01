<?php

namespace App\Actions\Bills;

use App\Enums\TransactionType;
use App\Models\Bill;
use App\Models\BillOccurrence;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Marks a bill occurrence as paid and creates the matching Cost transaction.
 * Shared by the web Bills page and the Telegram bot, so paying a bill from
 * either surface has identical behavior.
 */
class MarkBillOccurrencePaid
{
    public function __invoke(Bill $bill, BillOccurrence $occurrence): ?Transaction
    {
        return DB::transaction(function () use ($bill, $occurrence) {
            // Re-fetch under a row lock so two near-simultaneous "mark paid" calls
            // (double-click, or web + Telegram at once) can't both pass the
            // isPaid() check before either writes — the second caller blocks here
            // until the first commits, then sees paid_at already set.
            $locked = BillOccurrence::query()
                ->whereKey($occurrence->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->isPaid()) {
                return $locked?->transaction;
            }

            $transaction = $bill->user->transactions()->create([
                'category_id' => $bill->category_id,
                'type' => TransactionType::Cost,
                'amount' => (float) $bill->amount,
                'currency' => $bill->currency,
                'title' => $bill->title,
                'occurred_at' => Carbon::today()->toDateString(),
            ]);

            $locked->forceFill([
                'paid_at' => now(),
                'transaction_id' => $transaction->id,
            ])->save();

            return $transaction;
        });
    }
}
