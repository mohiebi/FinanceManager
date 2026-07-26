<?php

namespace App\Support;

use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\User;
use App\Support\Encryption\UserCrypto;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Shared transaction validation rules used by the web FormRequests and the
 * MCP tools so both surfaces enforce identical constraints.
 */
class TransactionRules
{
    /**
     * @param  bool  $vaultArmed  when true, amount/title/description arrive already
     *                            encrypted by the browser and the server can no
     *                            longer inspect them
     * @return array<string, mixed>
     */
    public static function rules(bool $vaultArmed = false): array
    {
        return [
            'type' => ['required', Rule::enum(TransactionType::class)],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'amount' => $vaultArmed
                ? self::encryptedRules()
                : ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'title' => $vaultArmed
                ? self::encryptedRules()
                : ['required', 'string', 'max:255'],
            'description' => $vaultArmed
                ? self::encryptedRules(required: false)
                : ['nullable', 'string', 'max:5000'],
            'occurred_at' => ['required', 'date'],
        ];
    }

    /**
     * Rules for a field the server cannot read.
     *
     * `numeric` and `max:255` are gone — the server has no way to check a value it
     * cannot decrypt, which is the honest cost of the vault. What it *can* still
     * enforce is that the client sent real ciphertext rather than junk or, worse,
     * plaintext that would sit unencrypted in an encrypted column.
     *
     * @return array<int, mixed>
     */
    private static function encryptedRules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:8192',
            function (string $attribute, mixed $value, Closure $fail): void {
                // `nullable` only short-circuits on null, so an empty optional
                // field would otherwise be told to encrypt nothing.
                if ($value === null || $value === '') {
                    return;
                }

                if (! UserCrypto::looksEncrypted(is_string($value) ? $value : null)) {
                    $fail('The :attribute must be encrypted by your browser before it is sent.');
                }
            },
        ];
    }

    /**
     * Validates the category/type pairing and category ownership.
     *
     * @return array<string, string> field => error message
     */
    public static function categoryErrors(User $user, mixed $categoryId, mixed $type): array
    {
        $category = Category::query()->find($categoryId);
        $transactionType = TransactionType::tryFrom((string) $type);

        if (! $category instanceof Category || ! $transactionType) {
            return [];
        }

        if ($category->type !== $transactionType) {
            return ['category_id' => 'Choose a category for the selected transaction type.'];
        }

        if ($category->user_id !== null && (int) $category->user_id !== (int) $user->id) {
            return ['category_id' => 'Choose one of your categories or a default category.'];
        }

        return [];
    }
}
