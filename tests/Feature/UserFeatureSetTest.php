<?php

use App\Actions\Features\FeatureToggleResult;
use App\Actions\Features\UpdateUserFeature;
use App\Enums\Feature;
use App\Enums\FeatureTier;
use App\Models\User;

test('a user with no feature rows sits at the enum defaults', function () {
    $user = User::factory()->create();

    expect($user->hasFeature(Feature::Transactions))->toBeTrue()
        ->and($user->hasFeature(Feature::Reports))->toBeTrue()
        ->and($user->hasFeature(Feature::Bills))->toBeFalse()
        ->and($user->hasFeature(Feature::Investments))->toBeFalse()
        ->and($user->hasFeature(Feature::Portfolio))->toBeFalse()
        ->and($user->hasFeature(Feature::AiAssistant))->toBeFalse()
        ->and($user->hasFeature(Feature::TelegramBot))->toBeFalse()
        ->and($user->features()->count())->toBe(0);
});

test('enabling portfolio persists investments alongside it', function () {
    $user = User::factory()->create();

    $result = app(UpdateUserFeature::class)($user, Feature::Portfolio, true);

    expect($result->wasRejected())->toBeFalse()
        ->and($result->enabledByCascade())->toBe([Feature::Investments])
        ->and($user->hasFeature(Feature::Portfolio))->toBeTrue()
        ->and($user->hasFeature(Feature::Investments))->toBeTrue()
        ->and($user->features()->pluck('feature')->all())
        ->toEqualCanonicalizing([Feature::Investments, Feature::Portfolio]);
});

test('disabling investments also switches portfolio off', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::Portfolio, true);

    $result = app(UpdateUserFeature::class)($user, Feature::Investments, false);

    expect($result->disabledByCascade())->toBe([Feature::Portfolio])
        ->and($user->hasFeature(Feature::Investments))->toBeFalse()
        ->and($user->hasFeature(Feature::Portfolio))->toBeFalse();
});

test('core features cannot be switched off through the action', function () {
    $user = User::factory()->create();

    $result = app(UpdateUserFeature::class)($user, Feature::Transactions, false);

    expect($result->rejected)->toBe(FeatureToggleResult::REJECTED_CORE)
        ->and($user->hasFeature(Feature::Transactions))->toBeTrue()
        ->and($user->features()->count())->toBe(0);
});

test('hiding a disabled module from the menu leaves it disabled', function () {
    $user = User::factory()->create();

    app(UpdateUserFeature::class)->setPromoVisibility($user, Feature::Bills, false);

    expect($user->hasFeature(Feature::Bills))->toBeFalse()
        ->and($user->featureSet()->showsPromo(Feature::Bills))->toBeFalse();
});

test('setting promo visibility never disables an already enabled module', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::Bills, true);

    app(UpdateUserFeature::class)->setPromoVisibility($user, Feature::Bills, false);

    expect($user->hasFeature(Feature::Bills))->toBeTrue();
});

test('core modules ignore stray override rows entirely', function () {
    $user = User::factory()->create();

    // A row that should never exist — written directly, bypassing the action.
    $user->features()->create(['feature' => Feature::Reports->value, 'enabled' => false]);
    $user->forgetFeatureSet();

    expect($user->hasFeature(Feature::Reports))->toBeTrue()
        ->and(User::query()->whereFeatureEnabled(Feature::Reports)->count())->toBe(1);
});

test('an enabled module never advertises a promo', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::Bills, true);

    expect($user->featureSet()->showsPromo(Feature::Bills))->toBeFalse();
});

test('every feature is entitlement free now that Miles have replaced Pro', function () {
    $user = User::factory()->create();

    foreach (Feature::cases() as $feature) {
        expect($feature->tier())->toBe(FeatureTier::Free)
            ->and($user->mayUse($feature))->toBeTrue();
    }
});

test('the shared feature map carries tier and entitlement alongside enablement', function () {
    $user = User::factory()->create();

    $map = $user->featureSet()->toArray($user->isPro());

    foreach (Feature::cases() as $feature) {
        expect($map[$feature->value]['tier'])->toBe('free')
            ->and($map[$feature->value]['may_use'])->toBeTrue();
    }
});

test('the whereFeatureEnabled scope honours sparse defaults in both directions', function () {
    $withBills = User::factory()->create();
    User::factory()->create();

    app(UpdateUserFeature::class)($withBills, Feature::Bills, true);

    // Off by default: only an explicit row counts.
    expect(User::query()->whereFeatureEnabled(Feature::Bills)->pluck('id')->all())
        ->toBe([$withBills->id]);

    // Core: everyone counts, always.
    expect(User::query()->whereFeatureEnabled(Feature::Reports)->count())->toBe(2)
        ->and(User::query()->whereFeatureEnabled(Feature::Transactions)->count())->toBe(2);

    // Enabling portfolio pulls investments in, so both now match.
    app(UpdateUserFeature::class)($withBills, Feature::Portfolio, true);

    expect(User::query()->whereFeatureEnabled(Feature::Portfolio)->pluck('id')->all())
        ->toBe([$withBills->id])
        ->and(User::query()->whereFeatureEnabled(Feature::Investments)->pluck('id')->all())
        ->toBe([$withBills->id]);
});
