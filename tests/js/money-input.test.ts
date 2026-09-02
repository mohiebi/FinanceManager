import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

import {
    formatMoneyInput,
    normalizeMoneyInput,
} from '../../resources/js/composables/useMoneyInput.ts';

function source(path: string): string {
    return readFileSync(new URL(path, import.meta.url), 'utf8');
}

const sellDialog = source(
    '../../resources/js/components/investments/InvestmentSellDialog.vue',
);
const entryDialog = source(
    '../../resources/js/components/investments/InvestmentEntryDialog.vue',
);

test('typed digits are grouped in threes', () => {
    assert.equal(formatMoneyInput('200000000', 'toman'), '200,000,000');
    assert.equal(formatMoneyInput('1000', 'toman'), '1,000');
    assert.equal(formatMoneyInput('999', 'toman'), '999');
    assert.equal(formatMoneyInput('', 'toman'), '');
});

test('the grouped display is not what the form submits', () => {
    assert.equal(normalizeMoneyInput('200,000,000', 'toman'), '200000000');
});

test('persian and arabic digits are folded to ascii', () => {
    assert.equal(normalizeMoneyInput('۲۰۰۰۰', 'toman'), '20000');
    assert.equal(normalizeMoneyInput('٥٠٠', 'toman'), '500');
    // Persian thousands separator and decimal mark, as a Persian keyboard sends them.
    assert.equal(normalizeMoneyInput('۱٬۲۳۴', 'toman'), '1234');
    assert.equal(normalizeMoneyInput('۱٫۵', 'usd'), '1.5');
});

test('toman is a whole-unit currency, so a decimal point ends the number', () => {
    assert.equal(normalizeMoneyInput('1200.75', 'toman'), '1200');
    assert.equal(normalizeMoneyInput('1200.75', 'usd'), '1200.75');
});

test('other currencies keep a single decimal point', () => {
    assert.equal(normalizeMoneyInput('1.2.3', 'usd'), '1.23');
    assert.equal(normalizeMoneyInput('.5', 'usd'), '0.5');
    assert.equal(formatMoneyInput('1234.5', 'usd'), '1,234.5');
});

test('letters and stray punctuation never reach the form', () => {
    assert.equal(normalizeMoneyInput('12 000 toman!', 'toman'), '12000');
});

test('the sell dialog uses the shared money field, not a bare input', () => {
    // The complaint this fixes: proceeds were typed as one unbroken run of
    // digits, with none of the grouping or the x1000 button every other money
    // field in the app has.
    assert.match(sellDialog, /useMoneyInput/);
    assert.match(sellDialog, /finance-dialog-money-field/);
    assert.match(sellDialog, /finance-dialog-money-button/);
    assert.match(sellDialog, /v-model="displayTotalSale"/);
});

test('the proceeds and currency controls share their grid rows', () => {
    // Without the shared tracks a wrapped proceeds label pushed its input a line
    // below the currency select sitting beside it.
    const rows = sellDialog.match(/sm:grid-rows-subgrid/g);

    assert.equal(rows?.length, 2);
});

test('the sell date uses the calendar-aware picker like the other dialogs', () => {
    // A native date input is Gregorian-only, which is the wrong calendar for a
    // Jalali user and a different control from every sibling dialog.
    assert.match(sellDialog, /<BirthdatePicker/);
    assert.doesNotMatch(sellDialog, /type="date"/);
});

test('both investment dialogs are built on the same shell', () => {
    // The sell dialog used to be a narrower box with its own padding and a split
    // pair of full-width buttons, which is what made it read as a different
    // component from every other finance dialog.
    for (const dialog of [sellDialog, entryDialog]) {
        assert.match(dialog, /sm:max-w-\[560px\]/);
        assert.match(dialog, /sm:px-\[80px\]/);
        // A scrolling body inside a fixed-height shell, so the footer stays put.
        assert.match(dialog, /flex-1 overflow-y-auto/);
        assert.match(dialog, /flex shrink-0 justify-end gap-2/);
    }
});

test('the sell dialog header carries a description like its siblings', () => {
    // Radix also warns about a DialogContent with no description.
    assert.match(sellDialog, /<DialogDescription/);
    assert.match(sellDialog, /finance\.investments\.sell_description/);
});
