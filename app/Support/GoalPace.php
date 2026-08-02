<?php

namespace App\Support;

/**
 * The quantity half of a savings goal.
 *
 * Split out from everything else because it is the only part the browser has to
 * recompute: under the vault both the target and the holdings are ciphertext,
 * while the dates never are. Five numbers in, five out — no Carbon, no calendar,
 * nothing that a Jalali conversion could disagree about.
 *
 * Mirrored by resources/js/lib/goals.ts and pinned by tests/fixtures/goal-vectors.json.
 */
class GoalPace
{
    /** Both sides round only at the end, so intermediates never diverge. */
    public const PRECISION = 6;

    /**
     * @param  float  $baseline  holdings when the goal was set
     * @param  float  $current  holdings now, net of disposals
     * @param  float  $target  what the user is aiming for
     * @param  int  $elapsed  days from the start to today
     * @param  int  $total  days from the start to the target date
     * @return array{
     *     progress: float|null,
     *     expected_quantity: float,
     *     pace_delta: float,
     *     on_track: bool,
     *     reached: bool,
     *     required_per_day: float,
     *     days_remaining: int,
     * }
     */
    public static function compute(
        float $baseline,
        float $current,
        float $target,
        int $elapsed,
        int $total,
    ): array {
        $daysRemaining = max(0, $total - $elapsed);

        // Past the target date the expectation is simply the whole target; a
        // ratio above 1 would keep raising the bar after the deadline.
        $expected = $total > 0
            ? $baseline + ($target - $baseline) * (min($elapsed, $total) / $total)
            : $target;

        $paceDelta = $current - $expected;

        return [
            // Null rather than zero for a zero target: "0% of nothing" is not a
            // fact about the user, and a bar cannot render it honestly.
            'progress' => $target > 0 ? self::round($current / $target) : null,
            'expected_quantity' => self::round($expected),
            'pace_delta' => self::round($paceDelta),
            'on_track' => $paceDelta >= 0,
            // The target is met, not merely being met on schedule. Kept separate
            // from `on_track` because someone at 103% is not "on track" — they
            // are finished, and saying otherwise buries the moment worth marking.
            // A zero target is never reached: there was nothing to reach.
            'reached' => $target > 0 && $current >= $target,
            // Clamped at zero: someone past their target does not owe a negative
            // amount per day. Divides by at least 1 so the last day is finite.
            'required_per_day' => self::round(max(0.0, $target - $current) / max(1, $daysRemaining)),
            'days_remaining' => $daysRemaining,
        ];
    }

    private static function round(float $value): float
    {
        return round($value, self::PRECISION);
    }
}
