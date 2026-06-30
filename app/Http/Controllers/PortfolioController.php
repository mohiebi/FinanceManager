<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\CurrencyConverter;
use App\Enums\Currency;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Services\AssetPriceService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class PortfolioController extends Controller
{
    public function __invoke(Request $request, AssetPriceService $priceService, CurrencyConverter $currencyConverter): Response
    {
        $user = $request->user();
        $selectedCurrency = Currency::tryFrom((string) $request->query('currency')) ?? Currency::Toman;

        $fmt = function (float $amount) use ($selectedCurrency, $currencyConverter): string {
            if ($selectedCurrency === Currency::Toman) {
                return number_format($amount, 0, '.', ',');
            }

            return number_format($currencyConverter->convert($amount, Currency::Toman, $selectedCurrency), 2, '.', ',');
        };

        /** @var Collection<int, Investment> $allEntries */
        $allEntries = $user->investments()
            ->with('asset')
            ->orderBy('occurred_at')
            ->orderBy('created_at')
            ->get()
            ->filter(fn (Investment $investment) => $investment->asset !== null);

        return Inertia::render('Portfolio', [
            'currencies' => collect(Currency::cases())->map(fn (Currency $c) => [
                'label' => strtoupper($c->value),
                'value' => $c->value,
            ]),
            'selectedCurrency' => $selectedCurrency->value,
            'assets' => Inertia::defer(fn () => $this->buildAssetBreakdown($allEntries, $priceService, $currencyConverter, $fmt)['assets']),
            'summary' => Inertia::defer(fn () => $this->buildAssetBreakdown($allEntries, $priceService, $currencyConverter, $fmt)['summary']),
            'pricesAvailable' => Inertia::defer(fn () => $priceService->pricesAvailable()),
            'pricesSyncedAt' => $priceService->lastSyncedAt(),
        ]);
    }

    /**
     * @param  Collection<int, Investment>  $allEntries
     * @return array{assets: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    private function buildAssetBreakdown(
        Collection $allEntries,
        AssetPriceService $priceService,
        CurrencyConverter $currencyConverter,
        callable $fmt,
    ): array {
        $grouped = $allEntries->groupBy('investment_asset_id');

        $assets = [];
        $totalCurrentValue = 0.0;
        $totalCurrentValueForCostUnits = 0.0;
        $totalCostBasis = 0.0;
        $hasCostBasisData = false;

        foreach ($grouped as $typeEntries) {
            $asset = $typeEntries->first()?->asset;

            if (! $asset instanceof InvestmentAsset) {
                continue;
            }

            $totalQuantity = (float) $typeEntries->sum('quantity');
            $currentValue = $priceService->valueOf($asset, $totalQuantity);
            $currentPrice = $priceService->priceFor($asset);

            $entriesWithCostBasis = $typeEntries->filter(fn ($entry) => $entry->cost_basis !== null);
            $totalCostBasisQuantity = (float) $entriesWithCostBasis->sum('quantity');

            $totalCostBasisInToman = $entriesWithCostBasis->sum(function ($entry) use ($currencyConverter): float {
                $costPerUnit = (float) $entry->cost_basis;
                if ($entry->cost_basis_currency && $entry->cost_basis_currency !== Currency::Toman->value) {
                    $fromCurrency = Currency::tryFrom($entry->cost_basis_currency);
                    if ($fromCurrency !== null) {
                        $costPerUnit = $currencyConverter->convert($costPerUnit, $fromCurrency, Currency::Toman);
                    }
                }

                return $costPerUnit * (float) $entry->quantity;
            });

            $averageCostBasisInToman = $totalCostBasisQuantity > 0
                ? $totalCostBasisInToman / $totalCostBasisQuantity
                : null;

            $currentValueForCostUnits = $priceService->valueOf($asset, $totalCostBasisQuantity);

            $profitAndLoss = $totalCostBasisQuantity > 0
                ? $currentValueForCostUnits - $totalCostBasisInToman
                : null;

            $profitAndLossPercent = ($totalCostBasisInToman > 0)
                ? round(($profitAndLoss / $totalCostBasisInToman) * 100, 2)
                : null;

            if ($totalCostBasisQuantity > 0) {
                $hasCostBasisData = true;
                $totalCurrentValueForCostUnits += $currentValueForCostUnits;
                $totalCostBasis += $totalCostBasisInToman;
            }

            $assets[] = [
                'key' => $asset->slug,
                'id' => $asset->id,
                'label' => $asset->label(),
                'icon' => $asset->icon,
                'icon_svg' => $asset->icon_svg,
                'color' => $asset->color,
                'unit' => $asset->unit,
                'quantity' => round($totalQuantity, 8),
                'current_price' => $currentPrice,
                'current_price_formatted' => $fmt($currentPrice),
                'current_value' => $currentValue,
                'current_value_formatted' => $fmt($currentValue),
                'price_available' => $priceService->priceAvailableFor($asset),
                'avg_cost_basis' => $averageCostBasisInToman,
                'avg_cost_basis_formatted' => $averageCostBasisInToman !== null ? $fmt($averageCostBasisInToman) : null,
                'total_cost' => $totalCostBasisQuantity > 0 ? $totalCostBasisInToman : null,
                'total_cost_formatted' => $totalCostBasisQuantity > 0 ? $fmt($totalCostBasisInToman) : null,
                'pnl' => $profitAndLoss,
                'pnl_formatted' => $profitAndLoss !== null ? $fmt(abs($profitAndLoss)) : null,
                'pnl_percent' => $profitAndLossPercent,
                'pnl_is_positive' => $profitAndLoss !== null ? $profitAndLoss >= 0 : null,
                'entries_count' => $typeEntries->count(),
            ];

            $totalCurrentValue += $currentValue;
        }

        usort($assets, fn ($leftAsset, $rightAsset) => $rightAsset['current_value'] <=> $leftAsset['current_value']);

        $totalProfitAndLoss = $hasCostBasisData
            ? $totalCurrentValueForCostUnits - $totalCostBasis
            : null;
        $totalProfitAndLossPercent = ($hasCostBasisData && $totalCostBasis > 0)
            ? round(($totalProfitAndLoss / $totalCostBasis) * 100, 2)
            : null;

        return [
            'assets' => $assets,
            'summary' => [
                'total_current_value' => $totalCurrentValue,
                'total_current_value_formatted' => $fmt($totalCurrentValue),
                'total_cost_basis' => $totalCostBasis,
                'total_cost_basis_formatted' => $fmt($totalCostBasis),
                'total_pnl' => $totalProfitAndLoss,
                'total_pnl_formatted' => $totalProfitAndLoss !== null ? $fmt(abs($totalProfitAndLoss)) : null,
                'total_pnl_percent' => $totalProfitAndLossPercent,
                'total_pnl_is_positive' => $totalProfitAndLoss !== null ? $totalProfitAndLoss >= 0 : null,
                'has_cost_basis_data' => $hasCostBasisData,
                'asset_count' => count($assets),
            ],
        ];
    }
}
