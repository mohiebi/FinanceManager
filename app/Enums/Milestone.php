<?php

namespace App\Enums;

/**
 * A one-time moment worth marking.
 *
 * Every one of these is about the record, never about the money: how much
 * someone spent is not an achievement, and treating it as one is what teaches
 * people to stop logging in the months they overspend.
 */
enum Milestone: string
{
    case FirstTransaction = 'first_transaction';
    case HundredTransactions = 'hundred_transactions';
    case FirstFullMonth = 'first_full_month';
    case ThirtyDayRun = 'thirty_day_run';

    /**
     * The number of records this milestone needs, when it is count-based.
     */
    public function transactionThreshold(): ?int
    {
        return match ($this) {
            self::FirstTransaction => 1,
            self::HundredTransactions => 100,
            default => null,
        };
    }

    /**
     * Milestones evaluated when a transaction is written.
     *
     * @return array<int, self>
     */
    public static function countBased(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $milestone): bool => $milestone->transactionThreshold() !== null,
        ));
    }
}
