<?php

use App\Actions\Miles\ActivateUserFeature;
use App\Actions\Miles\AdjustMiles;
use App\Actions\Miles\ClaimDailyMiles;
use App\Actions\Miles\EnsureWelcomeMiles;
use App\Actions\Miles\MilesOverview;
use App\Enums\Feature;
use App\Enums\MilesReason;
use App\Enums\Milestone;
use App\Enums\PilotRank;
use App\Enums\StreakProtectionType;
use App\Models\Bill;
use App\Models\Budget;
use App\Models\Category;
use App\Models\StreakProtection;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
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

it('awards a rank milestone the user passed before anything evaluated it', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->create();

    // Well past Captain. Rank is derived on every read, so nothing ever wrote
    // the milestone that marks passing one.
    foreach (range(1, PilotRank::Captain->threshold() + 5) as $offset) {
        Transaction::factory()->cost()->for($user)->for($category)->create([
            'occurred_at' => now()->subDays($offset)->toDateString(),
        ]);
    }

    expect($user->milestones()->whereIn('key', [
        Milestone::PilotRank->value,
        Milestone::CaptainRank->value,
    ])->count())->toBe(0);

    $this->actingAs($user)->get(route('miles.index'))->assertOk();

    expect($user->milestones()->where('key', Milestone::PilotRank->value)->exists())->toBeTrue()
        ->and($user->milestones()->where('key', Milestone::CaptainRank->value)->exists())->toBeTrue()
        ->and((int) $user->mileLedgerEntries()->where('reason', MilesReason::Milestone)->sum('amount'))
        ->toBeGreaterThanOrEqual(Milestone::PilotRank->miles() + Milestone::CaptainRank->miles());
});

it('does not award a rank the user has not reached', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->create();

    foreach (range(1, 5) as $offset) {
        Transaction::factory()->cost()->for($user)->for($category)->create([
            'occurred_at' => now()->subDays($offset)->toDateString(),
        ]);
    }

    $this->actingAs($user)->get(route('miles.index'))->assertOk();

    expect($user->milestones()->where('key', Milestone::PilotRank->value)->exists())->toBeFalse();
});

it('offers only the missed days a repair could actually mend', function () {
    Carbon::setTestNow('2026-09-10 09:00:00');

    try {
        $user = User::factory()->create(['timezone' => 'UTC', 'created_at' => now()->subMonth()]);
        $category = Category::factory()->cost()->create();

        // Recorded on the 9th and the 4th; the 5th to the 8th are gaps.
        foreach (['2026-09-09', '2026-09-04'] as $date) {
            Transaction::factory()->cost()->for($user)->for($category)->create(['occurred_at' => $date]);
        }

        // The 7th is already covered, so it is not for sale.
        StreakProtection::query()->create([
            'user_id' => $user->id,
            'protected_date' => '2026-09-07',
            'type' => StreakProtectionType::Repair,
            'timezone' => 'UTC',
        ]);

        $dates = collect(app(MilesOverview::class)($user)['protections']['repairableDates']);

        expect($dates->pluck('date')->all())->toBe(['2026-09-08', '2026-09-06', '2026-09-05', '2026-09-03'])
            // Newest first, and today is never offered - it is still open.
            ->and($dates->first()['daysAgo'])->toBe(2)
            ->and($dates->pluck('date'))->not->toContain('2026-09-10', '2026-09-09', '2026-09-07');
    } finally {
        Carbon::setTestNow();
    }
});
