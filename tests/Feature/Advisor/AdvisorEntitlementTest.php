<?php

use App\Actions\Features\UpdateUserFeature;
use App\Enums\Feature;
use App\Enums\FeatureTier;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

test('advisor is a pro entitlement exposed in the shared feature map', function () {
    $free = User::factory()->create();
    $pro = User::factory()->pro()->create();

    expect(Feature::Advisor->tier())->toBe(FeatureTier::Pro)
        ->and($free->mayUse(Feature::Advisor))->toBeFalse()
        ->and($pro->mayUse(Feature::Advisor))->toBeTrue()
        ->and($free->featureSet()->toArray(false)['advisor']['tier'])->toBe('pro')
        ->and($free->featureSet()->toArray(false)['advisor']['may_use'])->toBeFalse();
});

test('a free user sees advisor locked and cannot enable or directly access it', function () {
    $user = User::factory()->create();
    $result = app(UpdateUserFeature::class)($user, Feature::Advisor, true);

    expect($result->wasRejected())->toBeTrue()
        ->and($user->fresh()->hasFeature(Feature::Advisor))->toBeFalse();

    $this->actingAs($user)->get(route('advisor.index'))->assertRedirect(route('modules.edit'));
    $this->actingAs($user)->get(route('modules.edit'))->assertInertia(fn (Assert $page) => $page
        ->component('settings/Modules')
        ->where('modules', fn ($modules): bool => collect($modules)->contains(fn (array $module): bool => $module['key'] === 'advisor' && $module['tier'] === 'pro' && $module['may_use'] === false)));
});

test('a pro user can enable and access advisor', function () {
    $user = User::factory()->pro()->create();
    $result = app(UpdateUserFeature::class)($user, Feature::Advisor, true);

    expect($result->wasRejected())->toBeFalse();
    $this->actingAs($user->fresh())->get(route('advisor.index'))->assertOk();
});
