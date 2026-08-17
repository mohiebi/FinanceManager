<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use App\Models\User;
use App\Support\TransactionRules;
use Illuminate\Validation\ValidationException;

class SaveTransaction
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException when the category is not the user's to use, or
     *                             does not match the transaction type
     */
    public function handle(User $user, array $data, ?Transaction $transaction = null): Transaction
    {
        $this->assertCategoryIsUsable($user, $data, $transaction);

        if (! $transaction instanceof Transaction) {
            return $user->transactions()->create($data);
        }

        $transaction->fill($data);
        $transaction->save();

        return $transaction;
    }

    /**
     * Re-checks the category here, where every surface has to pass.
     *
     * The rule itself is not new — the web FormRequest, the MCP batch and the
     * propose tool each ran it before calling in, and each of them still does,
     * because failing at validation time is a better error than failing at write
     * time. What was missing was a floor: a confirmed MCP proposal applies a
     * payload stored earlier and re-validates none of it, so the check existed
     * on three paths out of five and the guarantee was only ever as good as the
     * next caller's memory.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function assertCategoryIsUsable(User $user, array $data, ?Transaction $transaction): void
    {
        $categoryId = $data['category_id'] ?? $transaction?->category_id;
        $type = $data['type'] ?? $transaction?->type;

        if ($categoryId === null || $type === null) {
            return;
        }

        $errors = TransactionRules::categoryErrors(
            $user,
            $categoryId,
            $type instanceof \BackedEnum ? $type->value : $type,
        );

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
