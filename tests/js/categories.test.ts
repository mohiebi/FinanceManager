import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

import {
    optionLabel,
    orderByParent,
    SUBCATEGORY_ITEM_CLASS,
} from '../../resources/js/lib/categories.ts';

function source(path: string): string {
    return readFileSync(new URL(path, import.meta.url), 'utf8');
}

const category = (
    id: number,
    name: string,
    parentId: number | null = null,
) => ({
    id,
    name,
    parent_id: parentId,
});

test('every parent is followed by its own subcategories', () => {
    const ordered = orderByParent([
        category(1, 'Food'),
        category(2, 'Transport'),
        category(3, 'Restaurant', 1),
        category(4, 'Taxi', 2),
        category(5, 'Cafe', 1),
    ]);

    assert.deepEqual(
        ordered.map(({ name, depth }) => [name, depth]),
        [
            ['Food', 0],
            ['Restaurant', 1],
            ['Cafe', 1],
            ['Transport', 0],
            ['Taxi', 1],
        ],
    );
});

test('a shared category merged from both types is listed once', () => {
    // The server lists a shared category under cost and income; the filters
    // merge the two lists, which would otherwise show it twice.
    const gift = category(7, 'Gift');
    const ordered = orderByParent([gift, category(8, 'Salary'), gift]);

    assert.deepEqual(
        ordered.map(({ id }) => id),
        [7, 8],
    );
});

test('a subcategory whose parent is missing stays selectable at the top level', () => {
    const ordered = orderByParent([category(3, 'Restaurant', 99)]);

    assert.deepEqual(
        ordered.map(({ name, depth }) => [name, depth]),
        [['Restaurant', 0]],
    );
});

test('native options indent subcategories with no-break spaces', () => {
    const [food, restaurant] = orderByParent([
        category(1, 'Food'),
        category(3, 'Restaurant', 1),
    ]);

    assert.equal(optionLabel(food), 'Food');
    // Ordinary spaces collapse inside option text; U+00A0 does not.
    assert.equal(optionLabel(restaurant), '\u00A0'.repeat(4) + 'Restaurant');
});

test('the indent escape is spelled out rather than pasted in', () => {
    // A literal U+00A0 looks like a space in an editor and gets "tidied" into
    // one, silently collapsing every subcategory indent.
    const lib = source('../../resources/js/lib/categories.ts');

    assert.doesNotMatch(lib, /\u00A0/);
    assert.match(lib, /'\\u00A0'\.repeat\(4\)/);
});

test('reka subcategory items flip their indent under RTL', () => {
    // The base item pads with physical pl-2/pr-8 and pins its checkmark right,
    // so a logical ps-* would not override it.
    assert.equal(SUBCATEGORY_ITEM_CLASS, 'pl-7 rtl:pl-2 rtl:pr-13');
});

test('every category picker groups subcategories under their parent', () => {
    const pickers = {
        dialog: source(
            '../../resources/js/components/transactions/TransactionDialog.vue',
        ),
        transactions: source('../../resources/js/pages/Transactions.vue'),
        report: source('../../resources/js/pages/Report.vue'),
        budgets: source('../../resources/js/pages/Budgets.vue'),
        bills: source('../../resources/js/pages/Bills.vue'),
    };

    for (const [name, file] of Object.entries(pickers)) {
        assert.match(file, /orderByParent\(/, name);
    }

    // Native selects indent with the label helper; reka selects with the class.
    assert.match(pickers.dialog, /\{\{ optionLabel\(category\) \}\}/);
    assert.match(pickers.budgets, /\{\{ optionLabel\(category\) \}\}/);

    for (const name of ['transactions', 'report', 'bills'] as const) {
        assert.match(pickers[name], /SUBCATEGORY_ITEM_CLASS/, name);
    }

    // The filters merge both types' lists and rely on orderByParent to drop
    // the repeat of a shared category.
    assert.match(
        pickers.transactions,
        /orderByParent\(\[\.\.\.props\.categories\.cost, \.\.\.props\.categories\.income\]\)/,
    );
    assert.match(
        pickers.report,
        /orderByParent\(\[\.\.\.props\.categories\.cost, \.\.\.props\.categories\.income\]\)/,
    );
});

test('the vault budget sums every category a line owns', () => {
    const vaultBudget = source(
        '../../resources/js/composables/useVaultBudget.ts',
    );

    // A parent line owns its unclaimed subcategories too; reading only the
    // first id was right while a line could only ever own one.
    assert.doesNotMatch(vaultBudget, /category_ids\[0\]/);
    assert.match(
        vaultBudget,
        /line\.category_ids\.reduce\([\s\S]*?spentByCategory\.get\(categoryId\)/,
    );
});

test('the category settings page edits the parent and the sharing', () => {
    const settings = source('../../resources/js/pages/settings/Categories.vue');

    assert.match(settings, /v-model="createForm\.parent_id"/);
    assert.match(settings, /v-model="editForm\.parent_id"/);
    assert.match(settings, /:checked="createForm\.for_both_types"/);
    assert.match(settings, /:checked="\s*editForm\.for_both_types\s*"/);

    // A category with subcategories cannot become one, so its parent picker
    // is locked rather than left to fail on save.
    assert.match(
        settings,
        /v-model="editForm\.parent_id"\s*:disabled="hasChildren\(category\)"/,
    );

    // Dragging only reorders siblings.
    assert.match(settings, /source\.parent_id !== targetCategory\.parent_id/);
});
