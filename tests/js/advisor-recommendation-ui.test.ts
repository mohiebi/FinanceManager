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

test('one colour series drives the ring, the rows and the alternatives', () => {
    // The alternatives bar used to inline its own five classes, so a colour
    // meant one asset there and a different one in the allocation list.
    assert.match(source, /const ALLOCATION_COLORS = \[/);
    assert.match(source, /function allocationColor\(index: number\): string/);
    assert.doesNotMatch(source, /'bg-\[#60a5fa\]'/);
});

test('the composition ring is labelled with every segment, not colour alone', () => {
    assert.match(source, /const compositionSegments = computed/);
    assert.match(source, /role="img"/);
    // Colour is decoration; the accessible name carries the same data.
    assert.match(source, /\$\{segment\.name\} \$\{segment\.percent\}%/);
    assert.match(source, /stroke-dasharray/);
});

test('allocation reasoning is collapsed behind an expandable control', () => {
    // Four rationales rendered permanently were the densest text on the page.
    assert.match(source, /const openRationales = ref<string\[\]>\(\[\]\)/);
    assert.match(source, /:aria-expanded="/);
    assert.match(source, /@click="toggleRationale\(allocation\.asset_key\)"/);
});

test('the current-to-target table heads every column it renders', () => {
    // A delta track was added to the body; a body cell with no matching header
    // silently shifts every column after it for a screen reader.
    const table = source.slice(
        source.indexOf('<thead'),
        source.indexOf('</tbody>'),
    );
    // `<th[\s>]` rather than `<th`, which also matches the `<thead` around them.
    const headerCells = table
        .slice(0, table.indexOf('</thead>'))
        .match(/<th[\s>]/g);
    const bodyCells = table.slice(table.indexOf('<tbody')).match(/<td[\s>]/g);

    assert.equal(headerCells?.length, bodyCells?.length);
});

test('the redesigned surfaces carry no hardcoded English', () => {
    for (const literal of [
        '>Asset<',
        '>Current<',
        '>Target<',
        '>Difference<',
        'Pricing required',
        'Coverage\n',
    ]) {
        assert.ok(
            !source.includes(literal),
            `${literal} should come from a translation key`,
        );
    }
});

test('motion respects a reduced-motion preference', () => {
    assert.match(source, /transition-\[width\][^"]*motion-reduce:transition-none/);
});
