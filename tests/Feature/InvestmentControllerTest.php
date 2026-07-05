<?php

use App\Enums\AssetType;
use App\Enums\Currency;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

test('it stores total investment cost as per unit cost basis', function () {
    $user = User::factory()->create();

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
    $user = User::factory()->create();
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
    $user = User::factory()->create();
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

    $user = User::factory()->create();
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
                ->where('marketPriceRows', function (array $rows): bool {
                    $commonAssetKeys = collect($rows[0]['assets'])->pluck('key');
                    $customAssets = collect($rows[1]['assets']);

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
