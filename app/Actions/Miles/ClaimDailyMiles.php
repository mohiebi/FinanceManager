<?php

namespace App\Actions\Miles;

use App\Actions\Gamification\AwardMilestones;
use App\Enums\MilesReason;
use App\Enums\Milestone;
use App\Models\MileDay;
use App\Models\User;
use App\Support\StreakCalculator;
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
            $day = MileDay::query()->firstOrCreate(
                ['user_id' => $user->getKey(), 'local_date' => $date],
                ['timezone' => $user->timezone],
            );

            if ($day->claimed_at !== null) {
                return $day;
            }

            $previous = MileDay::query()
                ->where('user_id', $user->getKey())
                ->whereNotNull('claimed_at')
                ->where('local_date', '<', $date)
                ->latest('local_date')
                ->first();

            $day->forceFill(['claimed_at' => now()])->save();
            ($this->applyStreakProtection)($user, $today);
            $streak = $this->streakCalculator->for($user, $today);
            $continues = $previous instanceof MileDay
                && $streak->currentRun >= abs($today->diffInDays($previous->local_date)) + 1;
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
}
