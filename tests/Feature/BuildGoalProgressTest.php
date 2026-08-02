<?php

use App\Actions\Goals\BuildGoalProgress;
use App\Actions\Investments\BuildPortfolioBreakdown;
use App\Enums\AssetType;
use App\Enums\Feature;
use App\Models\InvestmentAsset;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Support\GoalPace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

function goldAsset(): InvestmentAsset
{
    return InvestmentAsset::query()->where('slug', AssetType::Gold->value)->firstOrFail();
}

function buyGold(User $user, float $quantity, string $on): void
{
    $user->investments()->create([
        'investment_asset_id' => goldAsset()->id,
        'asset_type' => AssetType::Gold->value,
        'kind' => 'buy',
        'quantity' => $quantity,
        'occurred_at' => $on,
    ]);
}

function goalProgressFor(User $user, string $today = '2026-07-30'): array
{
    return app(BuildGoalProgress::class)->handle(
        $user,
        app(BuildPortfolioBreakdown::class)->entriesFor($user),
        CarbonImmutable::parse($today),
    );
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-07-30 09:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('progress is a ratio of quantities, needing no price at all', function () {
    $user = User::factory()->withModules(Feature::Portfolio)->create();

    SavingsGoal::factory()->for($user)->create([
        'investment_asset_id' => goldAsset()->id,
        'target_quantity' => 3,
        'started_on' => '2026-04-21',
        'target_date' => '2026-10-18',
    ]);

    buyGold($user, 1.84, '2026-06-01');

    $goal = goalProgressFor($user)[0];

    expect($goal['current_quantity'])->toBe(1.84)
        ->and($goal['target_quantity'])->toBe(3.0)
        ->and($goal['progress'])->toBe(0.613333)
        ->and($goal['asset']['unit'])->toBe(goldAsset()->unit);
});

test('holdings bought before the goal started are a baseline, not progress', function () {
    $user = User::factory()->withModules(Feature::Portfolio)->create();

    SavingsGoal::factory()->for($user)->create([
        'investment_asset_id' => goldAsset()->id,
        'target_quantity' => 3,
        'started_on' => '2026-06-01',
        'target_date' => '2026-10-18',
    ]);

    buyGold($user, 1.5, '2026-01-10');
    buyGold($user, 0.34, '2026-07-01');

    $goal = goalProgressFor($user)[0];

    // Without the baseline the user would be told they are ahead on day one and
    // stay ahead forever, because 1.5g they already had counts as progress.
    expect($goal['current_quantity'])->toBe(1.84)
        ->and($goal['expected_quantity'])->toBeGreaterThan(1.5)
        ->and($goal['on_track'])->toBeFalse();
});

test('a sale reduces progress, because holdings are netted', function () {
    $user = User::factory()->withModules(Feature::Portfolio)->create();

    SavingsGoal::factory()->for($user)->create([
        'investment_asset_id' => goldAsset()->id,
        'target_quantity' => 3,
        'started_on' => '2026-06-01',
        'target_date' => '2026-10-18',
    ]);

    buyGold($user, 2, '2026-06-10');

    // Disposals are stored with a negative quantity, so holdings stay a plain sum.
    $user->investments()->create([
        'investment_asset_id' => goldAsset()->id,
        'asset_type' => AssetType::Gold->value,
        'kind' => 'sell',
        'quantity' => -0.5,
        'occurred_at' => '2026-07-01',
    ]);

    expect(goalProgressFor($user)[0]['current_quantity'])->toBe(1.5);
});

test('a goal on an asset the user holds none of reads as zero, not unavailable', function () {
    $user = User::factory()->withModules(Feature::Portfolio)->create();

    SavingsGoal::factory()->for($user)->create([
        'investment_asset_id' => goldAsset()->id,
        'target_quantity' => 3,
        'started_on' => '2026-06-01',
        'target_date' => '2026-10-18',
    ]);

    // The highest-traffic moment for this feature is the goal a user sets before
    // their first purchase. It must render, not error.
    $goal = goalProgressFor($user)[0];

    expect($goal['current_quantity'])->toBe(0.0)
        ->and($goal['progress'])->toBe(0.0)
        ->and($goal['on_track'])->toBeFalse();
});

test('the target date is rendered in the user calendar', function () {
    $jalali = User::factory()->withModules(Feature::Portfolio)->create(['calendar' => 'jalali']);
    $gregorian = User::factory()->withModules(Feature::Portfolio)->create();

    foreach ([$jalali, $gregorian] as $user) {
        SavingsGoal::factory()->for($user)->create([
            'investment_asset_id' => goldAsset()->id,
            'target_quantity' => 3,
            'started_on' => '2026-06-01',
            'target_date' => '2027-03-21',
        ]);
    }

    // 2027-03-21 is 1 Farvardin 1406 — the Persian new year, which is exactly
    // the date a Persian user would have picked.
    expect(goalProgressFor($jalali)[0]['target_date_display'])
        ->not->toBe(goalProgressFor($gregorian)[0]['target_date_display'])
        ->and(goalProgressFor($jalali)[0]['target_date'])->toBe('2027-03-21');
});

test('inactive goals are left out', function () {
    $user = User::factory()->withModules(Feature::Portfolio)->create();

    SavingsGoal::factory()->for($user)->create([
        'investment_asset_id' => goldAsset()->id,
        'is_active' => false,
    ]);

    expect(goalProgressFor($user))->toBeEmpty();
});

test('one user cannot see another goal', function () {
    $mine = User::factory()->withModules(Feature::Portfolio)->create();
    $theirs = User::factory()->withModules(Feature::Portfolio)->create();

    SavingsGoal::factory()->for($theirs)->create([
        'investment_asset_id' => goldAsset()->id,
    ]);

    expect(goalProgressFor($mine))->toBeEmpty();
});

test('a met target reads as reached rather than merely on track', function () {
    // 103% is not "on track" — it is finished. The two facts are separate so the
    // card can mark the moment instead of burying it under a pace reading.
    $pace = GoalPace::compute(
        baseline: 0,
        current: 7.2,
        target: 7,
        elapsed: 100,
        total: 180,
    );

    expect($pace['reached'])->toBeTrue()
        ->and($pace['progress'])->toBeGreaterThan(1.0);
});

test('a goal short of its target is not reached even when ahead of pace', function () {
    $pace = GoalPace::compute(
        baseline: 0,
        current: 2.9,
        target: 3,
        elapsed: 10,
        total: 180,
    );

    expect($pace['on_track'])->toBeTrue()
        ->and($pace['reached'])->toBeFalse();
});

test('a zero target is never reached', function () {
    // "0 of 0, done!" is not a fact about the user, and the card would have
    // nothing honest to celebrate.
    $pace = GoalPace::compute(
        baseline: 0,
        current: 0,
        target: 0,
        elapsed: 10,
        total: 180,
    );

    expect($pace['reached'])->toBeFalse();
});
