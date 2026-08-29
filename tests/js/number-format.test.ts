import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
    formatCompactNumber,
    formatFullNumber,
    numericValue,
} from '../../resources/js/lib/number.ts';

test('summary figures compact without losing the full display value', () => {
    assert.equal(formatCompactNumber('227,963,512.30'), '228M');
    assert.equal(formatFullNumber('227,963,512.30'), '227,963,512.30');
});

test('formatted and unicode-negative values normalize consistently', () => {
    assert.equal(numericValue('−1,654,382.70'), -1654382.7);
    assert.equal(formatCompactNumber('−1,654,382.70'), '-1.7M');
    assert.equal(formatFullNumber('−1,654,382.70'), '-1,654,382.70');
});

test('invalid display values safely fall back to zero', () => {
    assert.equal(formatCompactNumber('not-a-number'), '0');
    assert.equal(formatFullNumber('not-a-number'), '0');
});

test('a Persian locale renders Persian digits for both full and compact figures', () => {
    assert.equal(
        formatFullNumber('227,963,512.30', 'fa-IR'),
        '\u06f2\u06f2\u06f7\u066c\u06f9\u06f6\u06f3\u066c\u06f5\u06f1\u06f2\u066b\u06f3\u06f0',
    );
    assert.equal(
        formatCompactNumber('227,963,512.30', 'fa-IR'),
        '\u06f2\u06f2\u06f8\u00a0\u0645\u06cc\u0644\u06cc\u0648\u0646',
    );
});
