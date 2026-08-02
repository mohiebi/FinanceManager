import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

import { computeAllowances } from '../../resources/js/lib/budget.ts';

/**
 * The same fixture is asserted by tests/Unit/BudgetMathTest.php.
 *
 * With the vault armed the browser resolves budget allowances instead of the
 * server, so two implementations exist. If either drifts, both suites fail and
 * name the exact case.
 */
const vectors = JSON.parse(
    readFileSync(new URL('../fixtures/budget-vectors.json', import.meta.url), 'utf8'),
);

test('budget allowances match the shared fixture', () => {
    for (const testCase of vectors.cases) {
        const { income, lines } = testCase.input;
        const actual = computeAllowances(income, lines);

        for (const [key, expected] of Object.entries(testCase.expected)) {
            assert.deepEqual(
                actual[key as keyof typeof actual],
                expected,
                `${testCase.name} / ${key}`,
            );
        }
    }
});
