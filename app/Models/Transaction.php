<?php

namespace App\Models;

use App\Casts\UserEncrypted;
use App\Concerns\OwnsEncryptedAttributes;
use App\Concerns\ScopedToOwner;
use App\Contracts\HasEncryptionOwner;
use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Observers\TransactionObserver;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[ObservedBy([TransactionObserver::class])]
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
class Transaction extends Model implements HasEncryptionOwner
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, OwnsEncryptedAttributes, ScopedToOwner;

    protected static function booted(): void
    {
        static::saving(function (Transaction $transaction): void {
            $category = Category::query()->find($transaction->category_id);

            if (! $category instanceof Category) {
                return;
            }

            if (! $category->allowsType($transaction->type)) {
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
            // Encrypted under the owning user's key. `decimal,2` keeps the same
            // two-decimal string shape the old 'decimal:2' cast returned, so
            // nothing downstream sees a changed value type.
            'amount' => UserEncrypted::class.':decimal,2',
            'title' => UserEncrypted::class,
            'description' => UserEncrypted::class,
            'occurred_at' => 'date:Y-m-d',
        ];
    }
}
