<?php

namespace App\Http\Resources;

use App\Models\InvestmentAsset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InvestmentAsset
 */
class InvestmentAssetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->slug,
            'value' => (string) $this->id,
            'label' => $this->label(),
            'name' => $this->name,
            'slug' => $this->slug,
            'unit' => $this->unit,
            'asset_class' => $this->asset_class?->value,
            'asset_class_label' => $this->asset_class?->label(),
            'underlying_asset_id' => $this->underlying_asset_id,
            'underlying_label' => $this->whenLoaded('underlying', fn () => $this->underlying?->label()),
            'underlying_ratio' => $this->underlying_ratio,
            'can_be_underlying' => $this->canBeUnderlying(),
            'icon' => $this->icon,
            'icon_svg' => $this->icon_svg,
            'color' => $this->color,
            'is_default' => $this->is_default,
            'price_source_type' => $this->price_source_type->value,
            'price_source_config' => $this->when(! $this->is_default, $this->price_source_config ?? []),
        ];
    }
}
