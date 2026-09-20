import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

function source(path: string): string {
    return readFileSync(new URL(path, import.meta.url), 'utf8');
}

const bills = source('../../resources/js/pages/Bills.vue');
const dashboard = source('../../resources/js/pages/Dashboard.vue');
const cipheredMoney = source('../../resources/js/components/CipheredMoney.vue');
const compactMoney = source('../../resources/js/components/CompactMoney.vue');
const donutChart = source(
    '../../resources/js/components/charts/DonutChart.vue',
);
const lineChart = source('../../resources/js/components/charts/LineChart.vue');
const modules = source('../../resources/js/pages/settings/Modules.vue');
const amountMask = source('../../resources/js/composables/useAmountMask.ts');
const portfolio = source('../../resources/js/pages/Portfolio.vue');
const investments = source('../../resources/js/pages/Investments.vue');
const preferences = source('../../resources/js/pages/settings/Preferences.vue');
const report = source('../../resources/js/pages/Report.vue');
const transactions = source('../../resources/js/pages/Transactions.vue');
const milesHub = source('../../resources/js/pages/Miles/Index.vue');
const milesIcon = source('../../resources/js/components/MilesIcon.vue');
const celebration = source(
    '../../resources/js/components/MilesCelebrationDialog.vue',
);
const milesPill = source('../../resources/js/components/MilesPill.vue');

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
    assert.match(portfolio, /:compact-values="shouldCompactChartValues"/);
    assert.match(
        portfolio,
        /compactFigures\.value \|\| selectedCurrency\.value === 'toman'/,
    );
    assert.match(
        portfolio,
        /:value-fraction-digits="chartValueFractionDigits"/,
    );
    assert.match(portfolio, /:masked="masked"/);
    assert.match(lineChart, /formatter: formatAxisValue/);
    assert.match(lineChart, /formatter: formatTooltipValue/);
    assert.match(lineChart, /props\.masked\s*\?\s*'••••••'/);
    assert.match(lineChart, /props\.compactValues === false/);
    assert.match(lineChart, /formatCompactCurrencyNumber/);
    assert.match(portfolio, /:tooltip-formatter="formatAllocationPercentage"/);
    assert.match(portfolio, /:value="asset\.current_value_formatted"/);
    assert.match(portfolio, /function formatAllocationPercentage/);
    assert.match(portfolio, /:center-value="allocationCenterValue"/);
    assert.match(portfolio, /formatCompactCurrencyDisplay/);
    assert.match(portfolio, /shouldCompactChartValues\.value/);
    assert.match(portfolio, /mask-variant="placeholder"/);
    assert.match(donutChart, /props\.tooltipFormatter/);
    assert.match(donutChart, /props\.masked \? '••••••'/);
});

test('compact money exposes exact tooltips and colours accounting losses red', () => {
    assert.match(compactMoney, /formatCompactCurrencyDisplay/);
    assert.match(compactMoney, /<TooltipContent/);
    assert.match(compactMoney, /!text-\[#E94E50\]/);
    assert.match(cipheredMoney, /!text-\[#E94E50\]/);
    assert.match(compactMoney, /maskVariant\?: 'blur' \| 'placeholder'/);
    assert.match(compactMoney, /blur-\[6px\]/);
    assert.match(compactMoney, /before:bg-white\/30/);
    assert.match(cipheredMoney, /blur-\[6px\]/);
    assert.doesNotMatch(cipheredMoney, /masked \? '••••••'/);
    assert.match(dashboard, /mask-variant="placeholder"/);
    assert.match(dashboard, /const maskClass[\s\S]*?blur-\[6px\]/);
    assert.doesNotMatch(dashboard, /const maskClass[\s\S]*?text-transparent/);
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

test('transaction filters and rows fit compact screens without hiding amounts', () => {
    assert.match(transactions, /flex-col items-stretch[\s\S]*sm:flex-row/);
    assert.match(
        transactions,
        /container-class="w-full grid-cols-\[1\.2fr_1fr_1fr\] sm:w-auto"/,
    );
    // The leading 22px track is the batch-select checkbox; the point of the
    // assertion is that subject, amount and actions still each get a track on
    // a narrow screen rather than the amount being hidden.
    assert.match(
        transactions,
        /grid-cols-\[22px_minmax\(0,1fr\)_max-content_68px\]/,
    );
    assert.match(
        transactions,
        /hidden text-xs text-\[#686868\][\s\S]*sm:block/,
    );
    assert.match(transactions, /class="mt-1 sm:hidden"/);
});

test('report filters and rows prioritise amounts on compact screens', () => {
    assert.match(report, /flex-col items-stretch[\s\S]*sm:flex-row/);
    assert.match(report, /container-class="grid-cols-\[1\.2fr_1fr_1fr\]"/);
    assert.match(
        report,
        /grid-cols-\[minmax\(0,1fr\)_max-content\][\s\S]*sm:grid-cols-\[minmax\(120px,1fr\)_112px_92px_118px\]/,
    );
    assert.match(report, /class="mt-1 sm:hidden"/);
});

test('portfolio export sits with the completed detail and investment entries fit compact screens', () => {
    assert.match(portfolio, /finance\.portfolio\.export_profit_loss_hint/);
    assert.match(portfolio, /assets\.length > 0 && !props\.vaultPortfolio/);
    assert.match(investments, /grid-cols-\[20px_minmax\(0,1fr\)_max-content\]/);
    assert.match(investments, /grid-cols-\[minmax\(0,1fr\)_max-content\]/);
    assert.match(investments, /opacity-100 transition sm:opacity-0/);
});

test('the Miles mark is one winged-coin component, not a stock sparkle', () => {
    // The wing path is the mark; every surface has to draw the same one, so
    // it lives in a component rather than being pasted three times.
    assert.match(milesIcon, /<circle cx="12" cy="12" r="8\.5" \/>/);
    assert.match(milesIcon, /M6\.8 13\.4c2\.2 0 4\.3-1\.2 5\.3-5/);
    assert.match(milesIcon, /stroke="currentColor"/);

    for (const [name, file] of [
        ['hub', milesHub],
        ['celebration', celebration],
        ['pill', milesPill],
    ] as const) {
        assert.match(
            file,
            /import MilesIcon from '@\/components\/MilesIcon\.vue'/,
            name,
        );
        assert.match(file, /<MilesIcon/, name);
    }

    assert.doesNotMatch(celebration, /Sparkles/);

    // The pill's balance side carries the currency in gold; its claim button
    // keeps a separate action icon rather than repeating the same mark twice.
    assert.match(
        milesPill,
        /<MilesIcon class="size-3\.5 text-\[#d9c48f\]" \/>/,
    );
    assert.doesNotMatch(milesPill, /CircleGauge/);
});

test('the claim modal pays out in gold and shows the week it belongs to', () => {
    // Gold marks the currency, green stays the action: the reward reads gold
    // while the dismiss button keeps the action colour.
    assert.match(celebration, /text-3xl font-bold text-\[#d9c48f\]/);
    assert.match(celebration, /bg-\[#d9c48f\]\/12 text-\[#d9c48f\]/);
    assert.match(celebration, /rounded-xl bg-\[#02cd86\]/);

    // Seven dots: gold behind the claim, green on the day that opens next.
    assert.match(celebration, /CLAIM_CYCLE_LENGTH = 7/);
    assert.match(celebration, /index < step\s*\?\s*'bg-\[#d9c48f\]'/);
    assert.match(celebration, /index === step\s*\?\s*'bg-\[#5eeeb5\]'/);
    assert.match(celebration, /v-for="\(dot, day\) in cycleDots"/);
});

test('transactions can be batch-selected for group edit and delete', () => {
    // Both tables gained a leading checkbox column, so header and rows have to
    // keep the same track count or the columns drift apart.
    const gridTracks = transactions.match(
        /grid-cols-\[22px_minmax\(0,1fr\)_max-content_68px\][^"]*sm:grid-cols-\[22px_minmax\(120px,1fr\)_112px_92px_118px_68px\]/g,
    );
    assert.equal(gridTracks?.length, 4);

    // The hover/selected highlight is pulled out past the content box so the
    // checkbox is not flush against its left edge; the matching px-2 keeps the
    // columns lined up with the header row, which has no highlight to inset.
    const insetRows = transactions.match(/-mx-2 grid grid-cols-\[22px_/g);
    assert.equal(insetRows?.length, 2);
    assert.doesNotMatch(
        transactions,
        /-mx-2[^"]*pb-2\.5 text-\[10px\] font-medium/,
    );

    assert.match(transactions, /:checked="costsSelectionState"/);
    assert.match(transactions, /:checked="incomesSelectionState"/);
    assert.match(transactions, /@update:checked="toggleAllCosts"/);
    assert.match(transactions, /@update:checked="toggleAllIncomes"/);

    // The toolbar only exists while something is ticked, and routes through
    // Wayfinder rather than hard-coded bulk URLs.
    assert.match(transactions, /v-if="totalSelected > 0"/);
    assert.match(transactions, /router\.delete\(destroyBulk\.url\(\)/);
    assert.match(transactions, /router\.patch\(\s*updateBulkCategory\.url\(\)/);
    assert.doesNotMatch(transactions, /'\/transactions\/bulk/);

    // A mixed cost+income selection has no single type to send, so the
    // category control is replaced by an explanation instead of submitting.
    assert.match(transactions, /v-if="selectionType !== null"/);
    assert.match(transactions, /t\('finance\.transactions\.bulk_type_mixed'\)/);
});

test('a transaction selection never outlives the rows it was made on', () => {
    // Paging preserves component state, so the sets are re-intersected with
    // whatever rows arrived rather than accumulating ids across pages.
    assert.match(
        transactions,
        /watch\(\s*\(\) => props\.transactions,[\s\S]*?intersect\(\s*selectedCostIds\.value,/,
    );
    assert.match(
        transactions,
        /intersect\(\s*selectedIncomeIds\.value,\s*transactions\.incomes,/,
    );
    // A category belongs to one type, so switching tables drops the choice.
    assert.match(
        transactions,
        /watch\(selectionType, \(\) => \{\s*bulkCategoryId\.value = '';/,
    );
});

test('the transactions page size is a filter, and changing it returns to page one', () => {
    // The options come from the server rather than being duplicated here, so
    // the control can never offer a size the request would reject.
    assert.match(transactions, /v-for="option in props\.perPageOptions"/);
    assert.match(transactions, /per_page: filterPerPage\.value/);

    // applyFilters() with no page argument is what resets both tables: a page 4
    // that no longer exists at 100 rows a page would render empty.
    assert.match(
        transactions,
        /watch\(\[filterCategory, filterFrom, filterTo, filterPerPage\], \(\) =>\s*applyFilters\(\),/,
    );
});
