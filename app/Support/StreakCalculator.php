<?php

namespace App\Support;

use App\Enums\StreakDayState;
use App\Models\User;
use App\Models\UserStreak;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Works out how many days in a row a user has kept their records up to date.
 *
 * The run is derived on every read rather than stored, because two write paths
 * bypass model events entirely — `TransactionController::destroyBulk` deletes
 * through the query builder, and the CSV importer backfills historical dates —
 * so a counter would drift with no way to notice. A walk over the dates cannot
 * drift: delete a transaction and the run simply recomputes shorter.
 *
 * Everything it reads is plaintext (`occurred_at`, `no_spend_days.date`), so the
 * numbers are identical whether or not the user's vault is armed.
 */
class StreakCalculator
{
    /**
     * How far back a single run can reach.
     *
     * Bounds the query and the walk. A run longer than this keeps counting via
     * the stored high-water mark, which is what the UI shows as the best run.
     */
    public const WINDOW_DAYS = 400;

    /** Days of chain history the dashboard card renders. */
    public const CHAIN_DAYS = 14;

    /** Missed days forgiven per ISO week. */
    public const GRACE_PER_WEEK = 1;

    public function for(User $user, ?CarbonImmutable $today = null): StreakSummary
    {
        $today = $today ?? $user->localToday();
        $floor = $this->floorFor($user, $today);

        $covered = $this->coveredDays($user, $floor);
        $streak = $this->streakRecordFor($user);
        $grace = $streak->graceDates();

        [$run, $states, $grace] = $this->walk($covered, $grace, $today, $floor);

        $this->persist($streak, $run, $grace, $today);
        $user->setRelation('streak', $streak);

        return new StreakSummary(
            currentRun: $run,
            bestRun: max($run, (int) $streak->best_run),
            loggedToday: isset($covered[$today->toDateString()]),
            graceRemainingThisWeek: $this->graceRemaining($grace, $today),
            days: $this->chain($user, $states, $today),
        );
    }

    /**
     * Walks backwards from today, counting days that keep the run alive.
     *
     * Today never breaks a run — it is still open until local midnight — so an
     * unrecorded today is skipped rather than counted as a miss. Gaps are
     * forgiven lazily here rather than by a nightly job, which keeps the answer
     * the same for a user whose queue was down, and is self-limiting: one grace
     * a week rescues a single missed day, never a whole quiet week.
     *
     * A forgiven gap stays provisional until a recorded day is found beyond it.
     * Otherwise grace would spend itself on the empty days before a user ever
     * started, reporting a run one longer than they actually kept.
     *
     * @param  array<string, StreakDayState>  $covered
     * @param  array<int, string>  $grace
     * @return array{0: int, 1: array<string, StreakDayState>, 2: array<int, string>}
     */
    private function walk(
        array $covered,
        array $grace,
        CarbonImmutable $today,
        CarbonImmutable $floor,
    ): array {
        // A date that has since been backfilled with a real transaction releases
        // the grace it was holding, so the allowance is not lost to a gap that
        // no longer exists.
        $forgiven = array_flip(array_values(array_filter(
            $grace,
            fn (string $date): bool => ! isset($covered[$date]),
        )));

        $states = [];
        $run = 0;
        $pending = [];
        $cursor = isset($covered[$today->toDateString()]) ? $today : $today->subDay();

        while ($cursor->greaterThanOrEqualTo($floor)) {
            $key = $cursor->toDateString();
            $sustains = isset($covered[$key]) || isset($forgiven[$key]);

            if ($sustains) {
                $states[$key] = $covered[$key] ?? StreakDayState::Grace;
                $run += count($pending) + 1;

                foreach ($pending as $date) {
                    $states[$date] = StreakDayState::Grace;
                    $forgiven[$date] = true;
                }

                $pending = [];
                $cursor = $cursor->subDay();

                continue;
            }

            if ($this->graceSpentIn($this->weekKey($cursor), $forgiven, $pending) >= self::GRACE_PER_WEEK) {
                break;
            }

            $pending[] = $key;
            $cursor = $cursor->subDay();
        }

        return [$run, $states, array_keys($forgiven)];
    }

    /**
     * The dates that already count as recorded, as `Y-m-d` => state.
     *
     * @return array<string, StreakDayState>
     */
    private function coveredDays(User $user, CarbonImmutable $floor): array
    {
        // Activity only, never a claim. Collecting a daily reward takes a tap
        // and no record, so counting it here let the run grow on days the user
        // logged in and wrote nothing - which is the one thing this number is
        // supposed to mean.
        $mileDays = $user->mileDays()
            ->where('local_date', '>=', $floor->toDateString())
            ->where('activity_miles', '>', 0)
            ->pluck('local_date');

        $transactions = $user->transactions()
            ->where('occurred_at', '>=', $floor->toDateString())
            ->distinct()
            ->pluck('occurred_at');

        $noSpend = $user->noSpendDays()
            ->where('date', '>=', $floor->toDateString())
            ->pluck('date');

        $covered = [];

        foreach ($mileDays as $date) {
            $covered[$date->toDateString()] = StreakDayState::Logged;
        }

        // No-spend days are applied second so an explicit "nothing spent" never
        // masks a real transaction recorded on the same date.
        foreach ($noSpend as $date) {
            $covered[$date->toDateString()] = StreakDayState::NoSpend;
        }

        foreach ($transactions as $date) {
            $covered[$date->toDateString()] = StreakDayState::Logged;
        }

        return $covered;
    }

    /**
     * The oldest day a run may reach back to.
     *
     * Clamped at the account's creation date so the walk cannot spend a grace
     * token forgiving a day before the user existed.
     */
    private function floorFor(User $user, CarbonImmutable $today): CarbonImmutable
    {
        $window = $today->subDays(self::WINDOW_DAYS - 1);
        $createdAt = $user->created_at;

        if ($createdAt === null) {
            return $window;
        }

        $signup = CarbonImmutable::parse($createdAt->toDateString());

        return $signup->greaterThan($window) ? $signup : $window;
    }

    /**
     * The last {@see self::CHAIN_DAYS} days, oldest first, for the chain UI.
     *
     * Day labels are rendered here rather than in the browser: the number shown
     * is the day of the user's own calendar month, and jalali day numbers do not
     * line up with gregorian ones.
     *
     * @param  array<string, StreakDayState>  $states
     * @return array<int, array{date: string, label: string, state: StreakDayState}>
     */
    private function chain(User $user, array $states, CarbonImmutable $today): array
    {
        $calendar = FrontendLocalization::normalizeCalendar($user->calendar);
        $days = [];

        for ($offset = self::CHAIN_DAYS - 1; $offset >= 0; $offset--) {
            $date = $today->subDays($offset);
            $key = $date->toDateString();

            $days[] = [
                'date' => $key,
                'label' => DateFormatter::format($date, $calendar, 'j'),
                'state' => $states[$key]
                    ?? ($key === $today->toDateString() ? StreakDayState::Open : StreakDayState::Missed),
            ];
        }

        return $days;
    }

    /**
     * How much of a week's allowance is already committed or provisionally held.
     *
     * @param  array<string, mixed>  $forgiven  keyed by date
     * @param  array<int, string>  $pending
     */
    private function graceSpentIn(string $week, array $forgiven, array $pending): int
    {
        $inWeek = fn (string $date): bool => $this->weekKey(CarbonImmutable::parse($date)) === $week;

        return count(array_filter(array_keys($forgiven), $inWeek))
            + count(array_filter($pending, $inWeek));
    }

    /**
     * @param  array<int, string>  $grace
     */
    private function graceRemaining(array $grace, CarbonImmutable $today): int
    {
        $week = $this->weekKey($today);

        $used = count(array_filter(
            $grace,
            fn (string $date): bool => $this->weekKey(CarbonImmutable::parse($date)) === $week,
        ));

        return max(0, self::GRACE_PER_WEEK - $used);
    }

    /**
     * ISO weeks, so the allowance resets on a fixed boundary in every calendar
     * rather than drifting with the user's locale.
     */
    private function weekKey(CarbonImmutable $date): string
    {
        return $date->isoWeekYear().'-'.$date->isoWeek();
    }

    /**
     * Queries rather than trusting the relation when it resolved to null, because
     * a null `streak` is memoized on the instance — so a second call in the same
     * request would build a second unsaved record and collide on the unique key.
     */
    private function streakRecordFor(User $user): UserStreak
    {
        $loaded = $user->relationLoaded('streak') ? $user->getRelation('streak') : null;

        return $loaded instanceof UserStreak ? $loaded : $user->streak()->firstOrNew([]);
    }

    /**
     * @param  array<int, string>  $grace
     */
    private function persist(UserStreak $streak, int $run, array $grace, CarbonImmutable $today): void
    {
        $grace = $this->pruneGrace($grace, $today);

        $changed = $grace !== $streak->graceDates();

        if ($run > (int) $streak->best_run) {
            $streak->best_run = $run;
            $streak->best_run_ended_on = $today->toDateString();
            $changed = true;
        }

        if (! $changed && $streak->exists) {
            return;
        }

        $streak->grace_dates = $grace;

        try {
            $streak->save();
        } catch (UniqueConstraintViolationException) {
            // The dashboard is a GET, so two parallel loads for a user with no
            // row yet both miss the select and both insert. Losing that race is
            // harmless — the other request wrote the same derived values — and
            // the alternative is a 500 on someone's first visit.
        }
    }

    /**
     * @param  array<int, string>  $grace
     * @return array<int, string>
     */
    private function pruneGrace(array $grace, CarbonImmutable $today): array
    {
        $cutoff = $today->subDays(UserStreak::GRACE_RETENTION_DAYS)->toDateString();

        $grace = array_values(array_unique(array_filter(
            $grace,
            fn (string $date): bool => $date >= $cutoff,
        )));

        sort($grace);

        return $grace;
    }
}
