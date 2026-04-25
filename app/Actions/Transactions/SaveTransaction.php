<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use App\Models\User;

class SaveTransaction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data, ?Transaction $transaction = null): Transaction
    {
        if (! $transaction instanceof Transaction) {
            return $user->transactions()->create($data);
        }

        $transaction->fill($data);
        $transaction->save();

        return $transaction;
    }
}
