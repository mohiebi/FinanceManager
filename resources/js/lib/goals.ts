/**
 * The quantity half of a savings goal, ported from App\Support\GoalPace.
 *
 * Under the vault both the target and the holdings are ciphertext, so the server
 * cannot compute progress and the browser does it instead. Two implementations
 * therefore exist; tests/fixtures/goal-vectors.json is the only thing keeping
 * them honest — see tests/js/goals.test.ts and BuildGoalProgressTest.php.
 *
 * Dates are deliberately absent: they are never encrypted, so the server always
 * supplies `elapsed` and `total` and no calendar maths is duplicated here.
 */
const PRECISION = 6;

export type GoalPace = {
    progress: number | null;
    expected_quantity: number;
    pace_delta: number;
    on_track: boolean;
    required_per_day: number;
    days_remaining: number;
};

function round(value: number): number {
    return Number(value.toFixed(PRECISION));
}

export function computePace(
    baseline: number,
    current: number,
    target: number,
    elapsed: number,
    total: number,
): GoalPace {
    const daysRemaining = Math.max(0, total - elapsed);

    // Past the target date the expectation is simply the whole target; a ratio
    // above 1 would keep raising the bar after the deadline.
    const expected =
        total > 0
            ? baseline + (target - baseline) * (Math.min(elapsed, total) / total)
            : target;

    const paceDelta = current - expected;

    return {
        // Null rather than zero for a zero target: "0% of nothing" is not a fact
        // about the user, and a bar cannot render it honestly.
        progress: target > 0 ? round(current / target) : null,
        expected_quantity: round(expected),
        pace_delta: round(paceDelta),
        on_track: paceDelta >= 0,
        // Clamped at zero: someone past their target does not owe a negative
        // amount per day. Divides by at least 1 so the last day is finite.
        required_per_day: round(
            Math.max(0, target - current) / Math.max(1, daysRemaining),
        ),
        days_remaining: daysRemaining,
    };
}
