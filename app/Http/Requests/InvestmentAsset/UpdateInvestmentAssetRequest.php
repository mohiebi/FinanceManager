<?php

namespace App\Http\Requests\InvestmentAsset;

use App\Models\InvestmentAsset;
use Illuminate\Validation\Validator;

class UpdateInvestmentAssetRequest extends StoreInvestmentAssetRequest
{
    public function authorize(): bool
    {
        $asset = $this->route('investment_asset');

        return $asset instanceof InvestmentAsset
            && $this->user() !== null
            && $asset->user_id !== null
            && (int) $asset->user_id === (int) $this->user()->id;
    }

    protected function validateUniqueSlug(Validator $validator): void
    {
        $asset = $this->route('investment_asset');
        $name = (string) $this->input('name');

        if (! $asset instanceof InvestmentAsset || $name === '') {
            return;
        }

        $exists = InvestmentAsset::query()
            ->availableFor($this->user())
            ->where('slug', InvestmentAsset::slugForName($name))
            ->whereKeyNot($asset->id)
            ->exists();

        if ($exists) {
            $validator->errors()->add('name', __('settings.assets.already_exists'));
        }
    }
}
