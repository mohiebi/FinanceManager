<?php

namespace App\Actions\Bills;

use App\Enums\TransactionType;
use App\Models\Bill;
use App\Models\BillOccurrence;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

/**
 * Marks a bill occurrence as paid and creates the matching Cost transaction.
 * Shared by the web Bills page and the Telegram bot, so paying a bill from
 * either surface has identical behavior.
 */
class MarkBillOccurrencePaid
{
    public function __invoke(Bill $bill, BillOccurrence $occurrence): ?Transaction
    {
        if ($occurrence->isPaid()) {
            return $occurrence->transaction;
        }

        $transaction = $bill->user->transactions()->create([
            'category_id' => $bill->category_id,
            'type' => TransactionType::Cost,
            'amount' => (float) $bill->amount,
            'currency' => $bill->currency,
            'title' => $bill->title,
            'occurred_at' => Carbon::today()->toDateString(),
        ]);

        $occurrence->forceFill([
            'paid_at' => now(),
            'transaction_id' => $transaction->id,
        ])->save();

        return $transaction;
    }
}
