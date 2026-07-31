import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

import { computePace } from '../../resources/js/lib/goals.ts';
import { encode } from '../../resources/js/lib/vault/codec.ts';

/**
 * The same fixture is asserted by tests/Unit/GoalPaceTest.php.
 *
 * With the vault armed the browser computes goal progress instead of the server,
 * so two implementations exist. If either drifts, both suites fail and name the
 * exact case.
 */
const vectors = JSON.parse(
    readFileSync(new URL('../fixtures/goal-vectors.json', import.meta.url), 'utf8'),
);

test('goal pace matches the shared fixture', () => {
    for (const testCase of vectors.cases) {
        const { q0, qNow, qT, elapsed, total } = testCase.input;
        const actual = computePace(q0, qNow, qT, elapsed, total);

        for (const [key, expected] of Object.entries(testCase.expected)) {
            assert.deepEqual(
                actual[key as keyof typeof actual],
                expected,
                `${testCase.name} / ${key}`,
            );
        }
    }
});

test('a sealed quantity is serialised exactly as the server serialises it', () => {
    for (const testCase of vectors.encoding) {
        // The 8dp type exists because `decimal` seals 0.00012345 as "0.00",
        // which would give a crypto goal a target of zero.
        assert.equal(
            encode(testCase.value, 'quantity'),
            testCase.quantity,
            `quantity / ${testCase.value}`,
        );
        // Only asserted where the fixture pins one — boundary values are left
        // out on purpose, see the fixture's comment.
        if (testCase.decimal !== undefined) {
            assert.equal(
                encode(testCase.value, 'decimal'),
                testCase.decimal,
                `decimal / ${testCase.value}`,
            );
        }
    }
});
