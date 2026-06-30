<?php

namespace App\Models;

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
class Investment extends Model
{
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
            'quantity' => 'encrypted',
            'cost_basis' => 'encrypted',
            'note' => 'encrypted',
            'occurred_at' => 'date:Y-m-d',
        ];
    }
}
