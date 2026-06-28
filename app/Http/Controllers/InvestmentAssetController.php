<?php

namespace App\Http\Controllers;

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
