<?php

namespace App\Actions\Miles;

use App\Actions\Gamification\AwardMilestones;
use App\Enums\MilesReason;
use App\Enums\Milestone;
use App\Models\MileDay;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AwardDailyActivity
{
    /** @var array<int, User|null> */
    private array $users = [];

    public function __construct(
        private AdjustMiles $adjustMiles,
        private AwardMilestones $awardMilestones,
        private ApplyStreakProtection $applyStreakProtection,
        private EvaluateReferralRewards $evaluateReferralRewards,
    ) {}

    public function forUserId(int $userId, string $source): ?MileDay
    {
        $user = $this->users[$userId] ??= User::query()->find($userId);

        return $user instanceof User ? $this($user, $source) : null;
    }

    public function __invoke(User $user, string $source): MileDay
    {
        return DB::transaction(function () use ($user, $source): MileDay {
            $date = $user->localToday()->toDateString();
            $day = MileDay::query()->firstOrCreate(
                ['user_id' => $user->getKey(), 'local_date' => $date],
                ['timezone' => $user->timezone],
            );

            // Evaluated ahead of the daily-bonus guard below. A first bill is
            // a first bill whatever else the day held, and awarding it after
            // the guard tied it to being that day's opening action - so anyone
            // who logged a transaction first thing lost the moment for good.
            // The award is idempotent, so repeating it costs a no-op.
            $milestone = match ($source) {
                'bill' => Milestone::FirstBill,
                'budget' => Milestone::FirstBudget,
                'investment' => Milestone::FirstInvestment,
                'savings_goal' => Milestone::FirstSavingsGoal,
                default => null,
            };

            if ($milestone instanceof Milestone) {
                $this->awardMilestones->award($user, $milestone);
            }

            if ((int) $day->activity_miles > 0) {
                return $day;
            }

            $reward = (int) config('miles.daily_activity_reward');
            ($this->adjustMiles)(
                $user,
                $reward,
                MilesReason::DailyActivity,
                "activity:{$user->getKey()}:{$date}",
                $day,
                ['activity_source' => $source],
            );
            $day->forceFill(['activity_miles' => $reward, 'activity_source' => $source])->save();
            ($this->applyStreakProtection)($user, $user->localToday());
            ($this->evaluateReferralRewards)($user);

            return $day->refresh();
        }, 3);
    }
}
