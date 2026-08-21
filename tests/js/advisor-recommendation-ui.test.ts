import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const source = readFileSync(
    new URL(
        '../../resources/js/pages/Advisor/Recommendation.vue',
        import.meta.url,
    ),
    'utf8',
);
const appCss = readFileSync(
    new URL('../../resources/css/app.css', import.meta.url),
    'utf8',
);

test('one colour series drives the ring, the rows and the transition table', () => {
    // Keyed by asset rather than by row position: the table sorts by weight and
    // the plan tabs reorder it, so an index-based colour would repaint the page
    // every time a tab moved an asset up or down.
    assert.match(
        source,
        /const assetColors = computed<Record<string, string>>/,
    );
    assert.match(
        source,
        /function allocationColor\(assetKey: string\): string/,
    );
    assert.doesNotMatch(source, /allocationColor\(index\)/);
});

test('the allocation ring is labelled with every segment, not colour alone', () => {
    assert.match(source, /const ringSegments = computed/);
    assert.match(source, /const ringLabel = computed/);
    // Colour is decoration; the accessible name carries the same data.
    assert.match(source, /\$\{row\.name\} \$\{row\.target_percent\}%/);
    assert.match(source, /:label="ringLabel"/);
});

test('allocation reasoning is collapsed behind an expandable control', () => {
    assert.match(source, /const openRationales = ref<string\[\]>\(\[\]\)/);
    assert.match(
        source,
        /:aria-expanded="\s*openRationales\.includes\(row\.asset_key\)\s*"/,
    );
    assert.match(source, /@click="toggleRationale\(row\.asset_key\)"/);
});

test('the largest holding opens its rationale by default', () => {
    // It is the weight a reader questions first, so it should not need a click.
    assert.match(source, /const seededRationale = ref<string \| null>\(null\)/);
    assert.match(source, /const largest = planRows\.value\[0\]\?\.asset_key/);
});

test('the current-to-target table heads every column it renders', () => {
    // A delta column with no matching header silently shifts every column after
    // it for a screen reader.
    const table = source.slice(
        source.indexOf('<thead'),
        source.indexOf('</tbody>'),
    );
    const headerCells = table
        .slice(0, table.indexOf('</thead>'))
        .match(/<th[\s>]/g);
    const bodyCells = table.slice(table.indexOf('<tbody')).match(/<td[\s>]/g);

    assert.equal(headerCells?.length, bodyCells?.length);
});

test('the transition table only appears under the plan it was priced against', () => {
    // The server prices one move, not three. Rendering it under the safer or
    // higher-risk weights would be showing amounts nothing computed.
    assert.match(
        source,
        /v-if="payload\.transition_plan && activePlan === 'primary'"/,
    );
});

test('the plan tabs are built from the variants that actually came back', () => {
    // A safer or higher-risk plan is omitted whenever it failed validation, so
    // a hardcoded three-tab control would offer an empty view.
    assert.match(source, /const planTabs = computed/);
    assert.match(source, /\.filter\(\(tab\) => tab\.plan !== null\)/);
    assert.match(source, /v-if="planTabs\.length > 1"/);
});

test('the generating ring is driven by job stages, never by a timer alone', () => {
    // Both counters move before the work they describe, so the stage shown is
    // the stage actually reached. The sweep eases inside a stage's quarter and
    // can only cross a boundary when the job says so.
    assert.match(source, /const stageIndex = computed/);
    assert.match(source, /props\.recommendation\.provider_calls === 0/);
    assert.match(source, /props\.recommendation\.repair_attempts > 0/);
    assert.match(source, /floor \+ 24/);
});

test('the citation chip only appears when the reply cites a profile value', () => {
    // A chip under every message stops meaning anything.
    assert.match(
        source,
        /function citationFor\(message: ConversationMessage\)/,
    );
    assert.match(source, /text\.includes\(String\(risk\)\)/);
    assert.match(source, /text\.includes\(String\(drawdown\)\)/);
});

test('the redesigned surfaces carry no hardcoded English', () => {
    for (const literal of [
        '>Asset<',
        '>Current<',
        '>Target<',
        '>Difference<',
        '>Role<',
        '>Total<',
        '>Primary<',
        '>You<',
        '>Advisor<',
        'Pricing required',
        'Allow a missing diversifier',
    ]) {
        assert.ok(
            !source.includes(literal),
            `${literal} should come from a translation key`,
        );
    }
});

test('motion respects a reduced-motion preference', () => {
    // The Advisor's entry animations, the blinking chat caret and the ring's
    // background transitions are all held at their final state.
    const block = appCss.slice(
        appCss.indexOf('@media (prefers-reduced-motion: reduce)'),
    );

    assert.ok(block.length > 0, 'app.css declares a reduced-motion block');

    for (const rule of [
        '.advisor-rise',
        '.advisor-seal',
        '.advisor-blink',
        '.advisor-drift',
        '.advisor-ring',
    ]) {
        assert.ok(
            block.slice(0, 600).includes(rule),
            `${rule} is neutralised under prefers-reduced-motion`,
        );
    }

    assert.match(source, /motion-reduce:animate-none/);
});
