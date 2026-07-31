<?php

namespace App\Enums;

/**
 * How a single day reads in the streak chain.
 */
enum StreakDayState: string
{
    /** At least one transaction was recorded. */
    case Logged = 'logged';

    /** The user stated they spent nothing. Sustains the run exactly like Logged. */
    case NoSpend = 'no_spend';

    /** Nothing was recorded, but the week's grace covered it. */
    case Grace = 'grace';

    /** Nothing recorded, and nothing left to cover it. */
    case Missed = 'missed';

    /** Today, still open — not yet recorded, and not yet a miss. */
    case Open = 'open';

    /** Whether a day in this state keeps a run alive. */
    public function sustainsRun(): bool
    {
        return match ($this) {
            self::Logged, self::NoSpend, self::Grace => true,
            self::Missed, self::Open => false,
        };
    }
}
