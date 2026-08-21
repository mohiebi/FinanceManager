import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

function source(path: string): string {
    return readFileSync(new URL(path, import.meta.url), 'utf8');
}

const bills = source('../../resources/js/pages/Bills.vue');
const cipheredMoney = source('../../resources/js/components/CipheredMoney.vue');
const compactMoney = source('../../resources/js/components/CompactMoney.vue');
const lineChart = source('../../resources/js/components/charts/LineChart.vue');
const modules = source('../../resources/js/pages/settings/Modules.vue');
const amountMask = source('../../resources/js/composables/useAmountMask.ts');
const portfolio = source('../../resources/js/pages/Portfolio.vue');
const preferences = source('../../resources/js/pages/settings/Preferences.vue');
const report = source('../../resources/js/pages/Report.vue');
const transactions = source('../../resources/js/pages/Transactions.vue');

test('compact figures are limited to totals and preserve exact table values', () => {
    assert.doesNotMatch(cipheredMoney, /useCompactFigures/);
    assert.doesNotMatch(cipheredMoney, /notation:\s*'compact'/);
    assert.match(transactions, /:value="summaryCost"/);
    assert.match(transactions, /:value="summaryIncome"/);
    assert.match(report, /<CompactMoney[\s\S]*?:value="balanceTotal"/);
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

test('compact figures lives under Money preferences, not App modules', () => {
    assert.doesNotMatch(modules, /control-id="compact_figures_enabled"/);
    assert.match(preferences, /control-id="compact_figures_enabled"/);
});

test('portfolio summary totals use selected-currency values', () => {
    assert.match(portfolio, /summary\.total_current_value_formatted/);
    assert.match(portfolio, /summary\.total_cost_basis_formatted/);
    assert.match(portfolio, /summary\.total_pnl_formatted/);
    assert.match(portfolio, /summary\.total_realised_pnl_formatted/);
    assert.match(portfolio, /asset\.pnl_formatted/);
    assert.doesNotMatch(portfolio, /:value="summary\.total_pnl \?\? 0"/);
    assert.doesNotMatch(
        portfolio,
        /:value="\s*summary\.total_realised_pnl \?\? 0/,
    );
    assert.match(portfolio, /:value-prefix="chartValuePrefix"/);
    assert.match(portfolio, /:value-suffix="chartValueSuffix"/);
    assert.match(lineChart, /formatter: formatAxisValue/);
    assert.match(lineChart, /formatter: formatTooltipValue/);
});

test('compact money exposes exact tooltips and colours accounting losses red', () => {
    assert.match(compactMoney, /formatCompactCurrencyDisplay/);
    assert.match(compactMoney, /<TooltipContent/);
    assert.match(compactMoney, /!text-\[#E94E50\]/);
    assert.match(cipheredMoney, /!text-\[#E94E50\]/);
});

test('transaction rows expose edit and delete actions', () => {
    assert.match(transactions, /@click="openEditForm\(transaction\)"/);
    assert.match(transactions, /@click="void requestDelete\(transaction\)"/);
    assert.match(transactions, /<ConfirmDeleteModal/);
});

test('amount-mask device state is scoped per user', () => {
    assert.match(amountMask, /page\.props\?\.auth\.user\?\.id/);
    assert.match(amountMask, /STORAGE_KEY_PREFIX \+ ':'/);
});

test('amount-mask page and state initialization are safe for SSR', () => {
    const useAmountMaskDeclaration = amountMask.indexOf(
        'export function useAmountMask',
    );
    const usePageCall = amountMask.indexOf('const page = usePage()');

    assert.ok(useAmountMaskDeclaration >= 0);
    assert.ok(usePageCall > useAmountMaskDeclaration);
    assert.match(amountMask, /typeof window === 'undefined'/);
    assert.match(
        amountMask,
        /return createControls\(ref\(amountMaskDefault\(page\)\)\)/,
    );
});

test('category filters remain in the same responsive row as search', () => {
    assert.match(transactions, /sm:!w-\[220px\]/);
    assert.match(report, /sm:!w-\[240px\]/);
});
