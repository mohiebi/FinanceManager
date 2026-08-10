<?php

namespace App\Actions\Investments;

use App\Actions\Transactions\CurrencyConverter;
use App\Enums\AssetType;
use App\Enums\Currency;
use App\Models\AssetPriceSnapshot;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\User;
use App\Services\AssetPriceService;
use App\Support\Encryption\EncryptedValue;
use Illuminate\Support\Carbon;
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
     * Everything the browser needs to build the breakdown itself.
     *
     * Used when the owner's vault is armed: quantities and cost bases are
     * ciphertext, so the sums have to happen client-side. Prices and exchange
     * rates are public market data, so shipping them costs nothing in privacy —
     * see the mirrored implementation in resources/js/lib/portfolio.ts.
     *
     * @return array{entries: array<int, array<string, mixed>>, assets: array<int, array<string, mixed>>, rates: array{tomanPerUsd: float, tomanPerEur: float}}
     */
    public function clientPayload(User $user, Currency $selectedCurrency): array
    {
        $entries = $this->entriesFor($user);

        $assets = $entries
            ->map(fn (Investment $entry): ?InvestmentAsset => $entry->asset)
            ->filter()
            ->unique('id')
            ->map(fn (InvestmentAsset $asset): array => [
                'id' => $asset->id,
                'key' => $asset->slug,
                'label' => $asset->label(),
                'icon' => $asset->icon,
                'icon_svg' => $asset->icon_svg,
                'color' => $asset->color,
                'unit' => $asset->unit,
                'price' => $this->priceService->priceFor($asset),
                'price_available' => $this->priceService->priceAvailableFor($asset),
            ])
            ->values()
            ->all();

        return [
            'entries' => $entries
                ->map(fn (Investment $entry): array => [
                    'id' => $entry->id,
                    'investment_asset_id' => $entry->investment_asset_id,
                    // Plaintext, and the only thing telling the browser which rows
                    // are disposals — the sign is inside the ciphertext.
                    'kind' => $entry->kind->value,
                    // Plaintext on the row, and already sent to the investments
                    // page. Savings goals need it to tell holdings bought before
                    // a goal started from progress made toward it.
                    'occurred_at' => $entry->occurred_at->toDateString(),
                    'quantity' => $entry->quantity,
                    'cost_basis' => $entry->cost_basis,
                    'cost_basis_currency' => $entry->cost_basis_currency,
                    'sale_price' => $entry->sale_price,
                    'sale_price_currency' => $entry->sale_price_currency,
                ])
                ->values()
                ->all(),
            'assets' => $assets,
            'rates' => [
                'tomanPerUsd' => $this->priceService->priceFor(AssetType::Usd),
                'tomanPerEur' => $this->priceService->priceFor(AssetType::Eur),
            ],
        ];
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
        $totalRealised = 0.0;
        $hasCostBasisData = false;
        $hasRealisedData = false;

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

            // Locked in at the moment of sale, from what the row already stores, so
            // a later price move can never rewrite a gain the user has banked.
            $disposals = $typeEntries->filter(
                fn (Investment $entry): bool => $entry->isSell() && $entry->sale_price !== null,
            );

            $realised = $disposals->sum(fn (Investment $entry): float => $this->realisedGain($entry));

            if ($disposals->isNotEmpty()) {
                $hasRealisedData = true;
                $totalRealised += $realised;
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
                'realised_pnl' => $disposals->isNotEmpty() ? $realised : null,
                'realised_pnl_formatted' => $disposals->isNotEmpty() ? $fmt(abs($realised)) : null,
                'realised_pnl_is_positive' => $disposals->isNotEmpty() ? $realised >= 0 : null,
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
                // Kept apart from total_pnl on purpose: one is money already banked,
                // the other is a paper figure that moves with the market. Adding
                // them together would be a number that means nothing.
                'total_realised_pnl' => $hasRealisedData ? $totalRealised : null,
                'total_realised_pnl_formatted' => $hasRealisedData ? $fmt(abs($totalRealised)) : null,
                'total_realised_pnl_is_positive' => $hasRealisedData ? $totalRealised >= 0 : null,
                'has_realised_data' => $hasRealisedData,
                'asset_count' => count($assets),
            ],
        ];
    }

    /**
     * What a disposal actually made, in toman.
     *
     * Derived rather than stored: both operands are frozen on the row at sale time,
     * so this cannot drift, and there is no second copy to disagree with.
     */
    private function realisedGain(Investment $entry): float
    {
        $units = abs((float) $entry->quantity);

        $soldFor = $this->toToman((float) $entry->sale_price, $entry->sale_price_currency);
        $paid = $entry->cost_basis === null
            ? 0.0
            : $this->toToman((float) $entry->cost_basis, $entry->cost_basis_currency);

        return ($soldFor - $paid) * $units;
    }

    private function toToman(float $amount, ?string $currency): float
    {
        if ($currency === null || $currency === Currency::Toman->value) {
            return $amount;
        }

        $from = Currency::tryFrom($currency);

        return $from === null
            ? $amount
            : $this->currencyConverter->convert($amount, $from, Currency::Toman);
    }

    /**
     * Build ApexCharts-ready series + categories for the selected time range —
     * the portfolio's "value over time" chart.
     *
     * @param  Collection<int, Investment>  $entries
     * @return array{categories: list<string>, series: list<array<string, mixed>>}
     */
    public function history(Collection $entries, string $range, AssetPriceService $priceService): array
    {
        $entries = $entries->filter(fn (Investment $entry): bool => $entry->asset !== null);

        if ($entries->isEmpty()) {
            return ['categories' => [], 'series' => []];
        }

        $now = Carbon::today();
        $firstEntry = Carbon::parse($entries->min('occurred_at'));

        [$from, $step] = match ($range) {
            '1w' => [$now->copy()->subDays(6), 'day'],
            '3m' => [$now->copy()->subMonths(3), 'week'],
            '1y' => [$now->copy()->subYear(), 'month'],
            'all' => [$firstEntry->copy(), 'month'],
            default => [$now->copy()->subDays(29), 'day'],
        };

        $dates = [];
        $dateCursor = $from->copy();
        while ($dateCursor->lte($now)) {
            $dates[] = $dateCursor->toDateString();
            match ($step) {
                'day' => $dateCursor->addDay(),
                'week' => $dateCursor->addWeek(),
                'month' => $dateCursor->addMonthNoOverflow(),
            };
        }
        if (! in_array($now->toDateString(), $dates, true)) {
            $dates[] = $now->toDateString();
        }

        $groupedEntries = $entries->groupBy('investment_asset_id');

        // Historical prices recorded by the hourly RefreshAssetPricesJob.
        // Dates before the first snapshot fall back to the current price;
        // "today" always uses the live price.
        $snapshotsByAsset = AssetPriceSnapshot::query()
            ->whereIn('investment_asset_id', $groupedEntries->keys())
            ->where('snapped_on', '<=', $now)
            ->orderBy('snapped_on')
            ->get()
            ->groupBy('investment_asset_id');

        $seriesList = [];
        $totalByDate = array_fill_keys($dates, 0.0);

        foreach ($groupedEntries as $assetId => $typeEntries) {
            $asset = $typeEntries->first()?->asset;

            if (! $asset) {
                continue;
            }

            $currentPrice = $priceService->priceFor($asset);
            $snapshots = $snapshotsByAsset->get($assetId, collect())->values();
            $snapshotPointer = 0;
            $lastKnownPrice = null;
            $seriesData = [];

            foreach ($dates as $date) {
                while (
                    $snapshotPointer < $snapshots->count()
                    && $snapshots[$snapshotPointer]->snapped_on->toDateString() <= $date
                ) {
                    $lastKnownPrice = (float) $snapshots[$snapshotPointer]->price;
                    $snapshotPointer++;
                }

                $price = $date === $now->toDateString()
                    ? $currentPrice
                    : ($lastKnownPrice ?? $currentPrice);

                $dateParsed = Carbon::parse($date);
                $cumulativeQuantity = $typeEntries
                    ->filter(fn ($entry) => $entry->occurred_at->lte($dateParsed))
                    ->sum('quantity');
                $value = round((float) $cumulativeQuantity * $price);
                $seriesData[] = $value;
                $totalByDate[$date] += $value;
            }

            $seriesList[] = [
                'name' => $asset->label(),
                'key' => $asset->slug,
                'color' => $asset->color,
                'data' => $seriesData,
            ];
        }

        array_unshift($seriesList, [
            'name' => __('finance.assets.total'),
            'key' => 'total',
            'color' => '#02CD86',
            'data' => array_values($totalByDate),
        ]);

        return [
            'categories' => $dates,
            'series' => $seriesList,
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
