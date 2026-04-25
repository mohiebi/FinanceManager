<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\TransactionType;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable([
    'user_id',
    'category_id',
    'type',
    'amount',
    'currency',
    'title',
    'description',
    'occurred_at',
])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Transaction $transaction): void {
            $category = Category::query()->find($transaction->category_id);

            if (! $category instanceof Category) {
                return;
            }

            if ($category->type !== $transaction->type) {
                throw new InvalidArgumentException('Transaction category type must match the transaction type.');
            }

            if ($category->user_id !== null && (int) $category->user_id !== (int) $transaction->user_id) {
                throw new InvalidArgumentException('Transaction category must be global or owned by the transaction user.');
            }
        });
    }

    /**
     * @return BelongsTo<User, Transaction>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Category, Transaction>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'currency' => Currency::class,
            'amount' => 'decimal:2',
            'occurred_at' => 'date:Y-m-d',
        ];
    }
}
