<?php

namespace App\Models;

use App\Casts\UserEncrypted;
use App\Concerns\OwnsEncryptedAttributes;
use App\Contracts\HasEncryptionOwner;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'investment_asset_id',
    'asset_type',
    'quantity',
    'cost_basis',
    'cost_basis_currency',
    'note',
    'occurred_at',
])]
class Investment extends Model implements HasEncryptionOwner
{
    use OwnsEncryptedAttributes;

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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => UserEncrypted::class,
            'cost_basis' => UserEncrypted::class,
            'note' => UserEncrypted::class,
            'occurred_at' => 'date:Y-m-d',
        ];
    }
}
