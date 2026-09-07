<?php

namespace App\Http\Controllers;

use App\Actions\Gamification\AwardMilestones;
use App\Enums\Milestone;
use App\Enums\PilotRank;
use App\Models\User;
use App\Support\LogbookCompleteness;
use App\Support\StreakCalculator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The flight log, on a page of its own.
 *
 * It used to be two cards on the dashboard and was dropped when that page was
 * rebuilt to the v3 mock, which left the module switchable but invisible: the
 * streak and completeness were still computed on every dashboard load and then
 * thrown away. This gives them somewhere to live, and the dashboard keeps only
 * the one-line strip that links here.
 *
 * Every figure on this page is about the record — days logged, months covered,
 * moments passed. None of it is about how much money moved, which is the whole
 * reason a rank here is safe to show.
 */
class FlightLogController extends Controller
{
    public function __invoke(
        Request $request,
        StreakCalculator $streakCalculator,
        LogbookCompleteness $completeness,
        AwardMilestones $awardMilestones,
    ): Response {
        $user = $request->user();
        $awardMilestones->afterMilestoneScan($user);
        $logbook = $completeness->for($user);

        return Inertia::render('FlightLog', [
            'streak' => $streakCalculator->for($user)->toArray(),
            'logbook' => $logbook,
            'ranks' => $this->ranks($logbook['days_logged']),
            'moments' => $this->moments($user, $completeness),
        ]);
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
