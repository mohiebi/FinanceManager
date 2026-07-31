<?php

use App\Casts\UserEncrypted;
use App\Support\GoalPace;

/**
 * The server's goal pace, checked against the fixture the browser's port is
 * checked against.
 *
 * Under the vault the same maths runs in resources/js/lib/goals.ts, so two
 * implementations exist. This fixture is the only thing keeping them honest —
 * see tests/js/goals.test.ts.
 */
function goalVectors(): array
{
    return json_decode(
        file_get_contents(dirname(__DIR__).'/fixtures/goal-vectors.json'),
        true,
    );
}

test('goal pace matches the shared fixture', function () {
    foreach (goalVectors()['cases'] as $case) {
        $input = $case['input'];

        $actual = GoalPace::compute(
            (float) $input['q0'],
            (float) $input['qNow'],
            (float) $input['qT'],
            (int) $input['elapsed'],
            (int) $input['total'],
        );

        foreach ($case['expected'] as $key => $expected) {
            // JSON has one number type; PHP has two. Compare loosely on numbers
            // only, so `3` still matches `3.0` without weakening null or bool.
            if (is_numeric($expected) && is_numeric($actual[$key])) {
                expect((float) $actual[$key])->toBe((float) $expected, "{$case['name']} / {$key}");

                continue;
            }

            expect($actual[$key])->toBe($expected, "{$case['name']} / {$key}");
        }
    }
});

test('a sealed quantity is serialised exactly as the browser serialises it', function () {
    foreach (goalVectors()['encoding'] as $case) {
        // Same two casts the models use, so a drift in either shows up here
        // rather than as a silently rounded target in someone's vault.
        $quantity = new UserEncrypted('decimal', '8');

        expect($quantity->encode($case['value']))->toBe($case['quantity']);

        // Only asserted where the fixture pins one — boundary values are left
        // out on purpose, see the fixture's comment.
        if (isset($case['decimal'])) {
            expect((new UserEncrypted('decimal', '2'))->encode($case['value']))
                ->toBe($case['decimal']);
        }
    }
});
