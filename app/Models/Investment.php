<?php

namespace App\Models;

use App\Casts\UserEncrypted;
use App\Concerns\OwnsEncryptedAttributes;
use App\Concerns\ScopedToOwner;
use App\Contracts\HasEncryptionOwner;
use App\Enums\InvestmentKind;
use App\Observers\MilesActivityObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([MilesActivityObserver::class])]
#[Fillable([
    'user_id',
    'investment_asset_id',
    'asset_type',
    'kind',
    'quantity',
    'cost_basis',
    'cost_basis_currency',
    'sale_price',
    'sale_price_currency',
    'note',
    'occurred_at',
])]
class Investment extends Model implements HasEncryptionOwner
{
    use OwnsEncryptedAttributes, ScopedToOwner;

    /**
     * @return BelongsTo<User, Investment>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<InvestmentAsset, Investment>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(InvestmentAsset::class, 'investment_asset_id');
    }

    /** A disposal — stored with a negative quantity so holdings stay a plain sum. */
    public function isSell(): bool
    {
        return $this->kind === InvestmentKind::Sell;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => InvestmentKind::class,
            'quantity' => UserEncrypted::class,
            'cost_basis' => UserEncrypted::class,
            'sale_price' => UserEncrypted::class,
            'note' => UserEncrypted::class,
            'occurred_at' => 'date:Y-m-d',
        ];
    }
}
