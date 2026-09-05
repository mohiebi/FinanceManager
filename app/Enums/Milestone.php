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
    case VerifiedEmail = 'verified_email';
    case FirstTransaction = 'first_transaction';
    case FirstBill = 'first_bill';
    case FirstBudget = 'first_budget';
    case FirstInvestment = 'first_investment';
    case FirstSavingsGoal = 'first_savings_goal';
    case ThreeDayRun = 'three_day_run';
    case FirstFlightWeek = 'first_flight_week';
    case FourteenDayRun = 'fourteen_day_run';
    case HundredTransactions = 'hundred_transactions';
    case FirstFullMonth = 'first_full_month';
    case PilotRank = 'pilot_rank';
    case ThirtyDayRun = 'thirty_day_run';
    case CaptainRank = 'captain_rank';

    public function miles(): int
    {
        return match ($this) {
            self::VerifiedEmail => (int) config('miles.welcome_grant'),
            self::FirstTransaction, self::FirstBill, self::FirstBudget, self::FirstInvestment, self::FirstSavingsGoal => 5,
            self::ThreeDayRun => 8,
            self::FirstFlightWeek => 12,
            self::FourteenDayRun => 20,
            self::HundredTransactions, self::PilotRank => 25,
            self::FirstFullMonth => 30,
            self::ThirtyDayRun => 40,
            self::CaptainRank => 150,
        };
    }

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
