<?php

namespace App\Models;

use App\Enums\AssetType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'asset_type'   => AssetType::class,
            'quantity'     => 'decimal:8',
            'cost_basis'   => 'decimal:4',
            'occurred_at'  => 'date:Y-m-d',
        ];
    }
}
