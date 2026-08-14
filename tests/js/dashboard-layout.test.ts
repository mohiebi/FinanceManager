import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const source = readFileSync(
    new URL('../../resources/js/pages/Dashboard.vue', import.meta.url),
    'utf8',
);

function classesFor(attribute: string): string {
    const element = source.match(
        new RegExp(`<div\\s+[^>]*${attribute}[^>]*class="([^"]+)"`),
    );

    assert.ok(element, `Could not find dashboard section with ${attribute}`);

    return element[1];
}

test('Flight Log uses the same full-width grid as recent transactions', () => {
    const flightLog = source.indexOf('data-dashboard-flight-log');
    const recentTransactions = source.indexOf(
        'data-dashboard-recent-transactions',
    );
    const flightLogClasses = classesFor('data-dashboard-flight-log');
    const recentClasses = classesFor('data-dashboard-recent-transactions');

    assert.ok(flightLog >= 0);
    assert.ok(recentTransactions > flightLog);
    assert.ok(flightLogClasses.split(' ').includes('px-[18px]'));
    assert.ok(flightLogClasses.split(' ').includes('xl:grid-cols-2'));
    assert.ok(recentClasses.split(' ').includes('px-[18px]'));
    assert.ok(recentClasses.split(' ').includes('xl:grid-cols-2'));
});

test('Flight Log remains conditional when its module data is absent', () => {
    assert.match(
        source,
        /v-if="props\.streak && props\.logbook"\s+data-dashboard-flight-log/,
    );
});
