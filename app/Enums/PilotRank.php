<?php

namespace App\Enums;

/**
 * How far into the habit a user is.
 *
 * Earned by days recorded, never by balance or net worth: two people with
 * identical discipline and wildly different incomes hold the same rank, which is
 * the entire point. Nothing here is comparable between users anyway — there is
 * no leaderboard and there will not be one.
 */
enum PilotRank: string
{
    case Cadet = 'cadet';
    case Pilot = 'pilot';
    case Captain = 'captain';

    /** Days with a record needed to reach this rank. */
    public function threshold(): int
    {
        return match ($this) {
            self::Cadet => 0,
            // Roughly a month and roughly six months of daily records.
            self::Pilot => 30,
            self::Captain => 180,
        };
    }

    public static function forDaysLogged(int $days): self
    {
        return match (true) {
            $days >= self::Captain->threshold() => self::Captain,
            $days >= self::Pilot->threshold() => self::Pilot,
            default => self::Cadet,
        };
    }

    /** The next rank up, or null at the top. */
    public function next(): ?self
    {
        return match ($this) {
            self::Cadet => self::Pilot,
            self::Pilot => self::Captain,
            self::Captain => null,
        };
    }
}
