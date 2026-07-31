<?php

use App\Enums\StreakDayState;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Support\StreakCalculator;
use App\Support\StreakSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Records a transaction on each given date, so tests read as a list of the days
 * a user actually logged something.
 */
function logDays(User $user, string ...$dates): void
{
    $category = Category::factory()->cost()->forUser($user)->create();

    foreach ($dates as $date) {
        Transaction::factory()->cost()->for($user)->for($category)->create([
            'occurred_at' => $date,
        ]);
    }
}

function streakFor(User $user, string $today = '2026-07-30'): StreakSummary
{
    return app(StreakCalculator::class)->for($user, CarbonImmutable::parse($today));
}

beforeEach(function () {
    // Accounts must predate the dates under test, or the walk clamps at signup.
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('a user who has logged nothing has no run', function () {
    $user = User::factory()->create();

    expect(streakFor($user)->currentRun)->toBe(0);
});

test('consecutive logged days count as a run', function () {
    $user = User::factory()->create();
    logDays($user, '2026-07-28', '2026-07-29', '2026-07-30');

    $streak = streakFor($user);

    expect($streak->currentRun)->toBe(3)
        ->and($streak->loggedToday)->toBeTrue();
});

test('several transactions on one day still count as one day', function () {
    $user = User::factory()->create();
    logDays($user, '2026-07-29', '2026-07-29', '2026-07-30', '2026-07-30');

    expect(streakFor($user)->currentRun)->toBe(2);
});

test('an unlogged today does not break a run that is still going', function () {
    $user = User::factory()->create();
    logDays($user, '2026-07-28', '2026-07-29');

    // Today is open until local midnight, so the run stands at yesterday's count.
    $streak = streakFor($user);

    expect($streak->currentRun)->toBe(2)
        ->and($streak->loggedToday)->toBeFalse()
        ->and($streak->days[13]['state'])->toBe(StreakDayState::Open);
});

test('a no-spend day sustains a run exactly like a transaction', function () {
    $user = User::factory()->create();
    logDays($user, '2026-07-28', '2026-07-30');
    $user->noSpendDays()->create(['date' => '2026-07-29']);

    $streak = streakFor($user);

    expect($streak->currentRun)->toBe(3)
        ->and($streak->days[12]['state'])->toBe(StreakDayState::NoSpend);
});

test('a single missed day is forgiven by the week grace', function () {
    $user = User::factory()->create();
    // 2026-07-29 missing, everything either side logged.
    logDays($user, '2026-07-27', '2026-07-28', '2026-07-30');

    $streak = streakFor($user);

    expect($streak->currentRun)->toBe(4)
        ->and($streak->graceRemainingThisWeek)->toBe(0)
        ->and($streak->days[12]['state'])->toBe(StreakDayState::Grace);
});

test('a second missed day in the same week ends the run', function () {
    $user = User::factory()->create();
    // 2026-07-28 and 2026-07-29 both missing, and both in ISO week 31 with today.
    logDays($user, '2026-07-26', '2026-07-27', '2026-07-30');

    // Only today counts: the 29th is provisionally forgiven, the 28th exhausts
    // the week, and a forgiveness that leads nowhere is discarded rather than
    // left inflating the run by one.
    expect(streakFor($user)->currentRun)->toBe(1)
        ->and(streakFor($user->fresh())->days[12]['state'])->toBe(StreakDayState::Missed);
});

test('forgiving the same gap twice does not change the answer', function () {
    $user = User::factory()->create();
    logDays($user, '2026-07-27', '2026-07-28', '2026-07-30');

    $first = streakFor($user)->currentRun;
    $second = streakFor($user->fresh())->currentRun;

    expect($second)->toBe($first)
        ->and($user->fresh()->streak->graceDates())->toBe(['2026-07-29']);
});

test('a backdated import extends the run that was already there', function () {
    $user = User::factory()->create();
    logDays($user, '2026-07-29', '2026-07-30');

    expect(streakFor($user)->currentRun)->toBe(2);

    // The CSV importer writes historical dates one row at a time; a stored
    // counter would never learn about them.
    logDays($user, '2026-07-27', '2026-07-28');

    expect(streakFor($user->fresh())->currentRun)->toBe(4);
});

test('a bulk delete shortens the run, because nothing is cached', function () {
    $user = User::factory()->create();
    logDays($user, '2026-07-26', '2026-07-27', '2026-07-28', '2026-07-29', '2026-07-30');

    expect(streakFor($user)->currentRun)->toBe(5);

    // Mirrors TransactionController::destroyBulk, which deletes through the
    // query builder and fires no model events.
    $user->transactions()->where('occurred_at', '2026-07-28')->delete();
    $user->transactions()->where('occurred_at', '2026-07-29')->delete();

    // Two gaps in one ISO week leave only today standing — a stored counter
    // would still be claiming 5.
    expect(streakFor($user->fresh())->currentRun)->toBe(1);
});

test('the best run is kept as a high-water mark after the run breaks', function () {
    $user = User::factory()->create();
    logDays($user, '2026-07-20', '2026-07-21', '2026-07-22', '2026-07-23');

    expect(streakFor($user, '2026-07-23')->bestRun)->toBe(4);

    // A week later, with nothing logged in between, the current run is gone but
    // the record stands.
    $streak = streakFor($user->fresh(), '2026-07-30');

    expect($streak->currentRun)->toBe(0)
        ->and($streak->bestRun)->toBe(4);
});

test('the run cannot reach back past the account creation date', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-29 00:00:00'));
    $user = User::factory()->create();

    logDays($user, '2026-07-29', '2026-07-30');

    // The day before signup is neither logged nor forgivable.
    $streak = streakFor($user);

    expect($streak->currentRun)->toBe(2)
        ->and($streak->graceRemainingThisWeek)->toBe(1);
});

test('the chain covers the last fourteen days, oldest first', function () {
    $user = User::factory()->create();
    logDays($user, '2026-07-30');

    $days = streakFor($user)->days;

    expect($days)->toHaveCount(14)
        ->and($days[0]['date'])->toBe('2026-07-17')
        ->and($days[13]['date'])->toBe('2026-07-30')
        ->and($days[13]['state'])->toBe(StreakDayState::Logged);
});

test('day labels follow the user own calendar', function () {
    $jalali = User::factory()->create(['calendar' => 'jalali']);
    $gregorian = User::factory()->create(['calendar' => 'gregorian']);

    logDays($jalali, '2026-07-30');
    logDays($gregorian, '2026-07-30');

    // 2026-07-30 falls on a different day number in the two calendars.
    expect(streakFor($jalali)->days[13]['label'])->not->toBe('30')
        ->and(streakFor($gregorian)->days[13]['label'])->toBe('30');
});

test('todayWouldSetRecord only promises a record there is one to win', function () {
    $user = User::factory()->create();
    logDays($user, '2026-07-28', '2026-07-29');

    // Run of 2 is already the best; logging today would make it 3.
    expect(streakFor($user)->todayWouldSetRecord())->toBeTrue();

    logDays($user, '2026-07-30');

    expect(streakFor($user->fresh())->todayWouldSetRecord())->toBeFalse();
});
