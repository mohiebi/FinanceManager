<?php

use App\Enums\AssetType;
use App\Enums\Currency;
use App\Models\AssetPriceSnapshot;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

test('it stores total investment cost as per unit cost basis', function () {
    $user = User::factory()->withModules()->create();

    $this->actingAs($user)
        ->post(route('investments.store'), [
            'asset_type' => AssetType::Gold->value,
            'quantity' => '2',
            'total_cost' => '1000000',
            'cost_basis_currency' => Currency::Toman->value,
            'occurred_at' => now()->toDateString(),
        ])
        ->assertRedirect();

    $investment = Investment::query()->sole();

    expect((float) $investment->cost_basis)->toBe(500000.0)
        ->and($investment->cost_basis_currency)->toBe(Currency::Toman->value)
        ->and($investment->asset_type)->toBe(AssetType::Gold->value)
        ->and($investment->investment_asset_id)->not->toBeNull();
});

test('it updates total investment cost as per unit cost basis', function () {
    $user = User::factory()->withModules()->create();
    $asset = InvestmentAsset::query()->where('slug', AssetType::Silver->value)->firstOrFail();
    $investment = Investment::query()->create([
        'user_id' => $user->id,
        'investment_asset_id' => $asset->id,
        'asset_type' => AssetType::Silver->value,
        'quantity' => 10,
        'cost_basis' => 100,
        'cost_basis_currency' => Currency::Toman->value,
        'occurred_at' => now()->subDay()->toDateString(),
    ]);

    $this->actingAs($user)
        ->patch(route('investments.update', $investment), [
            'asset_type' => AssetType::Silver->value,
            'quantity' => '4',
            'total_cost' => '1000',
            'cost_basis_currency' => Currency::Toman->value,
            'occurred_at' => now()->toDateString(),
        ])
        ->assertRedirect();

    $investment->refresh();

    expect((float) $investment->cost_basis)->toBe(250.0);
});

test('it stores investments for custom assets', function () {
    $user = User::factory()->withModules()->create();
    $asset = InvestmentAsset::query()->create([
        'user_id' => $user->id,
        'name' => 'Nim half coin',
        'unit' => 'coin',
        'color' => '#02CD86',
        'price_source_type' => 'formula',
        'price_source_config' => ['formula' => 'goldprice * 900 / 750 * 8.133 / 2'],
    ]);

    $this->actingAs($user)
        ->post(route('investments.store'), [
            'investment_asset_id' => $asset->id,
            'quantity' => '1.5',
            'total_cost' => '3000000',
            'cost_basis_currency' => Currency::Toman->value,
            'occurred_at' => now()->toDateString(),
        ])
        ->assertRedirect();

    $investment = Investment::query()->sole();

    expect($investment->investment_asset_id)->toBe($asset->id)
        ->and($investment->asset_type)->toBe('nim-half-coin')
        ->and((float) $investment->quantity)->toBe(1.5);
});

test('investment page shows live common and custom asset prices except the selected currency asset', function () {
    Cache::flush();
    config(['services.tgju.enabled' => true]);
    Cache::put('asset-prices.tgju', [
        'gold_750' => 12000000.0,
        'silver' => 200000.0,
        'usd' => 150000.0,
        'eur' => 175500.0,
        'bitcoin' => 15000000000.0,
    ], now()->addMinutes(5));

    $user = User::factory()->withModules()->create();
    InvestmentAsset::query()->create([
        'user_id' => $user->id,
        'name' => 'Nim half coin',
        'unit' => 'coin',
        'color' => '#02CD86',
        'price_source_type' => 'manual',
        'price_source_config' => ['price' => 300000.0],
    ]);

    $this->actingAs($user)
        ->get(route('investments.index', ['currency' => Currency::Usd->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Investments')
            ->loadDeferredProps('default', fn (Assert $page) => $page
                ->where('marketPriceRows', function (mixed $rows): bool {
                    $priceRows = collect($rows);
                    $commonAssetKeys = collect($priceRows->get(0)['assets'] ?? [])->pluck('key');
                    $customAssets = collect($priceRows->get(1)['assets'] ?? []);

                    expect($commonAssetKeys)
                        ->not->toContain('usd')
                        ->toContain('gold')
                        ->toContain('eur')
                        ->toContain('bitcoin')
                        ->and($customAssets->pluck('key'))->toContain('nim-half-coin')
                        ->and($customAssets->firstWhere('key', 'nim-half-coin')['price_formatted'])->toBe('2.00');

                    return true;
                }),
            ),
        );
});

test('the value-over-time chart prices past dates from snapshots and today from the live price', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-06 12:00:00'));
    Cache::flush();

    try {
        config(['services.tgju.enabled' => true]);
        // Live price today.
        Cache::put('asset-prices.tgju', ['usd' => 150000.0], now()->addMinutes(5));

        $user = User::factory()->withModules()->create();
        $usdAsset = InvestmentAsset::query()->where('slug', 'usd')->firstOrFail();

        Investment::query()->create([
            'user_id' => $user->id,
            'investment_asset_id' => $usdAsset->id,
            'asset_type' => AssetType::Usd->value,
            'quantity' => 100,
            'cost_basis' => 100000,
            'cost_basis_currency' => Currency::Toman->value,
            'occurred_at' => '2026-06-01',
        ]);

        // A recorded historical price, three days ago, lower than today's live price.
        AssetPriceSnapshot::query()->create([
            'investment_asset_id' => $usdAsset->id,
            'price' => 120000.0,
            'snapped_on' => '2026-07-03',
        ]);

        $this->actingAs($user)
            ->get(route('investments.index', ['range' => '1w']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Investments')
                ->loadDeferredProps('default', fn (Assert $page) => $page
                    ->where('chartData', function (mixed $chartData): bool {
                        $data = collect($chartData);
                        $dates = collect($data->get('categories'));
                        $total = collect($data->get('series'))->firstWhere('key', 'total');
                        $values = collect($total['data']);

                        $valueOn = fn (string $date) => (float) $values->get($dates->search($date));

                        // Snapshot day: 100 units × 120,000 historical price.
                        expect($valueOn('2026-07-03'))->toBe(12000000.0)
                            // Today: 100 units × 150,000 live price.
                            ->and($valueOn('2026-07-06'))->toBe(15000000.0);

                        return true;
                    }),
                ),
            );
    } finally {
        Carbon::setTestNow();
    }
});
