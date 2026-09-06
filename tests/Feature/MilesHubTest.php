<?php

use App\Actions\Miles\ActivateUserFeature;
use App\Actions\Miles\AdjustMiles;
use App\Actions\Miles\ClaimDailyMiles;
use App\Actions\Miles\EnsureWelcomeMiles;
use App\Actions\Miles\MilesOverview;
use App\Enums\Feature;
use App\Enums\MilesReason;
use App\Enums\StreakProtectionType;
use App\Models\StreakProtection;
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

it('tells the unlock list which modules are already paid for', function () {
    $user = User::factory()->create();
    app(AdjustMiles::class)($user, 50, MilesReason::AdminAdjustment, 'seed');
    app(ActivateUserFeature::class)($user, Feature::Bills, true);

    $overview = app(MilesOverview::class)($user->refresh());
    $modules = collect($overview['modules'])->keyBy('key');

    expect($modules)->toHaveCount(count(config('miles.paid_modules')))
        ->and($modules[Feature::Bills->value]['unlocked'])->toBeTrue()
        ->and($modules[Feature::Budgets->value]['unlocked'])->toBeFalse()
        ->and($modules[Feature::Budgets->value]['price'])->toBe(config('miles.unlock_price'))
        // Never the free ones: they would read as something still to buy.
        ->and($modules->has(Feature::Vault->value))->toBeFalse()
        ->and($modules->has(Feature::Advisor->value))->toBeFalse();
});

it('counts repairs left against the user own calendar month', function () {
    $user = User::factory()->create(['timezone' => 'UTC']);
    $cap = (int) config('miles.streak_repairs_per_month');

    expect(app(MilesOverview::class)($user)['protections']['repairsRemaining'])->toBe($cap);

    StreakProtection::query()->create([
        'user_id' => $user->id,
        'protected_date' => now()->subDay()->toDateString(),
        'type' => StreakProtectionType::Repair,
        'timezone' => 'UTC',
    ]);

    expect(app(MilesOverview::class)($user->refresh())['protections']['repairsRemaining'])
        ->toBe($cap - 1);
});
