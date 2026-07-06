<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['investment_asset_id', 'price', 'snapped_on'])]
class AssetPriceSnapshot extends Model
{
    /**
     * @return BelongsTo<InvestmentAsset, AssetPriceSnapshot>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(InvestmentAsset::class, 'investment_asset_id');
    }

    protected function casts(): array
    {
        return [
            'snapped_on' => 'date',
        ];
    }
}
