<?php

namespace App\Support;

use App\Enums\StreakDayState;

/**
 * A user's streak as of one particular local day.
 *
 * @phpstan-type StreakDay array{date: string, label: string, state: string}
 */
final readonly class StreakSummary
{
    /**
     * @param  array<int, array{date: string, label: string, state: StreakDayState}>  $days
     *                                                                                       The tail of the chain, oldest first, ending on today.
     */
    public function __construct(
        public int $currentRun,
        public int $bestRun,
        public bool $loggedToday,
        public int $graceRemainingThisWeek,
        public array $days,
    ) {}

    /**
     * Whether finishing today would set a new personal best.
     *
     * False once the run already is the best, so the UI can promise a new record
     * only when there is genuinely one to win.
     */
    public function todayWouldSetRecord(): bool
    {
        return ! $this->loggedToday && $this->currentRun + 1 > $this->bestRun;
    }

    /**
     * @return array{
     *     current_run: int,
     *     best_run: int,
     *     logged_today: bool,
     *     grace_remaining: int,
     *     today_would_set_record: bool,
     *     days: array<int, StreakDay>,
     * }
     */
    public function toArray(): array
    {
        return [
            'current_run' => $this->currentRun,
            'best_run' => $this->bestRun,
            'logged_today' => $this->loggedToday,
            'grace_remaining' => $this->graceRemainingThisWeek,
            'today_would_set_record' => $this->todayWouldSetRecord(),
            'days' => array_map(fn (array $day): array => [
                'date' => $day['date'],
                'label' => $day['label'],
                'state' => $day['state']->value,
            ], $this->days),
        ];
    }
}
