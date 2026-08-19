import assert from 'node:assert/strict';
import { test } from 'node:test';

import jalaali from 'jalaali-js';

import {
    countMonthlyPaymentsThrough,
    nextMonthlyDueDate,
} from '../../resources/js/lib/bill-recurrence.ts';

const { toJalaali } = jalaali;

test('gregorian end-date preview counts every payment inclusively', () => {
    assert.equal(
        countMonthlyPaymentsThrough(
            15,
            'gregorian',
            '2026-07-10',
            '2026-10-31',
        ),
        4,
    );
});

test('gregorian preview clamps day 31 in a short month', () => {
    assert.equal(
        nextMonthlyDueDate(31, 'gregorian', '2026-02-01'),
        '2026-02-28',
    );
});

test('jalali preview uses jalali month boundaries', () => {
    const dueDate = nextMonthlyDueDate(31, 'jalali', '2026-07-01');
    const [year, month, day] = dueDate.split('-').map(Number);
    const jalali = toJalaali(year, month, day);

    assert.ok(jalali.jd === 30 || jalali.jd === 31);
});
