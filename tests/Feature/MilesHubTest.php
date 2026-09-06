<?php

use App\Actions\Miles\AdjustMiles;
use App\Actions\Miles\ClaimDailyMiles;
use App\Actions\Miles\EnsureWelcomeMiles;
use App\Enums\Feature;
use App\Enums\MilesReason;
use App\Enums\Milestone;
use App\Models\Bill;
use App\Models\Budget;
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

it('awards first-record milestones for data that predates Miles', function () {
    $user = User::factory()->withModules(Feature::Bills, Feature::Budgets, Feature::Investments, Feature::Goals)->create();

    // withoutEvents is the point: these rows stand for everything written
    // before the Miles observer existed, so no create hook ever fired for them.
    Bill::withoutEvents(fn () => $user->bills()->create([
        'title' => 'Rent',
        'amount' => 100,
        'currency' => 'toman',
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 15,
    ]));
    Budget::withoutEvents(fn () => Budget::factory()->for($user)->create());

    expect($user->milestones()->count())->toBe(0);

    $this->actingAs($user)->get(route('miles.index'))->assertOk();

    $earned = $user->milestones()->pluck('key')->map->value;

    expect($earned)->toContain(Milestone::FirstBill->value, Milestone::FirstBudget->value)
        // Nothing was invented: no investment or goal exists, so neither is earned.
        ->not->toContain(Milestone::FirstInvestment->value)
        // Scoped to milestone entries: the welcome grant posts under its own
        // reason and would otherwise swamp the two being tested.
        ->and((int) $user->mileLedgerEntries()->where('reason', MilesReason::Milestone)->sum('amount'))
        ->toBe(Milestone::FirstBill->miles() + Milestone::FirstBudget->miles());
});

it('does not re-award a first-record milestone the user already holds', function () {
    $user = User::factory()->withModules(Feature::Bills)->create();
    Bill::withoutEvents(fn () => $user->bills()->create([
        'title' => 'Rent',
        'amount' => 100,
        'currency' => 'toman',
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 15,
    ]));

    $this->actingAs($user)->get(route('miles.index'))->assertOk();
    $balance = $user->mileWallet()->value('balance');
    $this->actingAs($user)->get(route('miles.index'))->assertOk();

    expect($user->milestones()->where('key', Milestone::FirstBill->value)->count())->toBe(1)
        ->and($user->mileWallet()->value('balance'))->toBe($balance);
});
