<?php

use App\Actions\Features\UpdateUserFeature;
use App\Enums\Feature;
use App\Models\User;

dataset('gated pages', [
    'investments index' => ['investments.index', Feature::Investments],
    'investments export' => ['investments.export', Feature::Investments],
    'asset settings' => ['investment-assets.edit', Feature::Investments],
    'portfolio' => ['portfolio', Feature::Portfolio],
    'portfolio export' => ['portfolio.export', Feature::Portfolio],
    'bills index' => ['bills.index', Feature::Bills],
]);

test('a page whose module is off redirects to the modules page', function (string $routeName, Feature $feature) {
    $user = User::factory()->create();

    // Idempotent: a no-op for modules that already ship disabled.
    app(UpdateUserFeature::class)($user, $feature, false);

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertRedirect(route('modules.edit'))
        ->assertSessionHas('status');
})->with('gated pages');

test('a page whose module is on is reachable', function (string $routeName, Feature $feature) {
    $user = User::factory()->create();

    app(UpdateUserFeature::class)($user, $feature, true);

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertSuccessful();
})->with('gated pages');

test('the gate covers writes, not just pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('asset-prices.sync'))
        ->assertRedirect(route('modules.edit'));
});

test('core pages stay reachable with every optional module off', function () {
    // A brand-new user has every optional module switched off.
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertSuccessful();
    $this->actingAs($user)->get(route('transactions.index'))->assertSuccessful();
    $this->actingAs($user)->get(route('report'))->assertSuccessful();
    $this->actingAs($user)->get(route('modules.edit'))->assertSuccessful();
});

test('reports is core and cannot be switched off', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('modules.update'), ['feature' => 'reports', 'enabled' => false])
        ->assertSessionHasErrors('feature');

    $this->actingAs($user)->get(route('report'))->assertSuccessful();
});
