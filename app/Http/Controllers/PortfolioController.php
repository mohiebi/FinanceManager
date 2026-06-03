<?php

namespace App\Http\Controllers;

use App\Enums\AssetType;
use App\Models\Investment;
use App\Services\AssetPriceService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class PortfolioController extends Controller
{
    public function __invoke(Request $request, AssetPriceService $priceService): Response
    {
        $user = $request->user();

        /** @var Collection<int, Investment> $allEntries */
        $allEntries = $user->investments()
            ->orderBy('occurred_at')
            ->orderBy('created_at')
            ->get();

        $grouped = $allEntries->groupBy(fn (Investment $investment) => $investment->asset_type->value);

        $assets = [];
        $totalCurrentValue = 0.0;
        $totalCostBasis = 0.0;
        $hasCostBasisData = false;

        foreach ($grouped as $typeValue => $typeEntries) {
            $type = AssetType::from($typeValue);
            $totalQuantity = (float) $typeEntries->sum('quantity');
            $currentValue = $priceService->valueOf($type, $totalQuantity);
            $currentPrice = $priceService->priceFor($type);

            $entriesWithCostBasis = $typeEntries->filter(fn ($entry) => $entry->cost_basis !== null);
            $totalCostBasisValue = $entriesWithCostBasis->sum(fn ($entry) => (float) $entry->cost_basis * (float) $entry->quantity);
            $totalCostBasisQuantity = (float) $entriesWithCostBasis->sum('quantity');
            $averageCostBasis = $totalCostBasisQuantity > 0 ? $totalCostBasisValue / $totalCostBasisQuantity : null;
            $totalAssetCost = $averageCostBasis !== null ? $averageCostBasis * $totalQuantity : null;

            $profitAndLoss = $totalAssetCost !== null ? $currentValue - $totalAssetCost : null;
            $profitAndLossPercent = ($totalAssetCost !== null && $totalAssetCost > 0)
                ? round(($profitAndLoss / $totalAssetCost) * 100, 2)
                : null;

            if ($totalAssetCost !== null) {
                $hasCostBasisData = true;
            }

            $assets[] = [
                'key' => $type->value,
                'label' => $type->label(),
                'icon' => $type->icon(),
                'color' => $type->color(),
                'unit' => $type->unit(),
                'quantity' => round($totalQuantity, 8),
                'current_price' => $currentPrice,
                'current_price_formatted' => number_format($currentPrice, 0, '.', ','),
                'current_value' => $currentValue,
                'current_value_formatted' => number_format($currentValue, 0, '.', ','),
                'avg_cost_basis' => $averageCostBasis,
                'avg_cost_basis_formatted' => $averageCostBasis !== null ? number_format($averageCostBasis, 0, '.', ',') : null,
                'total_cost' => $totalAssetCost,
                'total_cost_formatted' => $totalAssetCost !== null ? number_format($totalAssetCost, 0, '.', ',') : null,
                'pnl' => $profitAndLoss,
                'pnl_formatted' => $profitAndLoss !== null ? number_format(abs($profitAndLoss), 0, '.', ',') : null,
                'pnl_percent' => $profitAndLossPercent,
                'pnl_is_positive' => $profitAndLoss !== null ? $profitAndLoss >= 0 : null,
                'entries_count' => $typeEntries->count(),
            ];

            $totalCurrentValue += $currentValue;
            if ($totalAssetCost !== null) {
                $totalCostBasis += $totalAssetCost;
            }
        }

        usort($assets, fn ($leftAsset, $rightAsset) => $rightAsset['current_value'] <=> $leftAsset['current_value']);

        $totalProfitAndLoss = $hasCostBasisData ? $totalCurrentValue - $totalCostBasis : null;
        $totalProfitAndLossPercent = ($hasCostBasisData && $totalCostBasis > 0)
            ? round(($totalProfitAndLoss / $totalCostBasis) * 100, 2)
            : null;

        $entries = $user->investments()
            ->orderByDesc('occurred_at')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(function (Investment $investment) use ($priceService) {
                $currentPrice = $priceService->priceFor($investment->asset_type);
                $currentValue = $priceService->valueOf($investment->asset_type, (float) $investment->quantity);
                $entryProfitAndLoss = $investment->cost_basis !== null
                    ? $currentValue - ((float) $investment->cost_basis * (float) $investment->quantity)
                    : null;

                return [
                    'id' => $investment->id,
                    'asset_type' => $investment->asset_type->value,
                    'asset_label' => $investment->asset_type->label(),
                    'asset_icon' => $investment->asset_type->icon(),
                    'asset_color' => $investment->asset_type->color(),
                    'asset_unit' => $investment->asset_type->unit(),
                    'quantity' => (float) $investment->quantity,
                    'cost_basis' => $investment->cost_basis !== null ? (float) $investment->cost_basis : null,
                    'cost_basis_currency' => $investment->cost_basis_currency,
                    'current_price' => $currentPrice,
                    'current_price_fmt' => number_format($currentPrice, 0, '.', ','),
                    'current_value' => $currentValue,
                    'current_value_fmt' => number_format($currentValue, 0, '.', ','),
                    'pnl' => $entryProfitAndLoss,
                    'pnl_formatted' => $entryProfitAndLoss !== null ? number_format(abs($entryProfitAndLoss), 0, '.', ',') : null,
                    'pnl_is_positive' => $entryProfitAndLoss !== null ? $entryProfitAndLoss >= 0 : null,
                    'note' => $investment->note,
                    'occurred_at' => $investment->occurred_at->toDateString(),
                ];
            });

        return Inertia::render('Portfolio', [
            'assets' => $assets,
            'entries' => $entries,
            'summary' => [
                'total_current_value' => $totalCurrentValue,
                'total_current_value_formatted' => number_format($totalCurrentValue, 0, '.', ','),
                'total_cost_basis' => $totalCostBasis,
                'total_cost_basis_formatted' => number_format($totalCostBasis, 0, '.', ','),
                'total_pnl' => $totalProfitAndLoss,
                'total_pnl_formatted' => $totalProfitAndLoss !== null ? number_format(abs($totalProfitAndLoss), 0, '.', ',') : null,
                'total_pnl_percent' => $totalProfitAndLossPercent,
                'total_pnl_is_positive' => $totalProfitAndLoss !== null ? $totalProfitAndLoss >= 0 : null,
                'has_cost_basis_data' => $hasCostBasisData,
                'asset_count' => count($assets),
            ],
        ]);
    }
}
