import assert from 'node:assert/strict';
import { test } from 'node:test';

import { intlLocale, localizeDigits } from '../../resources/js/lib/locale.ts';

test('only Persian gets the region-qualified Intl tag', () => {
    assert.equal(intlLocale('fa'), 'fa-IR');
    assert.equal(intlLocale('en'), 'en');
    assert.equal(intlLocale('de'), 'de');
});

test('localizeDigits swaps the glyphs without grouping the number', () => {
    // A calendar year is never grouped ("1,405"), and a day count under 1000
    // would not show grouping either way — this only proves the glyph swap
    // does not also turn on a separator that was never there before.
    assert.equal(localizeDigits(1405, 'fa'), '۱۴۰۵');
    assert.equal(localizeDigits(27, 'fa'), '۲۷');
    assert.equal(localizeDigits(0, 'fa'), '۰');
});

test('localizeDigits leaves non-Persian locales as plain digits', () => {
    assert.equal(localizeDigits(2026, 'en'), '2026');
    assert.equal(localizeDigits(1405, 'de'), '1405');
});
