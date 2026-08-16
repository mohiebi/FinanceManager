<?php

namespace App\Http\Controllers;

use App\Actions\Investments\RecordInvestmentTransaction;
use App\Actions\Investments\SaveInvestment;
use App\Actions\Transactions\CurrencyConverter;
use App\Enums\Currency;
use App\Http\Resources\InvestmentAssetResource;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Services\AssetPriceService;
use App\Support\CurrencyPreference;
use App\Support\Encryption\SealedField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentController extends Controller
{
    public function index(Request $request, AssetPriceService $priceService, CurrencyConverter $currencyConverter): Response
    {
        $user = $request->user();
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

        $vaultArmed = $user->vaultIsArmed();
        $holdings = $vaultArmed ? [] : $this->computeHoldings($allEntries);

        $assetTypes = $assetOptions->map(fn (InvestmentAsset $asset) => (new InvestmentAssetResource($asset))->resolve($request));

        $recentEntries = $user->investments()
            ->with('asset')
            ->orderByDesc('occurred_at')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(function (Investment $investment) use ($vaultArmed) {
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
                    // Plaintext even with the vault armed — it is the only thing
                    // that tells a sale from a purchase once the quantity that
                    // carries the sign is ciphertext. Named as the portfolio
                    // payload names it, which ships it for the same reason.
                    'kind' => $investment->kind->value,
                    'quantity' => $vaultArmed ? $investment->quantity : (float) $investment->quantity,
                    'cost_basis' => $vaultArmed || $investment->cost_basis === null ? $investment->cost_basis : (float) $investment->cost_basis,
                    'cost_basis_currency' => $investment->cost_basis_currency,
                    'note' => $investment->note,
                    'occurred_at' => $investment->occurred_at->toDateString(),
                ];
            });

        return Inertia::render('Investments', [
            'assetTypes' => $assetTypes,
            'entries' => $recentEntries,
            'sourceTypes' => $this->sourceTypes(),
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
            'summary' => Inertia::defer(function () use ($vaultArmed, $holdings, $priceService, $fmt, $allEntries) {
                if ($vaultArmed) {
                    return null;
                }

                $assets = $this->buildAssets($holdings, $priceService);
                $totalValue = array_sum(array_column($assets, 'value'));

                return [
                    'total_value' => $totalValue,
                    'total_value_formatted' => $fmt($totalValue),
                    'asset_count' => count($assets),
                    'entry_count' => $allEntries->count(),
                ];
            }),
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

    public function store(
        Request $request,
        SaveInvestment $saveInvestment,
        RecordInvestmentTransaction $recordTransaction,
    ): RedirectResponse {
        $validated = $this->validatedInvestmentData($request);

        $investment = $saveInvestment->create($request->user(), $validated);

        if ($request->boolean('record_transaction')) {
            $recordTransaction->forPurchase(
                $request->user(),
                $investment,
                $investment->asset()->first(),
                $this->sealedTransaction($request),
            );
        }

        return redirect()->back();
    }

    /**
     * Record a disposal: a second row with a negative quantity, never an edit of
     * the purchase it came from.
     */
    public function sell(
        Request $request,
        SaveInvestment $saveInvestment,
        RecordInvestmentTransaction $recordTransaction,
    ): RedirectResponse {
        $user = $request->user();
        $vaultArmed = $user->vaultIsArmed();

        $validated = $request->validate(SaveInvestment::sellRules($vaultArmed));
        $data = SaveInvestment::normalizeSell($user, $validated, $vaultArmed);

        $investment = $saveInvestment->create($user, $data);

        if ($request->boolean('record_transaction')) {
            $recordTransaction->forSale(
                $user,
                $investment,
                $investment->asset()->first(),
                $this->sealedTransaction($request),
            );
        }

        return redirect()->back();
    }

    /**
     * The transaction fields the browser sealed, when it had to.
     *
     * Null unless the vault is armed — the server builds its own title and amount
     * whenever it can still read the investment it is mirroring.
     *
     * @return array{title: string, amount: string, description: string|null}|null
     */
    private function sealedTransaction(Request $request): ?array
    {
        if (! $request->user()->vaultIsArmed()) {
            return null;
        }

        $validated = $request->validate([
            'transaction_title' => SealedField::rules(),
            'transaction_amount' => SealedField::rules(),
            'transaction_description' => SealedField::rules(required: false),
        ]);

        return [
            'title' => $validated['transaction_title'],
            'amount' => $validated['transaction_amount'],
            'description' => $validated['transaction_description'] ?? null,
        ];
    }

    /**
     * Edits a purchase. Disposals are deliberately not editable here.
     *
     * This route speaks the buy vocabulary — a positive quantity and a
     * `total_cost` that becomes the per-unit basis — and a sale has none of
     * that: its quantity is stored negative so holdings stay a plain sum, its
     * basis is frozen at the average at sale time, and its proceeds live in
     * `sale_price`. Letting it through rewrote the sign, so a sale of two units
     * became a purchase of two and the holding moved by four in the wrong
     * direction, while `kind` still said `sell` and the realised gain kept
     * being computed from it.
     *
     * A sale is corrected by deleting it and recording it again, which is right
     * whether or not the vault is armed — the sign is the one thing a server
     * that cannot read the quantity could never have restored on its own.
     */
    public function update(Request $request, Investment $investment, SaveInvestment $saveInvestment): RedirectResponse
    {
        abort_unless((int) $investment->user_id === (int) $request->user()->id, 404);
        abort_if($investment->isSell(), 409, __('finance.investments.sell_not_editable'));

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
        $vaultArmed = $request->user()->vaultIsArmed();
        $validated = $request->validate(SaveInvestment::rules($vaultArmed));

        return SaveInvestment::normalize($request->user(), $validated, $vaultArmed);
    }

    /**
     * @param  Collection<int, Investment>  $entries
     * @return array<int, array{asset: InvestmentAsset, quantity: float, cost_quantity: float, cost_total: float}>
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
                'cost_quantity' => 0.0,
                'cost_total' => 0.0,
            ];
            $holdings[$assetId]['quantity'] += (float) $entry->quantity;

            // A disposal carries the average basis with a negative quantity, so
            // both sums stay correct and the per-unit average is unchanged by a
            // partial sale — the same arithmetic BuildPortfolioBreakdown relies on.
            if ($entry->cost_basis !== null) {
                $holdings[$assetId]['cost_quantity'] += (float) $entry->quantity;
                $holdings[$assetId]['cost_total'] += (float) $entry->cost_basis * (float) $entry->quantity;
            }
        }

        return $holdings;
    }

    /**
     * @param  array<int, array{asset: InvestmentAsset, quantity: float, cost_quantity: float, cost_total: float}>  $holdings
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
                // Travels so a disposal can freeze the basis at sale time.
                'avg_cost_basis' => $holding['cost_quantity'] > 0
                    ? $holding['cost_total'] / $holding['cost_quantity']
                    : null,
            ];
        }

        usort($assets, fn ($leftAsset, $rightAsset) => $rightAsset['value'] <=> $leftAsset['value']);

        return $assets;
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
