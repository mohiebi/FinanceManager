<?php

namespace App\Actions\Miles;

use App\Enums\Feature;
use App\Enums\Milestone;
use App\Enums\StreakProtectionType;
use App\Models\MileDay;
use App\Models\MileWallet;
use App\Models\StreakProtection;
use App\Models\User;
use App\Support\StreakCalculator;
use Carbon\CarbonImmutable;

final readonly class MilesOverview
{
    public function __construct(private StreakCalculator $streakCalculator) {}

    /** @return array<string, mixed> */
    public function __invoke(User $user): array
    {
        $today = $user->localToday();
        $wallet = MileWallet::query()->firstOrCreate(['user_id' => $user->getKey()]);
        $todayDay = MileDay::query()
            ->where('user_id', $user->getKey())
            ->where('local_date', $today->toDateString())
            ->first();
        $previousClaim = MileDay::query()
            ->where('user_id', $user->getKey())
            ->whereNotNull('claimed_at')
            ->latest('local_date')
            ->first();
        $streak = $this->streakCalculator->for($user, $today);
        $claimStep = $this->nextClaimStep($previousClaim, $todayDay, $streak->currentRun, $today->toDateString());
        $lastCollectedStep = $todayDay?->claimed_at !== null
            ? (int) $todayDay->claim_step
            : ($claimStep === 1 ? 0 : $claimStep - 1);
        $completedCycles = MileDay::query()
            ->where('user_id', $user->getKey())
            ->where('claim_step', 7)
            ->count();
        $earnedMilestones = $user->milestones()->get()->mapWithKeys(
            fn ($milestone): array => [$milestone->key->value => $milestone->achieved_at?->toIso8601String()],
        );
        $ownedCosmetics = $user->cosmetics()->get()->keyBy('key');

        return [
            'balance' => (int) $wallet->balance,
            'lifetimeEarned' => (int) $wallet->lifetime_earned,
            'lifetimeSpent' => (int) $wallet->lifetime_spent,
            'todayClaimed' => $todayDay?->claimed_at !== null,
            'todayActivityAwarded' => (int) ($todayDay?->activity_miles ?? 0) > 0,
            'claimable' => $todayDay?->claimed_at === null,
            'nextClaimReward' => (int) config('miles.daily_claims.'.($claimStep - 1)),
            'claimStep' => $claimStep,
            'claimProgress' => $lastCollectedStep,
            'freezesHeld' => (int) $wallet->freezes_held,
            'freezesMaximum' => (int) config('miles.streak_freeze_maximum'),
            'hubUrl' => route('miles.index'),
            'referralCode' => $wallet->referral_code,
            'referralUrl' => route('invite', ['code' => $wallet->referral_code]),
            'streak' => $streak->toArray(),
            'completedCycles' => $completedCycles,
            'badgeTier' => $this->badgeTier($completedCycles),
            'claimCycle' => collect(config('miles.daily_claims'))->map(
                fn (int $reward, int $index): array => [
                    'step' => $index + 1,
                    'reward' => $reward,
                    'collected' => $index + 1 <= $lastCollectedStep,
                    'next' => $index + 1 === $claimStep && $todayDay?->claimed_at === null,
                ],
            )->all(),
            'protections' => [
                'freezePrice' => (int) config('miles.streak_freeze_price'),
                'repairPrice' => (int) config('miles.streak_repair_price'),
                'repairsPerMonth' => (int) config('miles.streak_repairs_per_month'),
                'repairsRemaining' => $this->repairsRemaining($user),
                'repairWindowDays' => (int) config('miles.streak_repair_days'),
                'repairableDates' => $this->repairableDates($user),
            ],
            'modules' => $this->modules($user),
            'milestones' => collect(Milestone::cases())->map(fn (Milestone $milestone): array => [
                'key' => $milestone->value,
                'miles' => $milestone->miles(),
                'achievedAt' => $earnedMilestones->get($milestone->value),
            ])->all(),
            'cosmetics' => collect(config('miles.cosmetics'))->map(
                fn (array $cosmetic, string $key): array => [
                    'key' => $key,
                    ...$cosmetic,
                    'owned' => $ownedCosmetics->has($key),
                    'selected' => (bool) $ownedCosmetics->get($key)?->selected,
                ],
            )->values()->all(),
            'cosmeticsEnabled' => (bool) config('miles.cosmetics_enabled'),
            'giftingEnabled' => (bool) config('miles.gifting_enabled'),
            'referrals' => $user->referrals()
                ->with('referredUser:id,name,email')
                ->where('status', 'active')
                ->get()
                ->map(fn ($referral): array => [
                    'id' => $referral->id,
                    'userId' => $referral->referred_user_id,
                    'name' => $referral->referredUser->name,
                ])->all(),
        ];
    }

    private function nextClaimStep(?MileDay $previous, ?MileDay $todayDay, int $currentRun, string $today): int
    {
        if ($todayDay?->claimed_at !== null) {
            return (int) $todayDay->claim_step;
        }

        if (! $previous instanceof MileDay) {
            return 1;
        }

        $distance = (int) abs($previous->local_date->diffInDays($today));
        $requiredRun = $todayDay !== null && (int) $todayDay->activity_miles > 0 ? $distance + 1 : $distance;

        return $currentRun >= $requiredRun ? (((int) $previous->claim_step % 7) + 1) : 1;
    }

    /**
     * The paid modules and whether each one is currently switched on.
     *
     * Read from the live feature state rather than the unlock ledger: a module
     * grandfathered in before Miles existed carries no unlock row, so the ledger
     * would offer to sell someone a module they are already using.
     *
     * @return array<int, array{key: string, label: string, price: int, enabled: bool}>
     */
    private function modules(User $user): array
    {
        return collect(config('miles.paid_modules'))
            ->map(function (string $key) use ($user): array {
                $feature = Feature::from($key);

                return [
                    'key' => $key,
                    'label' => $feature->label(),
                    'price' => (int) config('miles.unlock_price'),
                    'enabled' => $user->hasFeature($feature),
                ];
            })
            ->all();
    }

    /**
     * The days a repair could actually mend, newest first.
     *
     * Built from the covered dates rather than the streak chain: the chain
     * renders everything beyond a break as missed, records included, so it
     * would offer days that need no mending and RepairStreak would refuse
     * after taking the click.
     *
     * @return array<int, array{date: string, daysAgo: int}>
     */
    private function repairableDates(User $user): array
    {
        $today = $user->localToday();
        $window = (int) config('miles.streak_repair_days');
        $floor = $today->subDays($window);

        $covered = $user->transactions()
            ->where('occurred_at', '>=', $floor->toDateString())
            ->distinct()
            ->pluck('occurred_at')
            ->concat($user->noSpendDays()->where('date', '>=', $floor->toDateString())->pluck('date'))
            ->concat($user->mileDays()
                ->where('local_date', '>=', $floor->toDateString())
                ->where('activity_miles', '>', 0)
                ->pluck('local_date'))
            ->map(fn ($date): string => CarbonImmutable::parse($date)->toDateString())
            ->flip();

        $protected = StreakProtection::query()
            ->where('user_id', $user->getKey())
            ->where('protected_date', '>=', $floor->toDateString())
            ->pluck('protected_date')
            ->map(fn ($date): string => CarbonImmutable::parse($date)->toDateString())
            ->flip();

        $repairable = [];

        // Today is never offered: it is still open, not yet missed.
        for ($daysAgo = 1; $daysAgo <= $window; $daysAgo++) {
            $date = $today->subDays($daysAgo)->toDateString();

            if (! $covered->has($date) && ! $protected->has($date)) {
                $repairable[] = ['date' => $date, 'daysAgo' => $daysAgo];
            }
        }

        return $repairable;
    }

    /** Repairs left in the user's own calendar month, which is what the cap counts. */
    private function repairsRemaining(User $user): int
    {
        $today = $user->localToday();
        $used = StreakProtection::query()
            ->where('user_id', $user->getKey())
            ->where('type', StreakProtectionType::Repair)
            ->whereBetween('created_at', [$today->startOfMonth(), $today->endOfMonth()])
            ->count();

        return max(0, (int) config('miles.streak_repairs_per_month') - $used);
    }

    private function badgeTier(int $cycles): ?string
    {
        return match (true) {
            $cycles >= 52 => 'legend',
            $cycles >= 12 => 'captain',
            $cycles >= 4 => 'pilot',
            $cycles >= 1 => 'first_flight',
            default => null,
        };
    }
}
