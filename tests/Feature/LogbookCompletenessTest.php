<?php

use App\Enums\Feature;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Support\LogbookCompleteness;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

function logbookFor(User $user, string $today = '2026-07-08'): array
{
    return app(LogbookCompleteness::class)->for($user, CarbonImmutable::parse($today));
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('completeness is measured against days elapsed, not days in the month', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->forUser($user)->create();

    // 7 of the 8 elapsed days in July have a record.
    foreach (['01', '02', '03', '04', '05', '06', '07'] as $day) {
        Transaction::factory()->cost()->for($user)->for($category)->create([
            'occurred_at' => "2026-07-{$day}",
        ]);
    }

    $logbook = logbookFor($user);

    // 7/8, not 7/31 — measuring against the whole month would read as failure
    // on the 8th for someone with a perfect record.
    expect($logbook['days_covered'])->toBe(7)
        ->and($logbook['days_elapsed'])->toBe(8)
        ->and($logbook['percent'])->toBe(88);
});

test('a no-spend day counts toward coverage', function () {
    $user = User::factory()->create();
    $user->noSpendDays()->create(['date' => '2026-07-01']);

    expect(logbookFor($user, '2026-07-01')['percent'])->toBe(100);
});

test('it counts this month uncategorised transactions only', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->forUser($user)->create();

    Transaction::factory()->cost()->for($user)->create([
        'occurred_at' => '2026-07-03',
        'category_id' => null,
    ]);
    Transaction::factory()->cost()->for($user)->create([
        'occurred_at' => '2026-06-20',
        'category_id' => null,
    ]);
    Transaction::factory()->cost()->for($user)->for($category)->create([
        'occurred_at' => '2026-07-04',
    ]);

    expect(logbookFor($user)['uncategorised'])->toBe(1);
});

test('bills are null rather than zero when the module is off', function () {
    $off = User::factory()->create();
    $on = User::factory()->withModules(Feature::Bills)->create();

    // Null lets the UI drop the row; 0 of 0 would read as a real, failing metric.
    expect(logbookFor($off)['bills_due'])->toBeNull()
        ->and(logbookFor($on)['bills_due'])->toBe(0);
});

test('it counts bills reconciled this month', function () {
    $user = User::factory()->withModules(Feature::Bills)->create();

    $bill = $user->bills()->create([
        'title' => 'Rent',
        'amount' => 100,
        'currency' => 'toman',
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 3,
    ]);

    $bill->occurrences()->create(['due_date' => '2026-07-03', 'paid_at' => now()]);
    $bill->occurrences()->create(['due_date' => '2026-07-05']);

    $logbook = logbookFor($user);

    expect($logbook['bills_paid'])->toBe(1)
        ->and($logbook['bills_due'])->toBe(2);
});

test('the month follows the user calendar, not the gregorian one', function () {
    $jalali = User::factory()->create(['calendar' => 'jalali']);
    $gregorian = User::factory()->create();

    // 2026-07-08 is 17 Tir 1405 — a Jalali month that began on 2026-06-22, so the
    // two users are measuring completely different windows.
    $jalaliBook = logbookFor($jalali);
    $gregorianBook = logbookFor($gregorian);

    expect($gregorianBook['days_elapsed'])->toBe(8)
        ->and($jalaliBook['days_elapsed'])->toBe(17)
        ->and($jalaliBook['month'])->not->toBe($gregorianBook['month']);
});

test('a brand new month with nothing logged reads as zero, not as an error', function () {
    $user = User::factory()->create();

    $logbook = logbookFor($user, '2026-07-01');

    expect($logbook['percent'])->toBe(0)
        ->and($logbook['days_covered'])->toBe(0)
        ->and($logbook['days_elapsed'])->toBe(1);
});
