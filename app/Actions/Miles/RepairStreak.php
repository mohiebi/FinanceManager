<?php

namespace App\Actions\Miles;

use App\Enums\MilesReason;
use App\Enums\StreakProtectionType;
use App\Models\StreakProtection;
use App\Models\User;
use App\Models\UserStreak;
use App\Support\StreakCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RepairStreak
{
    public function __construct(
        private AdjustMiles $adjustMiles,
        private StreakCalculator $streakCalculator,
    ) {}

    public function __invoke(User $user, CarbonImmutable $date): StreakProtection
    {
        return DB::transaction(function () use ($user, $date): StreakProtection {
            $today = $user->localToday();
            $key = $date->toDateString();

            if (! $date->lt($today) || $date->lt($today->subDays((int) config('miles.streak_repair_days')))) {
                throw ValidationException::withMessages(['date' => __('Choose a missed date from the previous seven days.')]);
            }

            if (StreakProtection::query()->where('user_id', $user->getKey())->where('protected_date', $key)->exists()) {
                throw ValidationException::withMessages(['date' => __('That date is already protected.')]);
            }

            $repairsThisMonth = StreakProtection::query()
                ->where('user_id', $user->getKey())
                ->where('type', StreakProtectionType::Repair)
                ->whereBetween('created_at', [$today->startOfMonth(), $today->endOfMonth()])
                ->count();

            if ($repairsThisMonth >= (int) config('miles.streak_repairs_per_month')) {
                throw ValidationException::withMessages(['date' => __('You have used this month\'s Streak Repairs.')]);
            }

            $before = $this->streakCalculator->for($user, $today)->currentRun;
            $streak = UserStreak::query()->firstOrCreate(['user_id' => $user->getKey()]);
            $grace = $streak->graceDates();
            $grace[] = $key;
            $streak->forceFill(['grace_dates' => array_values(array_unique($grace))])->save();
            $user->unsetRelation('streak');
            $after = $this->streakCalculator->for($user, $today)->currentRun;

            if ($after <= $before) {
                throw ValidationException::withMessages(['date' => __('Repair every missing date needed to reconnect your current streak.')]);
            }

            $entry = ($this->adjustMiles)(
                $user,
                -((int) config('miles.streak_repair_price')),
                MilesReason::StreakRepair,
                "streak-repair:{$user->getKey()}:{$key}",
                action: 'streak_repair',
            );

            return StreakProtection::query()->create([
                'user_id' => $user->getKey(),
                'protected_date' => $key,
                'type' => StreakProtectionType::Repair,
                'mile_ledger_entry_id' => $entry->getKey(),
                'timezone' => $user->resolvedTimezone(),
            ]);
        }, 3);
    }
}
