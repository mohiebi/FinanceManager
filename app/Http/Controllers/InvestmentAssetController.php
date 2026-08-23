<?php

namespace App\Http\Controllers;

use App\Enums\AssetClass;
use App\Http\Requests\InvestmentAsset\StoreInvestmentAssetRequest;
use App\Http\Requests\InvestmentAsset\UpdateInvestmentAssetRequest;
use App\Http\Resources\InvestmentAssetResource;
use App\Models\InvestmentAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentAssetController extends Controller
{
    public function edit(Request $request): Response
    {
        $assets = InvestmentAsset::query()
            ->where('user_id', $request->user()->id)
            ->with('underlying')
            ->withCount('investments')
            ->orderBy('name')
            ->get()
            ->map(fn (InvestmentAsset $asset) => [
                ...(new InvestmentAssetResource($asset))->resolve($request),
                'investments_count' => $asset->investments_count,
            ]);

        return Inertia::render('settings/Assets', [
            'assets' => $assets,
            'sourceTypes' => $this->sourceTypes(),
            'assetClasses' => $this->assetClasses(),
            'underlyingOptions' => $this->underlyingOptions($request),
        ]);
    }

    public function store(StoreInvestmentAssetRequest $request): RedirectResponse
    {
        $asset = InvestmentAsset::query()->create([
            'user_id' => $request->user()->id,
            ...$request->assetData(),
        ]);

        return back()->with('createdInvestmentAsset', [
            'id' => $asset->id,
        ]);
    }

    public function update(UpdateInvestmentAssetRequest $request, InvestmentAsset $investmentAsset): RedirectResponse
    {
        $investmentAsset->update($request->assetData());

        return back();
    }

    public function destroy(Request $request, InvestmentAsset $investmentAsset): RedirectResponse
    {
        abort_unless(
            $investmentAsset->user_id !== null
            && (int) $investmentAsset->user_id === (int) $request->user()->id,
            404,
        );

        if ($investmentAsset->investments()->exists()) {
            return back()->withErrors([
                'investment_asset' => __('settings.assets.delete_in_use'),
            ]);
        }

        $investmentAsset->delete();

        return back();
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function assetClasses(): array
    {
        return array_map(
            fn (AssetClass $class): array => ['label' => $class->label(), 'value' => $class->value],
            AssetClass::cases(),
        );
    }

    /**
     * The assets that may be named as an underlying.
     *
     * Roots only — an asset that already tracks something cannot itself be
     * tracked, which is what keeps the tree one level deep.
     *
     * @return list<array{label: string, value: string, slug: string, unit: string, asset_class: string|null}>
     */
    private function underlyingOptions(Request $request): array
    {
        return InvestmentAsset::query()
            ->availableFor($request->user())
            ->whereNull('underlying_asset_id')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (InvestmentAsset $asset): array => [
                'label' => $asset->label(),
                'value' => (string) $asset->id,
                'slug' => $asset->slug,
                'unit' => $asset->unit,
                'asset_class' => $asset->asset_class?->value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function sourceTypes(): array
    {
        return [
            ['label' => __('settings.assets.sources.manual'), 'value' => 'manual'],
            ['label' => __('settings.assets.sources.formula'), 'value' => 'formula'],
            ['label' => __('settings.assets.sources.json'), 'value' => 'json'],
            ['label' => __('settings.assets.sources.xml'), 'value' => 'xml'],
        ];
    }
}
