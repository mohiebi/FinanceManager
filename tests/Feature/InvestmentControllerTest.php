<?php

use App\Enums\AssetType;
use App\Enums\Currency;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\User;

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
