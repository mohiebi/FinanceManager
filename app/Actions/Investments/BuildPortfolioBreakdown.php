<?php

namespace App\Actions\Investments;

use App\Actions\Transactions\CurrencyConverter;
use App\Enums\Currency;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\User;
use App\Services\AssetPriceService;
use App\Support\Encryption\EncryptedValue;
use Illuminate\Support\Collection;

class BuildPortfolioBreakdown
{
    public function __construct(
        private readonly AssetPriceService $priceService,
        private readonly CurrencyConverter $currencyConverter,
    ) {}

    /**
     * @return Collection<int, Investment>
     */
    public function entriesFor(User $user): Collection
    {
        return $user->investments()
            ->with('asset')
            ->orderBy('occurred_at')
            ->orderBy('created_at')
            ->get()
            ->filter(fn (Investment $investment) => $investment->asset !== null);
    }

    /**
     * Format a toman amount in the selected currency.
     */
    public function formatter(Currency $selectedCurrency): callable
    {
        return function (float $amount) use ($selectedCurrency): string {
            if ($selectedCurrency === Currency::Toman) {
                return number_format($amount, 0, '.', ',');
            }

            return number_format(
                $this->currencyConverter->convert($amount, Currency::Toman, $selectedCurrency),
                2,
                '.',
                ',',
            );
        };
    }

    /**
     * @param  Collection<int, Investment>  $allEntries
     * @return array{assets: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function handle(Collection $allEntries, Currency $selectedCurrency): array
    {
        $fmt = $this->formatter($selectedCurrency);
        $allEntries = $allEntries
            ->reject(fn (Investment $entry): bool => $entry->quantity instanceof EncryptedValue)
            ->values();
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
            $currentValue = $this->priceService->valueOf($asset, $totalQuantity);
            $currentPrice = $this->priceService->priceFor($asset);

            $entriesWithCostBasis = $typeEntries->filter(fn ($entry) => $entry->cost_basis !== null);
            $totalCostBasisQuantity = (float) $entriesWithCostBasis->sum('quantity');

            $totalCostBasisInToman = $entriesWithCostBasis->sum(function ($entry): float {
                $costPerUnit = (float) $entry->cost_basis;
                if ($entry->cost_basis_currency && $entry->cost_basis_currency !== Currency::Toman->value) {
                    $fromCurrency = Currency::tryFrom($entry->cost_basis_currency);
                    if ($fromCurrency !== null) {
                        $costPerUnit = $this->currencyConverter->convert($costPerUnit, $fromCurrency, Currency::Toman);
                    }
                }

                return $costPerUnit * (float) $entry->quantity;
            });

            $averageCostBasisInToman = $totalCostBasisQuantity > 0
                ? $totalCostBasisInToman / $totalCostBasisQuantity
                : null;

            $currentValueForCostUnits = $this->priceService->valueOf($asset, $totalCostBasisQuantity);

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
                'price_available' => $this->priceService->priceAvailableFor($asset),
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

    /**
     * Compact snapshot for the dashboard: net worth, P&L, and top holdings.
     *
     * @return array<string, mixed>|null null when the user has no investments yet
     */
    public function snapshot(User $user, Currency $selectedCurrency): ?array
    {
        $entries = $this->entriesFor($user);

        if ($entries->isEmpty()) {
            return null;
        }

        $breakdown = $this->handle($entries, $selectedCurrency);
        $summary = $breakdown['summary'];
        $totalValue = (float) $summary['total_current_value'];

        $topAssets = collect($breakdown['assets'])
            ->take(3)
            ->map(fn (array $asset) => [
                'key' => $asset['key'],
                'label' => $asset['label'],
                'color' => $asset['color'],
                'icon' => $asset['icon'],
                'icon_svg' => $asset['icon_svg'],
                'value_formatted' => $asset['current_value_formatted'],
                'share' => $totalValue > 0
                    ? round(((float) $asset['current_value'] / $totalValue) * 100)
                    : 0,
            ])
            ->values()
            ->all();

        return [
            'net_worth_formatted' => $summary['total_current_value_formatted'],
            'pnl_percent' => $summary['total_pnl_percent'],
            'pnl_is_positive' => $summary['total_pnl_is_positive'],
            'pnl_formatted' => $summary['total_pnl_formatted'],
            'has_cost_basis_data' => $summary['has_cost_basis_data'],
            'asset_count' => $summary['asset_count'],
            'top_assets' => $topAssets,
        ];
    }
}
