<?php

namespace App\Actions\Miles;

use App\Enums\StreakProtectionType;
use App\Models\MileDay;
use App\Models\MileWallet;
use App\Models\StreakProtection;
use App\Models\User;
use App\Models\UserStreak;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ApplyStreakProtection
{
    public function __invoke(User $user, CarbonImmutable $today): void
    {
        DB::transaction(function () use ($user, $today): void {
            $previous = $this->previousCoveredDate($user, $today);

            if (! $previous instanceof CarbonImmutable || $previous->diffInDays($today) <= 1) {
                return;
            }

            $streak = UserStreak::query()->firstOrCreate(['user_id' => $user->getKey()]);
            $grace = $streak->graceDates();
            $missing = [];

            for ($date = $previous->addDay(); $date->lt($today); $date = $date->addDay()) {
                if (! in_array($date->toDateString(), $grace, true)) {
                    $missing[] = $date;
                }
            }

            $weeklyGrace = [];
            $freezes = [];
            $usedWeeks = [];

            foreach ($grace as $date) {
                $parsed = CarbonImmutable::parse($date);
                $usedWeeks[$parsed->isoWeekYear().'-'.$parsed->isoWeek()] = true;
            }

            foreach ($missing as $date) {
                $week = $date->isoWeekYear().'-'.$date->isoWeek();

                if (! isset($usedWeeks[$week])) {
                    $weeklyGrace[] = $date;
                    $usedWeeks[$week] = true;
                } else {
                    $freezes[] = $date;
                }
            }

            MileWallet::query()->firstOrCreate(['user_id' => $user->getKey()]);
            $wallet = MileWallet::query()->where('user_id', $user->getKey())->lockForUpdate()->firstOrFail();

            if (count($freezes) > $wallet->freezes_held) {
                return;
            }

            foreach ($weeklyGrace as $date) {
                $this->protect($user, $date, StreakProtectionType::WeeklyGrace);
                $grace[] = $date->toDateString();
            }

            foreach ($freezes as $date) {
                $this->protect($user, $date, StreakProtectionType::Freeze);
                $grace[] = $date->toDateString();
            }

            if ($freezes !== []) {
                $wallet->decrement('freezes_held', count($freezes));
            }

            $streak->forceFill(['grace_dates' => array_values(array_unique($grace))])->save();
            $user->unsetRelation('streak');
        }, 3);
    }

    private function previousCoveredDate(User $user, CarbonImmutable $today): ?CarbonImmutable
    {
        $date = MileDay::query()
            ->where('user_id', $user->getKey())
            ->where('local_date', '<', $today->toDateString())
            ->where(fn ($query) => $query->whereNotNull('claimed_at')->orWhere('activity_miles', '>', 0))
            ->max('local_date');

        return is_string($date) ? CarbonImmutable::parse($date) : null;
    }

    private function protect(User $user, CarbonImmutable $date, StreakProtectionType $type): void
    {
        StreakProtection::query()->firstOrCreate(
            ['user_id' => $user->getKey(), 'protected_date' => $date->toDateString()],
            ['type' => $type, 'timezone' => $user->resolvedTimezone()],
        );
    }
}
