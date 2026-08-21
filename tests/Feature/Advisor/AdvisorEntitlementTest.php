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

test('a free user sees advisor locked and cannot enable it', function () {
    $user = User::factory()->create();
    $result = app(UpdateUserFeature::class)($user, Feature::Advisor, true);

    expect($result->wasRejected())->toBeTrue()
        ->and($user->fresh()->hasFeature(Feature::Advisor))->toBeFalse();

    $this->actingAs($user)->get(route('modules.edit'))->assertInertia(fn (Assert $page) => $page
        ->component('settings/Modules')
        ->where('modules', fn ($modules): bool => collect($modules)->contains(fn (array $module): bool => $module['key'] === 'advisor' && $module['tier'] === 'pro' && $module['may_use'] === false)));
});

/*
 * The paywall replaced a redirect to the modules page. A user without the Pro
 * entitlement has nothing to switch on there, so the route now answers with the
 * screen that explains the feature and sells it.
 */
test('a free user hitting the advisor route gets the paywall, not the modules page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('advisor.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Advisor/Paywall'));
});

test('a pro user who switched advisor off is still sent to the modules page', function () {
    $user = User::factory()->pro()->create();
    app(UpdateUserFeature::class)($user, Feature::Advisor, false);

    $this->actingAs($user->fresh())
        ->get(route('advisor.index'))
        ->assertRedirect(route('modules.edit'));
});

/*
 * The whole point of the tier. Paying and then being sent to a settings page to
 * find a switch you did not know existed is not a purchase working, so Advisor
 * is on by default and the entitlement is the only thing standing in the way.
 */
test('buying pro is enough to open advisor, with no visit to the modules page', function () {
    $user = User::factory()->pro()->create();

    expect($user->features()->count())->toBe(0)
        ->and($user->hasFeature(Feature::Advisor))->toBeTrue();

    $this->actingAs($user)->get(route('advisor.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Advisor/Index'));
});

test('the default says nothing about entitlement', function () {
    // Advisor is "on" for a free user in the sense that they never switched it
    // off — and still unusable, which is exactly the locked state the nav draws.
    $free = User::factory()->create();

    expect(Feature::Advisor->enabledByDefault())->toBeTrue()
        ->and($free->featureSet()->enabled(Feature::Advisor))->toBeTrue()
        ->and($free->hasFeature(Feature::Advisor))->toBeFalse();
});

test('the shared feature map reports a locked module as off, not on', function () {
    // The client reads `enabled` to decide whether a nav item is live. Reporting
    // the raw stored preference would render Advisor as switched on for someone
    // who cannot open it, and would take away the "hide from menu" control that
    // only appears for an off module.
    $free = User::factory()->create()->featureSet()->toArray(false)['advisor'];
    $pro = User::factory()->pro()->create()->featureSet()->toArray(true)['advisor'];

    expect($free['enabled'])->toBeFalse()
        ->and($free['may_use'])->toBeFalse()
        ->and($free['show_promo'])->toBeTrue()
        ->and($pro['enabled'])->toBeTrue()
        ->and($pro['may_use'])->toBeTrue()
        ->and($pro['show_promo'])->toBeFalse();
});

test('a lapsed subscriber is told their profile survived', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('advisor.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Advisor/Paywall')
            ->where('hasProfile', false));
});

test('a pro user can enable and access advisor', function () {
    $user = User::factory()->pro()->create();
    $result = app(UpdateUserFeature::class)($user, Feature::Advisor, true);

    expect($result->wasRejected())->toBeFalse();
    $this->actingAs($user->fresh())->get(route('advisor.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Advisor/Index'));
});
