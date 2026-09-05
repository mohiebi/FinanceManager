<?php

use App\Actions\Miles\AdjustMiles;
use App\Actions\Miles\ClaimDailyMiles;
use App\Actions\Miles\EnsureWelcomeMiles;
use App\Enums\MilesReason;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

it('shares eager Miles state on authenticated pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('miles.balance', 150)
            ->where('miles.claimable', true)
            ->where('miles.nextClaimReward', 3)
            ->where('miles.freezesHeld', 0)
            ->where('miles.freezesMaximum', 2)
            ->where('miles.hubUrl', route('miles.index')));
});

it('renders the Miles hub with its cycle milestones referrals cosmetics and history', function () {
    $user = User::factory()->create();
    app(AdjustMiles::class)($user, 20, MilesReason::AdminAdjustment, 'test-history');
    app(ClaimDailyMiles::class)($user);

    $this->actingAs($user)
        ->get(route('miles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Miles/Index')
            ->has('overview.claimCycle', 7)
            ->has('overview.milestones', 14)
            ->has('overview.cosmetics', 4)
            ->has('overview.referralCode')
            ->where('overview.todayClaimed', true)
            ->has('history.data'));
});

it('can disable every Miles surface without changing balances', function () {
    config()->set('miles.ui_enabled', false);
    $user = User::factory()->create();
    app(EnsureWelcomeMiles::class)($user);
    app(AdjustMiles::class)($user, 50, MilesReason::AdminAdjustment, 'seed');
    $balance = $user->mileWallet()->first()->balance;

    $this->actingAs($user)->get(route('miles.index'))->assertNotFound();

    expect($user->mileWallet()->first()->balance)->toBe($balance);
});
