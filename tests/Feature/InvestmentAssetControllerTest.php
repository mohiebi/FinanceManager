<?php

use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\User;

test('users can create private investment assets', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('investment-assets.store'), [
            'name' => 'Nim half coin',
            'unit' => 'coin',
            'icon_svg' => '<svg viewBox="0 0 24 24" onclick="bad()"><path d="M1 1h22v22H1z" fill="currentColor"/></svg>',
            'color' => '#02CD86',
            'price_source_type' => 'formula',
            'price_source_config' => [
                'formula' => 'goldprice * 900 / 750 * 8.133 / 2',
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('createdInvestmentAsset');

    $asset = InvestmentAsset::query()->where('user_id', $user->id)->sole();

    expect($asset->name)->toBe('Nim half coin')
        ->and($asset->slug)->toBe('nim-half-coin')
        ->and($asset->icon_svg)->toContain('<svg')
        ->and($asset->icon_svg)->not->toContain('onclick')
        ->and($asset->is_default)->toBeFalse()
        ->and($asset->price_source_config)->toBe([
            'formula' => 'goldprice * 900 / 750 * 8.133 / 2',
        ]);
});

test('invalid svg icons are rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('investment-assets.store'), [
            'name' => 'Bad icon asset',
            'unit' => 'unit',
            'icon_svg' => '<script>alert(1)</script>',
            'price_source_type' => 'manual',
            'price_source_config' => ['price' => 100],
        ])
        ->assertSessionHasErrors('icon_svg');
});

test('users cannot duplicate available asset slugs', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('investment-assets.store'), [
            'name' => 'Gold',
            'unit' => 'g',
            'price_source_type' => 'manual',
            'price_source_config' => ['price' => 100],
        ])
        ->assertSessionHasErrors('name');
});

test('users can update only their custom assets', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $asset = InvestmentAsset::query()->create([
        'user_id' => $user->id,
        'name' => 'Custom metal',
        'unit' => 'g',
        'color' => '#02CD86',
        'price_source_type' => 'manual',
        'price_source_config' => ['price' => 100],
    ]);

    $this->actingAs($otherUser)
        ->patch(route('investment-assets.update', $asset), [
            'name' => 'Nope',
            'unit' => 'g',
            'color' => '#02CD86',
            'price_source_type' => 'manual',
            'price_source_config' => ['price' => 200],
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('investment-assets.update', $asset), [
            'name' => 'Custom metal plus',
            'unit' => 'gram',
            'color' => '#10B981',
            'price_source_type' => 'manual',
            'price_source_config' => ['price' => 250],
        ])
        ->assertRedirect();

    $asset->refresh();

    expect($asset->name)->toBe('Custom metal plus')
        ->and($asset->unit)->toBe('gram')
        ->and($asset->price_source_config)->toBe(['price' => 250]);
});

test('used custom assets cannot be deleted', function () {
    $user = User::factory()->create();
    $asset = InvestmentAsset::query()->create([
        'user_id' => $user->id,
        'name' => 'Custom bond',
        'unit' => 'unit',
        'color' => '#02CD86',
        'price_source_type' => 'manual',
        'price_source_config' => ['price' => 100],
    ]);

    Investment::query()->create([
        'user_id' => $user->id,
        'investment_asset_id' => $asset->id,
        'asset_type' => $asset->slug,
        'quantity' => 1,
        'occurred_at' => now()->toDateString(),
    ]);

    $this->actingAs($user)
        ->delete(route('investment-assets.destroy', $asset))
        ->assertSessionHasErrors('investment_asset');

    expect($asset->fresh())->not->toBeNull();
});

test('unused custom assets can be deleted', function () {
    $user = User::factory()->create();
    $asset = InvestmentAsset::query()->create([
        'user_id' => $user->id,
        'name' => 'Unused custom asset',
        'unit' => 'unit',
        'color' => '#02CD86',
        'price_source_type' => 'manual',
        'price_source_config' => ['price' => 100],
    ]);

    $this->actingAs($user)
        ->delete(route('investment-assets.destroy', $asset))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($asset->fresh())->toBeNull();
});
