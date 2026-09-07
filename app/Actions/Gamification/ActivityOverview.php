<?php

namespace App\Actions\Gamification;

use App\Enums\Milestone;
use App\Enums\PilotRank;
use App\Models\User;
use App\Support\LogbookCompleteness;
use App\Support\StreakCalculator;

final class ActivityOverview
{
    /** @return array<string, mixed> */
    public function __invoke(User $user): array
    {
        $completeness = app(LogbookCompleteness::class);
        $logbook = $completeness->for($user);

        return [
            'streak' => app(StreakCalculator::class)->for($user)->toArray(),
            'logbook' => $logbook,
            'ranks' => $this->ranks($logbook['days_logged']),
            'moments' => $this->moments($user, $completeness),
        ];
    }

    /**
     * Every rank, in order, with where the user stands against it.
     *
     * The whole ladder is shown rather than just the current rung: knowing
     * Captain is 180 days is what makes Pilot mean something, and a rank you
     * cannot see the shape of is just a badge.
     *
     * @return list<array{key: string, threshold: int, state: string, days_away: int|null}>
     */
    private function ranks(int $daysLogged): array
    {
        $current = PilotRank::forDaysLogged($daysLogged);

        return array_map(function (PilotRank $rank) use ($current, $daysLogged): array {
            return [
                'key' => $rank->value,
                'threshold' => $rank->threshold(),
                'state' => match (true) {
                    $rank === $current => 'current',
                    $rank->threshold() < $current->threshold() => 'passed',
                    default => 'upcoming',
                },
                // Only meaningful for a rank still ahead of the user.
                'days_away' => $rank->threshold() > $daysLogged
                    ? $rank->threshold() - $daysLogged
                    : null,
            ];
        }, PilotRank::cases());
    }

    /**
     * The one-time moments, earned or not.
     *
     * Unearned ones are listed too — a moment nobody can see coming is not
     * something to work toward. The only hint carried is for the complete
     * month, because it is the one a user can miss by a day and never know.
     *
     * @return list<array{key: string, achieved_at: string|null, missed: array{month: string, days: int}|null}>
     */
    private function moments(User $user, LogbookCompleteness $completeness): array
    {
        $achieved = $user->milestones()
            ->get()
            ->keyBy(fn ($milestone): string => $milestone->key->value);

        return array_map(function (Milestone $milestone) use ($achieved, $user, $completeness): array {
            $earned = $achieved->get($milestone->value);

            return [
                'key' => $milestone->value,
                'achieved_at' => $earned?->achieved_at?->toDateString(),
                'missed' => $earned === null && $milestone === Milestone::FirstFullMonth
                    ? $completeness->previousMonthShortfall($user)
                    : null,
            ];
        }, Milestone::cases());
    }
}
