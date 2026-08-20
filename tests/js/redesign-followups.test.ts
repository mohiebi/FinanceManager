import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

function source(path: string): string {
    return readFileSync(new URL(path, import.meta.url), 'utf8');
}

const bills = source('../../resources/js/pages/Bills.vue');
const cipheredMoney = source('../../resources/js/components/CipheredMoney.vue');
const modules = source('../../resources/js/pages/settings/Modules.vue');
const report = source('../../resources/js/pages/Report.vue');
const transactions = source('../../resources/js/pages/Transactions.vue');

test('compact figures are limited to totals and preserve exact table values', () => {
    assert.doesNotMatch(cipheredMoney, /useCompactFigures/);
    assert.doesNotMatch(cipheredMoney, /notation:\s*'compact'/);
    assert.match(transactions, /:value="summaryCost"/);
    assert.match(transactions, /:value="summaryIncome"/);
    assert.match(report, /<CompactNumber\s+:value="balanceTotal"/);
});

test('transaction and report actions use their redesigned footers', () => {
    assert.doesNotMatch(transactions, /href="\/transactions\/export"/);
    assert.match(transactions, /finance\.import\.footer_hint/);
    assert.match(report, /finance\.reports\.export_hint/);
    assert.match(report, /href="\/transactions\/export"/);
});

test('due-soon Telegram reminders show their remaining due time', () => {
    assert.match(bills, /v-if="reminderDueLabel\(bill\)"/);
    assert.match(bills, /days !== null && days < 7/);
    assert.match(bills, /statusLabel\(bill\)/);
});

test('the dark-only module screen does not render appearance controls', () => {
    assert.doesNotMatch(modules, /modules\.display\.appearance/);
    assert.doesNotMatch(modules, /modules\.display\.dark/);
});

test('category filters remain in the same responsive row as search', () => {
    assert.match(transactions, /sm:!w-\[220px\]/);
    assert.match(report, /sm:!w-\[240px\]/);
});
