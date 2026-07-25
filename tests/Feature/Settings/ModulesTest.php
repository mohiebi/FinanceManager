<?php

use App\Actions\Features\UpdateUserFeature;
use App\Enums\Feature;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the modules page lists every toggleable module at its default state', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('modules.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Modules')
            ->has('modules', 3)
            ->has('coreModules', 3)
            ->where('modules.0.key', Feature::Bills->value)
            ->where('modules.0.enabled', false)
            ->where('modules.1.key', Feature::Investments->value)
            ->where('modules.1.enabled', false)
            ->where('modules.2.key', Feature::Portfolio->value)
            ->where('modules.2.enabled', false)
            ->where('modules.2.requires', ['Investments'])
        );
});

test('enabling portfolio also enables investments and says so', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('modules.update'), ['feature' => 'portfolio', 'enabled' => true])
        ->assertRedirect()
        ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'Investments'));

    $fresh = $user->fresh();

    expect($fresh->hasFeature(Feature::Portfolio))->toBeTrue()
        ->and($fresh->hasFeature(Feature::Investments))->toBeTrue();
});

test('disabling investments cascades to portfolio and reports it', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::Portfolio, true);

    $this->actingAs($user)
        ->patch(route('modules.update'), ['feature' => 'investments', 'enabled' => false])
        ->assertRedirect()
        ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'Portfolio'));

    $fresh = $user->fresh();

    expect($fresh->hasFeature(Feature::Investments))->toBeFalse()
        ->and($fresh->hasFeature(Feature::Portfolio))->toBeFalse();
});

test('the modules page reports which enabled modules a disable would take with it', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::Portfolio, true);

    $this->actingAs($user)
        ->get(route('modules.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('modules.1.key', Feature::Investments->value)
            ->where('modules.1.disables', ['Portfolio'])
        );
});

test('core modules cannot be toggled', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('modules.update'), ['feature' => 'transactions', 'enabled' => false])
        ->assertSessionHasErrors('feature');

    expect($user->fresh()->features()->count())->toBe(0);
});

test('a request that changes nothing is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('modules.update'), ['feature' => 'bills'])
        ->assertSessionHasErrors(['enabled', 'show_promo']);
});

test('the resolved feature map is shared with every authenticated page', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::Bills, true);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('features.reports.enabled', true)
            ->where('features.reports.core', true)
            ->where('features.bills.enabled', true)
            ->where('features.investments.enabled', false)
            ->where('features.investments.show_promo', true)
            ->where('features.transactions.core', true)
        );
});

test('a disabled module can be hidden from the menu without enabling it', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('modules.update'), ['feature' => 'bills', 'show_promo' => false])
        ->assertRedirect();

    $fresh = $user->fresh();

    expect($fresh->hasFeature(Feature::Bills))->toBeFalse()
        ->and($fresh->featureSet()->showsPromo(Feature::Bills))->toBeFalse();
});
