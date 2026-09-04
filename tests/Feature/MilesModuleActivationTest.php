<?php

use App\Actions\Miles\ActivateUserFeature;
use App\Actions\Miles\AdjustMiles;
use App\Enums\Feature;
use App\Enums\MilesReason;
use App\Enums\Milestone;
use App\Models\User;

test('welcome miles unlock six optional modules once', function () {
    $user = User::factory()->create();
    app(AdjustMiles::class)($user, 150, MilesReason::Welcome, 'welcome-test');
    $activate = app(ActivateUserFeature::class);

    foreach ([Feature::Bills, Feature::Budgets, Feature::Investments, Feature::Portfolio, Feature::Goals, Feature::AiAssistant] as $feature) {
        $activate($user, $feature, true);
    }

    expect($user->featureUnlocks()->count())->toBe(6)
        ->and($user->mileWallet()->value('balance'))->toBe(0);

    $activate($user, Feature::Bills, false);
    $activate($user, Feature::Bills, true);

    expect($user->mileWallet()->value('balance'))->toBe(0)
        ->and($user->featureUnlocks()->where('feature', Feature::Bills->value)->count())->toBe(1);
});

test('dependency closure is quoted and charged together', function () {
    $user = User::factory()->create();
    app(AdjustMiles::class)($user, 50, MilesReason::AdminAdjustment, 'seed');
    $activate = app(ActivateUserFeature::class);

    expect($activate->quote($user, Feature::Goals))->toMatchArray(['cost' => 50]);
    $activate($user, Feature::Goals, true);

    expect($user->featureUnlocks()->pluck('feature')->map->value->all())
        ->toContain(Feature::Goals->value, Feature::Investments->value)
        ->and($user->mileWallet()->value('balance'))->toBe(0);
});

test('vault and advisor are never activation purchases', function () {
    $user = User::factory()->create();
    $activate = app(ActivateUserFeature::class);

    expect($activate->quote($user, Feature::Vault)['cost'])->toBe(0)
        ->and($activate->quote($user, Feature::Advisor)['cost'])->toBe(0);
});

test('an unaffordable module activation returns the structured 402', function () {
    $user = User::factory()->create();
    $user->milestones()->create(['key' => Milestone::VerifiedEmail, 'achieved_at' => now()]);

    $this->actingAs($user)->patch(route('modules.update'), [
        'feature' => Feature::Bills->value,
        'enabled' => true,
    ])->assertStatus(402)->assertJson([
        'error' => 'insufficient_miles',
        'available' => 0,
        'cost' => 25,
        'shortfall' => 25,
        'action' => 'module_unlock',
    ]);
});
