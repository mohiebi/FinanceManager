import assert from 'node:assert/strict';
import { test } from 'node:test';

import { dayOfMonthInCalendar } from '../../resources/js/lib/date.ts';

test('gregorian viewers bucket by the gregorian day of month', () => {
    assert.equal(dayOfMonthInCalendar('2026-08-23', 'gregorian'), 23);
    assert.equal(dayOfMonthInCalendar('2026-08-23', undefined), 23);
});

test('jalali viewers bucket by the jalali day of month', () => {
    // Shahrivar 1405 runs 2026-08-23 .. 2026-09-22, so the dashboard chart has
    // to plot those as days 1 and 31 rather than 23 and 22.
    assert.equal(dayOfMonthInCalendar('2026-08-23', 'jalali'), 1);
    assert.equal(dayOfMonthInCalendar('2026-08-29', 'jalali'), 7);
    assert.equal(dayOfMonthInCalendar('2026-09-22', 'jalali'), 31);
});

test('a timestamp is read from its date part alone', () => {
    assert.equal(dayOfMonthInCalendar('2026-08-23T00:00:00Z', 'jalali'), 1);
    assert.equal(dayOfMonthInCalendar('2026-08-23 18:45:00', 'gregorian'), 23);
});

test('an unparseable date falls outside every bucket', () => {
    assert.equal(dayOfMonthInCalendar('', 'jalali'), 0);
    assert.equal(dayOfMonthInCalendar('not-a-date', 'gregorian'), 0);
});
