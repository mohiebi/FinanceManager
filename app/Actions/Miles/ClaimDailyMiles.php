<?php

namespace App\Actions\Miles;

use App\Actions\Gamification\AwardMilestones;
use App\Enums\MilesReason;
use App\Enums\Milestone;
use App\Models\MileDay;
use App\Models\User;
use App\Support\StreakCalculator;
use App\Support\StreakSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class ClaimDailyMiles
{
    public function __construct(
        private AdjustMiles $adjustMiles,
        private ApplyStreakProtection $applyStreakProtection,
        private StreakCalculator $streakCalculator,
        private AwardMilestones $awardMilestones,
    ) {}

    public function __invoke(User $user): MileDay
    {
        return DB::transaction(function () use ($user): MileDay {
            $today = $user->localToday();
            $date = $today->toDateString();

            $previous = MileDay::query()
                ->where('user_id', $user->getKey())
                ->whereNotNull('claimed_at')
                ->latest('local_date')
                ->first();

            // Compared against the newest claim, not just this date's row: a
            // westward timezone change rewinds localToday() onto an earlier
            // date the unique key cannot catch.
            if ($previous instanceof MileDay && $previous->local_date->toDateString() >= $date) {
                return $previous;
            }

            $day = MileDay::query()->firstOrCreate(
                ['user_id' => $user->getKey(), 'local_date' => $date],
                ['timezone' => $user->timezone],
            );

            // Losing the race to a concurrent claim on this same date.
            if ($day->claimed_at !== null) {
                return $day;
            }

            $day->forceFill(['claimed_at' => now()])->save();
            ($this->applyStreakProtection)($user, $today);
            $streak = $this->streakCalculator->for($user, $today);
            $continues = $previous instanceof MileDay && $this->cycleContinues($streak, $previous, $today);
            $step = $continues ? (((int) $previous->claim_step % 7) + 1) : 1;
            $reward = (int) config('miles.daily_claims.'.($step - 1));

            $day->forceFill(['claim_step' => $step, 'claim_miles' => $reward])->save();
            ($this->adjustMiles)(
                $user,
                $reward,
                MilesReason::DailyClaim,
                "claim:{$user->getKey()}:{$date}",
                $day,
                ['claim_step' => $step],
            );

            if ($streak->currentRun >= 3) {
                $this->awardMilestones->award($user, Milestone::ThreeDayRun);
            }
            if ($step === 7) {
                $this->awardMilestones->award($user, Milestone::FirstFlightWeek);
            }
            if ($streak->currentRun >= 14) {
                $this->awardMilestones->award($user, Milestone::FourteenDayRun);
            }
            if ($streak->currentRun >= 30) {
                $this->awardMilestones->award($user, Milestone::ThirtyDayRun);
            }

            return $day->refresh();
        }, 3);
    }

    /**
     * Whether this claim carries on the previous one's cycle.
     *
     * Every day since the last claim has to have held something: a record, or
     * the grace or freeze that stands in for one. Recorded days count so that
     * forgetting to tap for a day or two never costs a cycle the user was
     * plainly active through, and a day with neither breaks it.
     */
    private function cycleContinues(StreakSummary $streak, MileDay $previous, CarbonImmutable $today): bool
    {
        $sustained = [];

        foreach ($streak->days as $day) {
            $sustained[$day['date']] = $day['state']->sustainsRun();
        }

        $cursor = CarbonImmutable::parse($previous->local_date->toDateString())->addDay();

        while ($cursor->lt($today)) {
            if (($sustained[$cursor->toDateString()] ?? false) === false) {
                return false;
            }

            $cursor = $cursor->addDay();
        }

        return true;
    }
}
