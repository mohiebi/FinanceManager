<?php

use App\Support\BillDueDateCalculator;
use App\Support\BillRecurrenceSchedule;
use Illuminate\Support\Carbon;
use Morilog\Jalali\Jalalian;

test('gregorian: returns this month when the due day has not passed yet', function () {
    $calculator = new BillDueDateCalculator;

    $next = $calculator->nextOccurrence(15, 'gregorian', Carbon::create(2026, 7, 10));

    expect($next->toDateString())->toBe('2026-07-15');
});

test('gregorian: rolls over to next month when the due day already passed', function () {
    $calculator = new BillDueDateCalculator;

    $next = $calculator->nextOccurrence(5, 'gregorian', Carbon::create(2026, 7, 10));

    expect($next->toDateString())->toBe('2026-08-05');
});

test('gregorian: clamps to the last day of a shorter month', function () {
    $calculator = new BillDueDateCalculator;

    // February 2026 is not a leap year — 28 days.
    $next = $calculator->nextOccurrence(31, 'gregorian', Carbon::create(2026, 2, 1));

    expect($next->toDateString())->toBe('2026-02-28');
});

test('gregorian: due today counts as the next occurrence', function () {
    $calculator = new BillDueDateCalculator;

    $next = $calculator->nextOccurrence(10, 'gregorian', Carbon::create(2026, 7, 10));

    expect($next->toDateString())->toBe('2026-07-10');
});

test('jalali: clamps to the last day of a shorter jalali month', function () {
    $calculator = new BillDueDateCalculator;
    $after = Carbon::create(2026, 7, 1);
    $jAfter = Jalalian::fromCarbon($after);
    $daysInMonth = $jAfter->getMonthDays();

    $next = $calculator->nextOccurrence(31, 'jalali', $after);
    $jNext = Jalalian::fromCarbon($next);

    expect($jNext->getDay())->toBe(min(31, $daysInMonth));
});

test('jalali: rolls over to next jalali month when the due day already passed', function () {
    $calculator = new BillDueDateCalculator;
    $after = Carbon::create(2026, 7, 1);
    $jAfter = Jalalian::fromCarbon($after);

    $next = $calculator->nextOccurrence(1, 'jalali', $after->copy()->addDays(5));
    $jNext = Jalalian::fromCarbon($next);

    expect($jNext->getMonth())->toBe($jAfter->getMonth() === 12 ? 1 : $jAfter->getMonth() + 1)
        ->and($jNext->getDay())->toBe(1);
});

test('it counts every gregorian payment through an inclusive end date', function () {
    $schedule = new BillRecurrenceSchedule(new BillDueDateCalculator);

    $count = $schedule->countThrough(
        15,
        'gregorian',
        Carbon::create(2026, 7, 10),
        Carbon::create(2026, 10, 31),
    );

    expect($count)->toBe(4);
});

test('it counts jalali payments using jalali month boundaries', function () {
    $schedule = new BillRecurrenceSchedule(new BillDueDateCalculator);
    $from = Carbon::create(2026, 7, 1);
    $jalaliFrom = Jalalian::fromCarbon($from);
    $jalaliEnd = $jalaliFrom->addMonths(2);

    $count = $schedule->countThrough(
        $jalaliFrom->getDay(),
        'jalali',
        $from,
        Carbon::instance($jalaliEnd->toCarbon()),
    );

    expect($count)->toBe(3);
});
