<?php

namespace App\Actions\Bills;

use App\Enums\TransactionType;
use App\Models\Bill;
use App\Models\BillOccurrence;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\Encryption\SealedField;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Marks a bill occurrence as paid and creates the matching Cost transaction.
 * Shared by the web Bills page and the Telegram bot, so paying a bill from
 * either surface has identical behavior.
 */
class MarkBillOccurrencePaid
{
    /**
     * Title and amount the browser re-encrypted for the `transactions` table.
     *
     * Required when the owner's vault is armed: a ciphertext is bound to its table
     * by the AAD, so the bill's own blobs cannot be copied across, and the server
     * holds no key to re-seal them.
     *
     * @param  array{title: string, amount: string}|null  $sealed
     */
    public function __invoke(Bill $bill, BillOccurrence $occurrence, ?array $sealed = null): ?Transaction
    {
        return DB::transaction(function () use ($bill, $occurrence, $sealed) {
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

            $categoryId = $bill->category_id ?? Category::query()
                ->whereNull('user_id')
                ->where('type', TransactionType::Cost)
                ->where('slug', 'bills')
                ->value('id');

            $transaction = $bill->user->transactions()->create([
                'category_id' => $categoryId,
                'type' => TransactionType::Cost,
                'currency' => $bill->currency,
                'occurred_at' => Carbon::today()->toDateString(),
                ...($sealed === null
                    ? ['amount' => (float) $bill->amount, 'title' => $bill->title]
                    : SealedField::wrap($sealed, ['amount', 'title'])),
            ]);

            $locked->forceFill([
                'paid_at' => now(),
                'transaction_id' => $transaction->id,
            ])->save();

            if ($bill->recurrence_count !== null
                && $bill->occurrences()->whereNotNull('paid_at')->count() >= $bill->recurrence_count) {
                $bill->forceFill(['is_active' => false])->save();
            }

            return $transaction;
        });
    }
}
