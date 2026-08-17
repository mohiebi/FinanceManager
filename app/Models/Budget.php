<?php

namespace App\Models;

use App\Casts\UserEncrypted;
use App\Concerns\OwnsEncryptedAttributes;
use App\Concerns\ScopedToOwner;
use App\Contracts\HasEncryptionOwner;
use App\Enums\BudgetIncomeBasis;
use App\Enums\Currency;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'title',
    'income_basis',
    'expected_income',
    'currency',
    'starts_on',
    'is_active',
])]
class Budget extends Model implements HasEncryptionOwner
{
    /** @use HasFactory<BudgetFactory> */
    use HasFactory, OwnsEncryptedAttributes, ScopedToOwner;

    /**
     * @return BelongsTo<User, Budget>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<BudgetLine, Budget>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class)->orderBy('sort_order');
    }

    /**
     * @param  Builder<Budget>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'title' => UserEncrypted::class,
            // Money, so two decimals — the same shape every other amount in the
            // app is stored in.
            'expected_income' => UserEncrypted::class.':decimal,2',
            'income_basis' => BudgetIncomeBasis::class,
            'currency' => Currency::class,
            'starts_on' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }
}
