<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\CurrencyConverter;
use App\Enums\AssetType;
use App\Enums\Currency;
use App\Models\Investment;
use App\Services\AssetPriceService;
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
        $selectedCurrency = Currency::tryFrom((string) $request->query('currency')) ?? Currency::Toman;

        $fmt = function (float $amount) use ($selectedCurrency, $currencyConverter): string {
            if ($selectedCurrency === Currency::Toman) {
                return $this->formatMoney($amount);
            }

            return number_format($currencyConverter->convert($amount, Currency::Toman, $selectedCurrency), 2, '.', ',');
        };

        /** @var Collection<int, Investment> $allEntries */
        $allEntries = $user->investments()
            ->orderBy('occurred_at')
            ->orderBy('created_at')
            ->get();

        $holdings = $this->computeHoldings($allEntries);

        $assetTypes = collect(AssetType::cases())->map(fn (AssetType $assetType) => [
            'value' => $assetType->value,
            'label' => $assetType->label(),
            'unit' => $assetType->unit(),
            'icon' => $assetType->icon(),
            'color' => $assetType->color(),
        ]);

        $recentEntries = $user->investments()
            ->orderByDesc('occurred_at')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (Investment $investment) => [
                'id' => $investment->id,
                'asset_type' => $investment->asset_type->value,
                'asset_label' => $investment->asset_type->label(),
                'asset_icon' => $investment->asset_type->icon(),
                'asset_color' => $investment->asset_type->color(),
                'asset_unit' => $investment->asset_type->unit(),
                'quantity' => (float) $investment->quantity,
                'cost_basis' => $investment->cost_basis !== null ? (float) $investment->cost_basis : null,
                'cost_basis_currency' => $investment->cost_basis_currency,
                'note' => $investment->note,
                'occurred_at' => $investment->occurred_at->toDateString(),
            ]);

        return Inertia::render('Investments', [
            'assetTypes' => $assetTypes,
            'entries' => $recentEntries,
            'selectedRange' => $range,
            'currencies' => collect(Currency::cases())->map(fn (Currency $c) => [
                'label' => strtoupper($c->value),
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
            'prices' => Inertia::defer(fn () => $priceService->allPrices()),
            'pricesAvailable' => Inertia::defer(fn () => $priceService->pricesAvailable()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'asset_type' => ['required', 'string', 'in:'.implode(',', array_column(AssetType::cases(), 'value'))],
            'quantity' => ['required', 'numeric', 'min:0.00000001'],
            'cost_basis' => ['nullable', 'numeric', 'min:0'],
            'cost_basis_currency' => ['nullable', 'string', 'max:10'],
            'note' => ['nullable', 'string', 'max:500'],
            'occurred_at' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $request->user()->investments()->create($validated);

        return redirect()->back();
    }

    public function update(Request $request, Investment $investment): RedirectResponse
    {
        abort_unless((int) $investment->user_id === (int) $request->user()->id, 404);

        $validated = $request->validate([
            'asset_type' => ['required', 'string', 'in:'.implode(',', array_column(AssetType::cases(), 'value'))],
            'quantity' => ['required', 'numeric', 'min:0.00000001'],
            'cost_basis' => ['nullable', 'numeric', 'min:0'],
            'cost_basis_currency' => ['nullable', 'string', 'max:10'],
            'note' => ['nullable', 'string', 'max:500'],
            'occurred_at' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $investment->fill($validated)->save();

        return redirect()->back();
    }

    public function destroy(Request $request, Investment $investment): RedirectResponse
    {
        abort_unless((int) $investment->user_id === (int) $request->user()->id, 404);

        $investment->delete();

        return redirect()->back();
    }

    /** @return array<string, float> */
    private function computeHoldings(Collection $entries): array
    {
        $holdings = [];
        foreach ($entries as $entry) {
            $key = $entry->asset_type->value;
            $holdings[$key] = ($holdings[$key] ?? 0.0) + (float) $entry->quantity;
        }

        return $holdings;
    }

    /** @return array<int, array<string, mixed>> */
    private function buildAssets(array $holdings, AssetPriceService $priceService): array
    {
        $assets = [];
        foreach ($holdings as $typeValue => $quantity) {
            $type = AssetType::from($typeValue);
            $price = $priceService->priceFor($type);
            $value = $priceService->valueOf($type, $quantity);

            $assets[] = [
                'key' => $type->value,
                'label' => $type->label(),
                'icon' => $type->icon(),
                'color' => $type->color(),
                'unit' => $type->unit(),
                'quantity' => round($quantity, 8),
                'quantity_display' => $this->formatQuantity($quantity, $type),
                'price' => $price,
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

        $assetTypes = $entries->pluck('asset_type')->unique()->values();

        $seriesList = [];
        $totalByDate = array_fill_keys($dates, 0.0);

        foreach ($assetTypes as $assetType) {
            $typeEntries = $entries->filter(fn ($entry) => $entry->asset_type === $assetType);
            $price = $priceService->priceFor($assetType);
            $seriesData = [];

            foreach ($dates as $date) {
                $dateParsed = Carbon::parse($date);
                $cumulativeQuantity = $typeEntries
                    ->filter(fn ($entry) => $entry->occurred_at->lte($dateParsed))
                    ->sum('quantity');
                $value = round((float) $cumulativeQuantity * $price);
                $seriesData[] = $value;
                $totalByDate[$date] += $value;
            }

            $seriesList[] = [
                'name' => $assetType->label(),
                'key' => $assetType->value,
                'color' => $assetType->color(),
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

    private function formatQuantity(float $quantity, AssetType $type): string
    {
        return match ($type) {
            AssetType::Bitcoin => number_format($quantity, 6),
            default => number_format($quantity, 2),
        };
    }
}
