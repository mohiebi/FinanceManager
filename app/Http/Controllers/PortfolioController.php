<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\CurrencyConverter;
use App\Enums\AssetType;
use App\Enums\Currency;
use App\Models\Investment;
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
            ->orderBy('occurred_at')
            ->orderBy('created_at')
            ->get();

        return Inertia::render('Portfolio', [
            'currencies' => collect(Currency::cases())->map(fn (Currency $c) => [
                'label' => strtoupper($c->value),
                'value' => $c->value,
            ]),
            'selectedCurrency' => $selectedCurrency->value,
            'assets' => Inertia::defer(fn () => $this->buildAssetBreakdown($allEntries, $priceService, $currencyConverter, $fmt)['assets']),
            'summary' => Inertia::defer(fn () => $this->buildAssetBreakdown($allEntries, $priceService, $currencyConverter, $fmt)['summary']),
            'pricesAvailable' => Inertia::defer(fn () => $priceService->pricesAvailable()),
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
        $grouped = $allEntries->groupBy(fn (Investment $investment) => $investment->asset_type->value);

        $assets = [];
        $totalCurrentValue = 0.0;
        // Track only the current value and cost of units that actually have cost-basis data,
        // so the summary P/L compares like-for-like instead of mixing cost-bearing and
        // non-cost-bearing assets on different sides of the equation.
        $totalCurrentValueForCostUnits = 0.0;
        $totalCostBasis = 0.0;
        $hasCostBasisData = false;

        foreach ($grouped as $typeValue => $typeEntries) {
            $type = AssetType::from($typeValue);
            $totalQuantity = (float) $typeEntries->sum('quantity');
            $currentValue = $priceService->valueOf($type, $totalQuantity);
            $currentPrice = $priceService->priceFor($type);

            // --- Cost-basis entries only --------------------------------
            $entriesWithCostBasis = $typeEntries->filter(fn ($entry) => $entry->cost_basis !== null);
            $totalCostBasisQuantity = (float) $entriesWithCostBasis->sum('quantity');

            // Convert every entry's cost_basis to Toman before summing so that
            // mixed-currency portfolios (e.g. USD cost_basis vs Toman price) stay consistent.
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

            // Average cost per unit (in Toman) — used for display only.
            $averageCostBasisInToman = $totalCostBasisQuantity > 0
                ? $totalCostBasisInToman / $totalCostBasisQuantity
                : null;

            // Current value of ONLY the units that have known cost data.
            // Comparing against this (instead of the full holding value) gives a
            // correct P/L even when some entries lack a cost_basis.
            $currentValueForCostUnits = $priceService->valueOf($type, $totalCostBasisQuantity);

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
                'key' => $type->value,
                'label' => $type->label(),
                'icon' => $type->icon(),
                'color' => $type->color(),
                'unit' => $type->unit(),
                'quantity' => round($totalQuantity, 8),
                'current_price' => $currentPrice,
                'current_price_formatted' => $fmt($currentPrice),
                'current_value' => $currentValue,
                'current_value_formatted' => $fmt($currentValue),
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

        // Summary P/L: compare the current value of cost-bearing units only
        // against what was actually paid for them.
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
