<?php

use App\Actions\Features\FeatureToggleResult;
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
            ->has('modules', 7)
            ->has('coreModules', 2)
            ->where('modules.0.key', Feature::Bills->value)
            ->where('modules.0.enabled', false)
            ->where('modules.0.manage_url', null)
            ->where('modules.1.key', Feature::Investments->value)
            ->where('modules.1.enabled', false)
            ->where('modules.2.key', Feature::Portfolio->value)
            ->where('modules.2.enabled', false)
            ->where('modules.2.requires', ['Investments'])
            // The one optional module that ships on: it has no page of its own,
            // so shipping it off would mean nobody ever finds it. It also has no
            // sidebar entry, so the hide-from-menu control must not be offered.
            ->where('modules.3.key', Feature::Gamification->value)
            ->where('modules.3.enabled', true)
            ->where('modules.3.in_nav', false)
            // Depends only on Transactions, which is core — so it advertises no
            // requirement even though it has one.
            ->where('modules.3.requires', [])
            ->where('modules.4.key', Feature::AiAssistant->value)
            ->where('modules.4.enabled', false)
            ->where('modules.5.key', Feature::TelegramBot->value)
            ->where('modules.5.enabled', false)
            // Advertised on the page, but switched from its own — the card is a
            // link rather than a toggle.
            ->where('modules.6.key', Feature::Vault->value)
            ->where('modules.6.enabled', false)
            ->where('modules.6.manage_url', route('security.edit'))
        );
});

test('the vault cannot be armed through the generic modules endpoint', function () {
    $user = User::factory()->create();

    // Arming the vault requires the browser to wrap the data key first. A plain
    // PATCH would null the server's copy with nothing wrapped in its place, which
    // is unrecoverable — so the endpoint must refuse outright.
    $this->actingAs($user)
        ->patch(route('modules.update'), ['feature' => 'vault', 'enabled' => true])
        ->assertSessionHasErrors('feature');

    expect($user->fresh()->hasFeature(Feature::Vault))->toBeFalse()
        ->and($user->fresh()->features()->count())->toBe(0);
});

test('the update action refuses a self-managed feature even if validation is bypassed', function () {
    $user = User::factory()->create();

    $result = app(UpdateUserFeature::class)($user, Feature::Vault, true);

    expect($result->rejected)->toBe(FeatureToggleResult::REJECTED_SELF_MANAGED)
        ->and($user->fresh()->hasFeature(Feature::Vault))->toBeFalse();
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
            ->where('features.ai_assistant.enabled', false)
            ->where('features.telegram_bot.enabled', false)
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
