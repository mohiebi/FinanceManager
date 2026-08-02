<?php

use App\Support\BudgetMath;

/**
 * The server's budget allowances, checked against the fixture the browser's port
 * is checked against.
 *
 * Under the vault the same maths runs in resources/js/lib/budget.ts, so two
 * implementations exist. This fixture is the only thing keeping them honest —
 * see tests/js/budget.test.ts.
 */
function budgetVectors(): array
{
    return json_decode(
        file_get_contents(dirname(__DIR__).'/fixtures/budget-vectors.json'),
        true,
    );
}

test('budget allowances match the shared fixture', function () {
    foreach (budgetVectors()['cases'] as $case) {
        $actual = BudgetMath::compute(
            (float) $case['input']['income'],
            $case['input']['lines'],
        );

        foreach ($case['expected'] as $key => $expected) {
            if ($key === 'lines') {
                expect($actual['lines'])->toHaveCount(count($expected), $case['name']);

                foreach ($expected as $index => $expectedLine) {
                    foreach ($expectedLine as $field => $value) {
                        assertMatchesVector(
                            $actual['lines'][$index][$field],
                            $value,
                            "{$case['name']} / line {$index} / {$field}",
                        );
                    }
                }

                continue;
            }

            assertMatchesVector($actual[$key], $expected, "{$case['name']} / {$key}");
        }
    }
});

/**
 * JSON has one number type; PHP has two. Compare loosely on numbers only, so
 * `0` still matches `0.0` without weakening null or bool.
 */
function assertMatchesVector(mixed $actual, mixed $expected, string $message): void
{
    if (is_numeric($expected) && is_numeric($actual)) {
        expect((float) $actual)->toBe((float) $expected, $message);

        return;
    }

    expect($actual)->toBe($expected, $message);
}
