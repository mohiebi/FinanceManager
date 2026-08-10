<?php

use App\Support\CalendarDates;
use Carbon\CarbonImmutable;

/**
 * The Jalali branch of monthStart() round-trips through a bare "Y-m-d" string
 * (new Jalalian(...)->toCarbon()->format('Y-m-d')), which drops whatever
 * timezone the input date carried. Left unanchored, the result implicitly
 * takes the app's UTC default instead of the caller's timezone — so a
 * non-UTC $today (e.g. Asia/Tehran) and a UTC $monthStart end up measuring
 * across the offset. diffInDays() then returns a fraction, and callers that
 * cast it to int (LogbookCompleteness::for()) silently lose a day for anyone
 * ahead of UTC — which is most Jalali-calendar users.
 */
test('month start keeps the timezone of the date it was computed from', function () {
    $today = CarbonImmutable::parse('2026-08-19 00:00:00', 'Asia/Tehran');

    $monthStart = CalendarDates::monthStart($today, 'jalali');

    expect($monthStart->getTimezone()->getName())->toBe('Asia/Tehran');
});

test('days elapsed matches the jalali day of month for a user ahead of UTC', function () {
    $today = CarbonImmutable::parse('2026-08-19 00:00:00', 'Asia/Tehran');

    $monthStart = CalendarDates::monthStart($today, 'jalali');
    $descriptor = CalendarDates::monthDescriptor($today, 'jalali');

    // Independent of the buggy path: day_of_month is read straight off the
    // Jalali digits, with no Carbon round-trip to lose a timezone. "Today is
    // the Nth day" and "N days have elapsed since day 1" must agree.
    $daysElapsed = (int) $monthStart->diffInDays($today) + 1;

    expect($daysElapsed)->toBe($descriptor['day_of_month']);
});

test('days elapsed is exactly 1 on the first of the jalali month', function () {
    $anchor = CarbonImmutable::parse('2026-08-19 00:00:00', 'Asia/Tehran');
    // Guaranteed to be day 1 of its own Jalali month, still in Asia/Tehran.
    $today = CalendarDates::monthStart($anchor, 'jalali');

    $monthStart = CalendarDates::monthStart($today, 'jalali');
    $descriptor = CalendarDates::monthDescriptor($today, 'jalali');
    $daysElapsed = (int) $monthStart->diffInDays($today) + 1;

    expect($daysElapsed)->toBe(1)
        ->and($descriptor['day_of_month'])->toBe(1);
});

test('a utc user is unaffected either way', function () {
    $today = CarbonImmutable::parse('2026-08-19 00:00:00', 'UTC');

    $monthStart = CalendarDates::monthStart($today, 'jalali');
    $descriptor = CalendarDates::monthDescriptor($today, 'jalali');
    $daysElapsed = (int) $monthStart->diffInDays($today) + 1;

    expect($daysElapsed)->toBe($descriptor['day_of_month']);
});
