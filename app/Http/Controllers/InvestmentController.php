<?php

namespace App\Http\Controllers;

use App\Actions\Investments\SaveInvestment;
use App\Actions\Transactions\CurrencyConverter;
use App\Enums\Currency;
use App\Http\Resources\InvestmentAssetResource;
use App\Models\AssetPriceSnapshot;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Services\AssetPriceService;
use App\Support\CurrencyPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentController extends Controller
{
    public function index(Request $request, AssetPriceService $priceService, CurrencyConverter $currencyConverter): Response
    {
        $user = $request->user();
        $range = in_array($request->query('range'), ['1w', '1m', '3m', '1y', 'all'])
            ? (string) $request->query('range')
            : '1m';
        $selectedCurrency = CurrencyPreference::resolve($request);

        $fmt = function (float $amount) use ($selectedCurrency, $currencyConverter): string {
            if ($selectedCurrency === Currency::Toman) {
                return $this->formatMoney($amount);
            }

            return number_format($currencyConverter->convert($amount, Currency::Toman, $selectedCurrency), 2, '.', ',');
        };

        $assetOptions = InvestmentAsset::query()
            ->availableFor($user)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        /** @var Collection<int, Investment> $allEntries */
        $allEntries = $user->investments()
            ->with('asset')
            ->orderBy('occurred_at')
            ->orderBy('created_at')
            ->get();

        $holdings = $this->computeHoldings($allEntries);

        $assetTypes = $assetOptions->map(fn (InvestmentAsset $asset) => (new InvestmentAssetResource($asset))->resolve($request));

        $recentEntries = $user->investments()
            ->with('asset')
            ->orderByDesc('occurred_at')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(function (Investment $investment) {
                $asset = $investment->asset;

                return [
                    'id' => $investment->id,
                    'investment_asset_id' => $asset?->id,
                    'asset_type' => $asset?->slug ?? $investment->asset_type,
                    'asset_label' => $asset?->label() ?? $investment->asset_type,
                    'asset_icon' => $asset?->icon,
                    'asset_icon_svg' => $asset?->icon_svg,
                    'asset_color' => $asset?->color ?? '#02CD86',
                    'asset_unit' => $asset?->unit ?? '',
                    'quantity' => (float) $investment->quantity,
                    'cost_basis' => $investment->cost_basis !== null ? (float) $investment->cost_basis : null,
                    'cost_basis_currency' => $investment->cost_basis_currency,
                    'note' => $investment->note,
                    'occurred_at' => $investment->occurred_at->toDateString(),
                ];
            });

        return Inertia::render('Investments', [
            'assetTypes' => $assetTypes,
            'entries' => $recentEntries,
            'sourceTypes' => $this->sourceTypes(),
            'selectedRange' => $range,
            'currencies' => collect(Currency::cases())->map(fn (Currency $c) => [
                'label' => $c->label(),
                'value' => $c->value,
            ]),
            'selectedCurrency' => $selectedCurrency->value,
            'entryCount' => $allEntries->count(),
            'assetTypeCount' => count($holdings),
            'assets' => Inertia::defer(function () use ($holdings, $priceService, $fmt) {
                $assets = $this->buildAssets($holdings, $priceService);
                $totalValue = array_sum(array_column($assets, 'value'));

                return array_map(function (array $asset) use ($totalValue, $fmt) {
                    return [
                        ...$asset,
                        'allocation' => $totalValue > 0 ? round($asset['value'] / $totalValue * 100, 1) : 0,
                        'price_formatted' => $fmt($asset['price']),
                        'value_formatted' => $fmt($asset['value']),
                    ];
                }, $assets);
            }),
            'summary' => Inertia::defer(function () use ($holdings, $priceService, $fmt, $allEntries) {
                $assets = $this->buildAssets($holdings, $priceService);
                $totalValue = array_sum(array_column($assets, 'value'));

                return [
                    'total_value' => $totalValue,
                    'total_value_formatted' => $fmt($totalValue),
                    'asset_count' => count($assets),
                    'entry_count' => $allEntries->count(),
                ];
            }),
            'chartData' => Inertia::defer(fn () => $this->generateChartData($allEntries, $range, $priceService)),
            'prices' => Inertia::defer(fn () => $assetOptions
                ->mapWithKeys(fn (InvestmentAsset $asset) => [$asset->slug => $priceService->priceFor($asset)])
                ->all()),
            'marketPriceRows' => Inertia::defer(fn () => [
                [
                    'key' => 'default',
                    'title' => __('finance.investments.market_prices_common'),
                    'assets' => $this->buildMarketPriceAssets(
                        $assetOptions->where('is_default', true),
                        $priceService,
                        $currencyConverter,
                        $selectedCurrency,
                    ),
                ],
                [
                    'key' => 'custom',
                    'title' => __('finance.investments.market_prices_custom'),
                    'assets' => $this->buildMarketPriceAssets(
                        $assetOptions->where('is_default', false),
                        $priceService,
                        $currencyConverter,
                        $selectedCurrency,
                    ),
                ],
            ]),
            'pricesAvailable' => Inertia::defer(fn () => $priceService->pricesAvailable()),
            'pricesSyncedAt' => $priceService->lastSyncedAt(),
        ]);
    }

    public function store(Request $request, SaveInvestment $saveInvestment): RedirectResponse
    {
        $validated = $this->validatedInvestmentData($request);

        $saveInvestment->create($request->user(), $validated);

        return redirect()->back();
    }

    public function update(Request $request, Investment $investment, SaveInvestment $saveInvestment): RedirectResponse
    {
        abort_unless((int) $investment->user_id === (int) $request->user()->id, 404);

        $validated = $this->validatedInvestmentData($request);

        $saveInvestment->update($investment, $validated);

        return redirect()->back();
    }

    public function destroy(Request $request, Investment $investment): RedirectResponse
    {
        abort_unless((int) $investment->user_id === (int) $request->user()->id, 404);

        $investment->delete();

        return redirect()->back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedInvestmentData(Request $request): array
    {
        $validated = $request->validate(SaveInvestment::rules());

        return SaveInvestment::normalize($request->user(), $validated);
    }

    /**
     * @param  Collection<int, Investment>  $entries
     * @return array<int, array{asset: InvestmentAsset, quantity: float}>
     */
    private function computeHoldings(Collection $entries): array
    {
        $holdings = [];
        foreach ($entries as $entry) {
            if (! $entry->asset) {
                continue;
            }

            $assetId = (int) $entry->asset->id;
            $holdings[$assetId] ??= [
                'asset' => $entry->asset,
                'quantity' => 0.0,
            ];
            $holdings[$assetId]['quantity'] += (float) $entry->quantity;
        }

        return $holdings;
    }

    /**
     * @param  array<int, array{asset: InvestmentAsset, quantity: float}>  $holdings
     * @return array<int, array<string, mixed>>
     */
    private function buildAssets(array $holdings, AssetPriceService $priceService): array
    {
        $assets = [];
        foreach ($holdings as $holding) {
            $asset = $holding['asset'];
            $quantity = $holding['quantity'];
            $price = $priceService->priceFor($asset);
            $value = $priceService->valueOf($asset, $quantity);

            $assets[] = [
                'key' => $asset->slug,
                'id' => $asset->id,
                'label' => $asset->label(),
                'icon' => $asset->icon,
                'icon_svg' => $asset->icon_svg,
                'color' => $asset->color,
                'unit' => $asset->unit,
                'quantity' => round($quantity, 8),
                'quantity_display' => $this->formatQuantity($quantity, $asset),
                'price' => $price,
                'price_available' => $priceService->priceAvailableFor($asset),
                'price_formatted' => $this->formatMoney($price),
                'value' => $value,
                'value_formatted' => $this->formatMoney($value),
            ];
        }

        usort($assets, fn ($leftAsset, $rightAsset) => $rightAsset['value'] <=> $leftAsset['value']);

        return $assets;
    }

    /**
     * Build ApexCharts-ready series + categories for the selected time range.
     *
     * @param  Collection<int, Investment>  $entries
     * @return array{categories: list<string>, series: list<array<string, mixed>>}
     */
    private function generateChartData(Collection $entries, string $range, AssetPriceService $priceService): array
    {
        $entries = $entries->filter(fn (Investment $entry) => $entry->asset !== null);

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

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 0, '.', ',');
    }

    /**
     * @param  Collection<int, InvestmentAsset>  $assets
     * @return list<array<string, mixed>>
     */
    private function buildMarketPriceAssets(
        Collection $assets,
        AssetPriceService $priceService,
        CurrencyConverter $currencyConverter,
        Currency $selectedCurrency,
    ): array {
        return $assets
            ->reject(fn (InvestmentAsset $asset): bool => $this->isSelectedCurrencyAsset($asset, $selectedCurrency))
            ->map(function (InvestmentAsset $asset) use ($priceService, $currencyConverter, $selectedCurrency): array {
                $price = $priceService->priceFor($asset);
                $convertedPrice = $currencyConverter->convert($price, Currency::Toman, $selectedCurrency);

                return [
                    'id' => $asset->id,
                    'key' => $asset->slug,
                    'label' => $asset->label(),
                    'icon' => $asset->icon,
                    'icon_svg' => $asset->icon_svg,
                    'color' => $asset->color,
                    'unit' => $asset->unit,
                    'price' => $convertedPrice,
                    'price_available' => $price > 0,
                    'price_formatted' => $this->formatConvertedMoney($convertedPrice, $selectedCurrency),
                ];
            })
            ->values()
            ->all();
    }

    private function isSelectedCurrencyAsset(InvestmentAsset $asset, Currency $selectedCurrency): bool
    {
        return match ($selectedCurrency) {
            Currency::Usd => $asset->slug === 'usd',
            Currency::Eur => $asset->slug === 'eur',
            Currency::Toman => in_array($asset->slug, ['toman', 'irr', 'rial'], true),
        };
    }

    private function formatConvertedMoney(float $amount, Currency $currency): string
    {
        return number_format($amount, $currency === Currency::Toman ? 0 : 2, '.', ',');
    }

    private function formatQuantity(float $quantity, InvestmentAsset $asset): string
    {
        return $asset->slug === 'bitcoin' || strtoupper($asset->unit) === 'BTC'
            ? number_format($quantity, 6)
            : number_format($quantity, 2);
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
