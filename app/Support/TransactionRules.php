<?php

namespace App\Support;

use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Shared transaction validation rules used by the web FormRequests and the
 * MCP tools so both surfaces enforce identical constraints.
 */
class TransactionRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(TransactionType::class)],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'occurred_at' => ['required', 'date'],
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
