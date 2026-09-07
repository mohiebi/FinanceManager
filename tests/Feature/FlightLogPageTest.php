<?php

use App\Actions\Features\UpdateUserFeature;
use App\Enums\Feature;
use App\Enums\Milestone;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

/*
 * The flight log lived on the dashboard until that page was rebuilt to the v3
 * mock. It was dropped there but never retired: the streak and the completeness
 * were still computed on every dashboard load and thrown away, and the module
 * stayed switchable while rendering nothing.
 */
test('the flight log is included in the Activity and Miles page', function () {
    $user = User::factory()->withModules()->create();

    $this->actingAs($user)->get(route('miles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Miles/Index')
            ->has('activity.streak')
            ->has('activity.logbook')
            ->has('activity.ranks', 3)
            ->has('activity.moments', count(Milestone::cases())));
});

test('the module appears in the navigation now that it has somewhere to point', function () {
    // appearsInNav drove promoByDefault too, so a module with no page could not
    // sensibly advertise itself.
    expect(Feature::Gamification->appearsInNav())->toBeTrue();
});

test('the ladder shows every rank, not just the one reached', function () {
    // Knowing Captain is 180 days is what makes Pilot mean anything.
    $this->actingAs(User::factory()->withModules()->create())
        ->get(route('miles.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activity.ranks', function ($ranks): bool {
                $keys = collect($ranks)->pluck('key')->all();
                $current = collect($ranks)->firstWhere('state', 'current');

                return $keys === ['cadet', 'pilot', 'captain']
                    // A fresh account has recorded nothing, so it sits at the
                    // bottom rung with the rest still ahead of it.
                    && $current['key'] === 'cadet'
                    && collect($ranks)->firstWhere('key', 'captain')['days_away'] === 180;
            })
            ->etc());
});

test('an unearned complete month says how close the last one came', function () {
    $user = User::factory()->withModules()->create();

    $this->actingAs($user)->get(route('miles.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activity.moments', function ($moments): bool {
                $month = collect($moments)->firstWhere('key', Milestone::FirstFullMonth->value);

                // Nothing recorded at all is not a near miss, so no hint —
                // "missed by 31 days" would be noise, not encouragement.
                return $month['achieved_at'] === null && $month['missed'] === null;
            })
            ->etc());
});

test('the page is gated by the module like every other', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::Gamification, false);

    $this->actingAs($user->fresh())
        ->get(route('flight-log'))
        ->assertRedirect(route('modules.edit'));
});
