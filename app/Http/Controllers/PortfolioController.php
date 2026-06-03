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

        $grouped = $allEntries->groupBy(fn (Investment $inv) => $inv->asset_type->value);

        $assets = [];
        $totalCurrentVal = 0.0;
        $totalCostBasis = 0.0;
        $hasCostBasisData = false;

        foreach ($grouped as $typeValue => $typeEntries) {
            $type = AssetType::from($typeValue);
            $totalQty = (float) $typeEntries->sum('quantity');
            $currentValue = $priceService->valueOf($type, $totalQty);
            $currentPrice = $priceService->priceFor($type);

            $entriesWithCB = $typeEntries->filter(fn ($e) => $e->cost_basis !== null);
            $totalCBValue = $entriesWithCB->sum(fn ($e) => (float) $e->cost_basis * (float) $e->quantity);
            $totalCBQty = (float) $entriesWithCB->sum('quantity');
            $avgCostBasis = $totalCBQty > 0 ? $totalCBValue / $totalCBQty : null;
            $totalAssetCost = $avgCostBasis !== null ? $avgCostBasis * $totalQty : null;

            $pnl = $totalAssetCost !== null ? $currentValue - $totalAssetCost : null;
            $pnlPct = ($totalAssetCost !== null && $totalAssetCost > 0)
                ? round(($pnl / $totalAssetCost) * 100, 2)
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
                'quantity' => round($totalQty, 8),
                'current_price' => $currentPrice,
                'current_price_formatted' => number_format($currentPrice, 0, '.', ','),
                'current_value' => $currentValue,
                'current_value_formatted' => number_format($currentValue, 0, '.', ','),
                'avg_cost_basis' => $avgCostBasis,
                'avg_cost_basis_formatted' => $avgCostBasis !== null ? number_format($avgCostBasis, 0, '.', ',') : null,
                'total_cost' => $totalAssetCost,
                'total_cost_formatted' => $totalAssetCost !== null ? number_format($totalAssetCost, 0, '.', ',') : null,
                'pnl' => $pnl,
                'pnl_formatted' => $pnl !== null ? number_format(abs($pnl), 0, '.', ',') : null,
                'pnl_percent' => $pnlPct,
                'pnl_is_positive' => $pnl !== null ? $pnl >= 0 : null,
                'entries_count' => $typeEntries->count(),
            ];

            $totalCurrentVal += $currentValue;
            if ($totalAssetCost !== null) {
                $totalCostBasis += $totalAssetCost;
            }
        }

        usort($assets, fn ($a, $b) => $b['current_value'] <=> $a['current_value']);

        $totalPnl = $hasCostBasisData ? $totalCurrentVal - $totalCostBasis : null;
        $totalPnlPct = ($hasCostBasisData && $totalCostBasis > 0)
            ? round(($totalPnl / $totalCostBasis) * 100, 2)
            : null;

        $entries = $user->investments()
            ->orderByDesc('occurred_at')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(function (Investment $inv) use ($priceService) {
                $currentPrice = $priceService->priceFor($inv->asset_type);
                $currentValue = $priceService->valueOf($inv->asset_type, (float) $inv->quantity);
                $entryPnl = $inv->cost_basis !== null
                    ? $currentValue - ((float) $inv->cost_basis * (float) $inv->quantity)
                    : null;

                return [
                    'id' => $inv->id,
                    'asset_type' => $inv->asset_type->value,
                    'asset_label' => $inv->asset_type->label(),
                    'asset_icon' => $inv->asset_type->icon(),
                    'asset_color' => $inv->asset_type->color(),
                    'asset_unit' => $inv->asset_type->unit(),
                    'quantity' => (float) $inv->quantity,
                    'cost_basis' => $inv->cost_basis !== null ? (float) $inv->cost_basis : null,
                    'cost_basis_currency' => $inv->cost_basis_currency,
                    'current_price' => $currentPrice,
                    'current_price_fmt' => number_format($currentPrice, 0, '.', ','),
                    'current_value' => $currentValue,
                    'current_value_fmt' => number_format($currentValue, 0, '.', ','),
                    'pnl' => $entryPnl,
                    'pnl_formatted' => $entryPnl !== null ? number_format(abs($entryPnl), 0, '.', ',') : null,
                    'pnl_is_positive' => $entryPnl !== null ? $entryPnl >= 0 : null,
                    'note' => $inv->note,
                    'occurred_at' => $inv->occurred_at->toDateString(),
                ];
            });

        return Inertia::render('Portfolio', [
            'assets' => $assets,
            'entries' => $entries,
            'summary' => [
                'total_current_value' => $totalCurrentVal,
                'total_current_value_formatted' => number_format($totalCurrentVal, 0, '.', ','),
                'total_cost_basis' => $totalCostBasis,
                'total_cost_basis_formatted' => number_format($totalCostBasis, 0, '.', ','),
                'total_pnl' => $totalPnl,
                'total_pnl_formatted' => $totalPnl !== null ? number_format(abs($totalPnl), 0, '.', ',') : null,
                'total_pnl_percent' => $totalPnlPct,
                'total_pnl_is_positive' => $totalPnl !== null ? $totalPnl >= 0 : null,
                'has_cost_basis_data' => $hasCostBasisData,
                'asset_count' => count($assets),
            ],
        ]);
    }
}
