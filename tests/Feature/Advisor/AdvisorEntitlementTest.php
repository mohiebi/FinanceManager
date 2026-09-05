<?php

use App\Actions\Features\UpdateUserFeature;
use App\Enums\Feature;
use App\Enums\FeatureTier;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

test('advisor access is free while individual services carry Miles prices', function () {
    $user = User::factory()->create();

    expect(Feature::Advisor->tier())->toBe(FeatureTier::Free)
        ->and($user->mayUse(Feature::Advisor))->toBeTrue()
        ->and($user->featureSet()->toArray(false)['advisor']['tier'])->toBe('free')
        ->and($user->featureSet()->toArray(false)['advisor']['may_use'])->toBeTrue();
});

test('any eligible user can enable Advisor without buying Pro or an activation', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::Advisor, false);

    $result = app(UpdateUserFeature::class)($user->fresh(), Feature::Advisor, true);

    expect($result->wasRejected())->toBeFalse()
        ->and($user->fresh()->hasFeature(Feature::Advisor))->toBeTrue();

    $this->actingAs($user->fresh())->get(route('modules.edit'))->assertInertia(fn (Assert $page) => $page
        ->component('settings/Modules')
        ->where('modules', fn ($modules): bool => collect($modules)->contains(fn (array $module): bool => $module['key'] === 'advisor'
            && $module['tier'] === 'free'
            && $module['may_use'] === true
            && $module['activation_cost'] === 0)));
});

test('Advisor opens directly without a Pro paywall', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('advisor.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Advisor/Index'));
});

test('a user who switched Advisor off is sent to the modules page', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::Advisor, false);

    $this->actingAs($user->fresh())
        ->get(route('advisor.index'))
        ->assertRedirect(route('modules.edit'));
});

test('Advisor is enabled by default and usable without a stored preference', function () {
    $user = User::factory()->create();

    expect(Feature::Advisor->enabledByDefault())->toBeTrue()
        ->and($user->features()->count())->toBe(0)
        ->and($user->featureSet()->enabled(Feature::Advisor))->toBeTrue()
        ->and($user->hasFeature(Feature::Advisor))->toBeTrue();
});

test('the shared feature map no longer advertises an Advisor promotion', function () {
    $advisor = User::factory()->create()->featureSet()->toArray(false)['advisor'];

    expect($advisor['enabled'])->toBeTrue()
        ->and($advisor['may_use'])->toBeTrue()
        ->and($advisor['show_promo'])->toBeFalse();
});
