<?php

namespace App\Support;

use App\Enums\Feature;
use App\Enums\PilotRank;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * How complete this month's records are.
 *
 * Not a score — a trust meter for the reports. A category breakdown built on
 * eleven of twenty days is worse than no breakdown, because it looks equally
 * authoritative. Every figure here is something the user can act on in one tap.
 *
 * Reads only plaintext columns (`occurred_at`, `category_id`, `paid_at`), so a
 * single implementation serves vault and non-vault users alike.
 */
class LogbookCompleteness
{
    /**
     * @return array{
     *     month: string,
     *     day_of_month: int,
     *     days_in_month: int,
     *     days_covered: int,
     *     days_elapsed: int,
     *     percent: int,
     *     uncategorised: int,
     *     bills_paid: int|null,
     *     bills_due: int|null,
     *     rank: string,
     *     days_logged: int,
     *     days_to_next_rank: int|null,
     * }
     */
    public function for(User $user, ?CarbonImmutable $today = null): array
    {
        $today = $today ?? $user->localToday();
        $calendar = FrontendLocalization::normalizeCalendar($user->calendar);

        $monthStart = CalendarDates::monthStart($today, $calendar);
        $descriptor = CalendarDates::monthDescriptor($today, $calendar);

        // Cast: Carbon 3 returns a float here, which would ship "8.0" to the UI.
        $daysElapsed = (int) $monthStart->diffInDays($today) + 1;
        $daysCovered = $this->daysCovered($user, $monthStart, $today);
        $bills = $this->bills($user, $monthStart, $today);

        return [
            'month' => $descriptor['label'],
            'day_of_month' => $descriptor['day_of_month'],
            'days_in_month' => $descriptor['days_in_month'],
            'days_covered' => $daysCovered,
            'days_elapsed' => $daysElapsed,
            // Measured against days elapsed, not days in the month. Showing 26%
            // on the 8th would read as failure for someone with a perfect record.
            'percent' => $daysElapsed > 0
                ? (int) round(($daysCovered / $daysElapsed) * 100)
                : 0,
            'uncategorised' => $user->transactions()
                ->whereNull('category_id')
                ->whereBetween('occurred_at', [$monthStart->toDateString(), $today->toDateString()])
                ->count(),
            'bills_paid' => $bills['paid'],
            'bills_due' => $bills['due'],
            ...$this->rank($user),
        ];
    }

    /**
     * Rank, and how far it is to the next one.
     *
     * Counted over the user's whole history rather than the current month, since
     * a rank that could go down is a punishment rather than a record.
     *
     * @return array{rank: string, days_logged: int, days_to_next_rank: int|null}
     */
    private function rank(User $user): array
    {
        // Unioned, not added: a day can hold both a transaction and a no-spend
        // marker (backdate an import row onto one, and it does), and counting it
        // twice would promote a user on fewer real days than the rank claims.
        $daysLogged = $user->transactions()
            ->distinct()
            ->pluck('occurred_at')
            ->map(fn ($date): string => $date->toDateString())
            ->concat($user->noSpendDays()->pluck('date')->map(
                fn ($date): string => $date->toDateString(),
            ))
            ->unique()
            ->count();

        $rank = PilotRank::forDaysLogged($daysLogged);
        $next = $rank->next();

        return [
            'rank' => $rank->value,
            'days_logged' => $daysLogged,
            'days_to_next_rank' => $next === null
                ? null
                : max(0, $next->threshold() - $daysLogged),
        ];
    }

    /**
     * Whether the calendar month before this one was recorded without a gap.
     *
     * A month in progress is only ever complete on its final day, so the
     * FirstFullMonth milestone asks about the month just gone — reachable on any
     * visit in the following month rather than on one day in thirty.
     */
    public function previousMonthWasComplete(User $user, ?CarbonImmutable $today = null): bool
    {
        $today = $today ?? $user->localToday();
        $calendar = FrontendLocalization::normalizeCalendar($user->calendar);

        $end = CalendarDates::monthStart($today, $calendar)->subDay();
        $start = CalendarDates::monthStart($end, $calendar);
        $days = (int) $start->diffInDays($end) + 1;

        return $days > 0 && $this->daysCovered($user, $start, $end) >= $days;
    }

    /**
     * Distinct days with either a transaction or an explicit no-spend marker.
     */
    private function daysCovered(User $user, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $range = [$from->toDateString(), $to->toDateString()];

        $logged = $user->transactions()
            ->whereBetween('occurred_at', $range)
            ->distinct()
            ->pluck('occurred_at')
            ->map(fn ($date): string => $date->toDateString());

        $noSpend = $user->noSpendDays()
            ->whereBetween('date', $range)
            ->pluck('date')
            ->map(fn ($date): string => $date->toDateString());

        return $logged->concat($noSpend)->unique()->count();
    }

    /**
     * Bills reconciled this month so far.
     *
     * Null when the module is off, so the UI can drop the row entirely rather
     * than render a meaningless "0 of 0".
     *
     * @return array{paid: int|null, due: int|null}
     */
    private function bills(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! $user->hasFeature(Feature::Bills)) {
            return ['paid' => null, 'due' => null];
        }

        $occurrences = $user->bills()
            ->join('bill_occurrences', 'bills.id', '=', 'bill_occurrences.bill_id')
            ->whereBetween('bill_occurrences.due_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('count(*) as total, count(bill_occurrences.paid_at) as paid')
            ->first();

        return [
            'paid' => (int) ($occurrences->paid ?? 0),
            'due' => (int) ($occurrences->total ?? 0),
        ];
    }
}
