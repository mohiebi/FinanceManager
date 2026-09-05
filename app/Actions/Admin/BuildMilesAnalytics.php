<?php

namespace App\Actions\Admin;

use App\Enums\MilesReason;
use App\Enums\ReferralStage;
use App\Enums\StreakProtectionType;
use App\Models\MileDay;
use App\Models\MileLedgerEntry;
use App\Models\MileWallet;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\ServiceUsageEvent;
use App\Models\StreakProtection;
use App\Models\User;
use App\Models\UserCosmetic;
use App\Models\UserFeatureUnlock;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class BuildMilesAnalytics
{
    /** @return array<string, mixed> */
    public function __invoke(CarbonInterface $rangeStart): array
    {
        $customerIds = $this->customers()->pluck('id');
        $ledger = MileLedgerEntry::query()
            ->whereIn('user_id', $customerIds)
            ->where('created_at', '>=', $rangeStart)
            ->get(['reason', 'amount', 'transfer_id', 'source_type', 'source_id']);
        $days = MileDay::query()
            ->whereIn('user_id', $customerIds)
            ->where('local_date', '>=', $rangeStart->toDateString())
            ->get();
        $claims = $days->whereNotNull('claimed_at');
        $activityDays = $days->where('activity_miles', '>', 0);

        return [
            'overview' => $this->overview($customerIds, $ledger),
            'retention' => $this->retention($rangeStart),
            'economy' => $this->economy($ledger),
            'engagement' => [
                'claimers' => $claims->pluck('user_id')->unique()->count(),
                'claims' => $claims->count(),
                'average_claims_per_claimer' => $this->average($claims->count(), $claims->pluck('user_id')->unique()->count()),
                'completed_cycles' => $claims->where('claim_step', 7)->count(),
                'activity_days' => $activityDays->count(),
                'average_activity_days_per_active_user' => $this->average($activityDays->count(), $activityDays->pluck('user_id')->unique()->count()),
            ],
            'unlocks' => $this->unlocks($customerIds, $rangeStart),
            'protections' => $this->protections($customerIds, $ledger, $rangeStart),
            'referrals' => $this->referrals($customerIds, $ledger, $rangeStart),
            'advisor' => $this->advisor($customerIds, $ledger, $rangeStart),
        ];
    }

    /** @param Collection<int, int> $customerIds
     * @param  Collection<int, MileLedgerEntry>  $ledger
     * @return array<string, int>
     */
    private function overview(Collection $customerIds, Collection $ledger): array
    {
        return [
            'wallets' => MileWallet::query()->whereIn('user_id', $customerIds)->count(),
            'outstanding' => (int) MileWallet::query()->whereIn('user_id', $customerIds)->sum('balance'),
            'issued' => (int) $ledger
                ->where('amount', '>', 0)
                ->where('reason', '!=', MilesReason::ReferralGiftReceived)
                ->sum('amount'),
            'spent' => abs((int) $ledger
                ->where('amount', '<', 0)
                ->where('reason', '!=', MilesReason::ReferralGiftSent)
                ->sum('amount')),
            'transferred' => abs((int) $ledger->where('reason', MilesReason::ReferralGiftSent)->sum('amount')),
        ];
    }

    /** @return array{labels: array<int, string>, claimed: array<int, float>, never_claimed: array<int, float>} */
    private function retention(CarbonInterface $rangeStart): array
    {
        $labels = ['D1', 'D7', 'D30'];
        $claimed = [];
        $neverClaimed = [];

        foreach ([1, 7, 30] as $day) {
            $eligible = $this->customers()
                ->where('created_at', '>=', $rangeStart)
                ->where('created_at', '<=', now()->subDays($day))
                ->get(['id', 'created_at']);
            $claimedUserIds = MileDay::query()
                ->whereIn('user_id', $eligible->pluck('id'))
                ->whereNotNull('claimed_at')
                ->pluck('user_id')
                ->unique();

            $claimed[] = $this->retainedPercentage($eligible->whereIn('id', $claimedUserIds), $day);
            $neverClaimed[] = $this->retainedPercentage($eligible->whereNotIn('id', $claimedUserIds), $day);
        }

        return ['labels' => $labels, 'claimed' => $claimed, 'never_claimed' => $neverClaimed];
    }

    /** @param Collection<int, User> $users */
    private function retainedPercentage(Collection $users, int $day): float
    {
        if ($users->isEmpty()) {
            return 0;
        }

        $coveredDates = MileDay::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->where(fn (Builder $query): Builder => $query
                ->whereNotNull('claimed_at')
                ->orWhere('activity_miles', '>', 0))
            ->get(['user_id', 'local_date'])
            ->mapWithKeys(fn (MileDay $mileDay): array => ["{$mileDay->user_id}:{$mileDay->local_date->toDateString()}" => true]);
        $retained = $users->filter(function (User $user) use ($coveredDates, $day): bool {
            $target = $user->created_at->copy()->addDays($day)->toDateString();

            return $coveredDates->has("{$user->id}:{$target}");
        })->count();

        return round(($retained / $users->count()) * 100, 1);
    }

    /** @param Collection<int, MileLedgerEntry> $ledger
     * @return array{labels: array<int, string>, issued: array<int, int>, spent: array<int, int>}
     */
    private function economy(Collection $ledger): array
    {
        $grouped = $ledger->groupBy(fn (MileLedgerEntry $entry): string => $entry->reason->value);
        $labels = $grouped->keys()->sort()->values();

        return [
            'labels' => $labels->all(),
            'issued' => $labels->map(fn (string $reason): int => (int) $grouped[$reason]->where('amount', '>', 0)->sum('amount'))->all(),
            'spent' => $labels->map(fn (string $reason): int => abs((int) $grouped[$reason]->where('amount', '<', 0)->sum('amount')))->all(),
        ];
    }

    /** @param Collection<int, int> $customerIds
     * @return array<string, int|float|null>
     */
    private function unlocks(Collection $customerIds, CarbonInterface $rangeStart): array
    {
        $users = $this->customers()->whereIn('id', $customerIds)->get(['id', 'created_at'])->keyBy('id');
        $grouped = UserFeatureUnlock::query()
            ->whereIn('user_id', $customerIds)
            ->where('unlocked_at', '>=', $rangeStart)
            ->orderBy('unlocked_at')
            ->get(['user_id', 'unlocked_at'])
            ->groupBy('user_id');
        $first = [];
        $seventh = [];

        foreach ($grouped as $userId => $unlocks) {
            $createdAt = $users->get($userId)?->created_at;

            if (! $createdAt instanceof CarbonInterface) {
                continue;
            }

            $first[] = $createdAt->diffInHours($unlocks->first()->unlocked_at);

            if ($unlocks->count() >= count((array) config('miles.paid_modules'))) {
                $seventh[] = $createdAt->diffInHours($unlocks->values()->get(count((array) config('miles.paid_modules')) - 1)->unlocked_at);
            }
        }

        return [
            'total' => $grouped->flatten()->count(),
            'median_hours_to_first' => $this->median($first),
            'median_hours_to_seventh' => $this->median($seventh),
        ];
    }

    /** @param Collection<int, int> $customerIds
     * @param  Collection<int, MileLedgerEntry>  $ledger
     * @return array<string, int>
     */
    private function protections(Collection $customerIds, Collection $ledger, CarbonInterface $rangeStart): array
    {
        $protections = StreakProtection::query()
            ->whereIn('user_id', $customerIds)
            ->where('created_at', '>=', $rangeStart)
            ->get();

        return [
            'freeze_purchases' => $ledger->where('reason', MilesReason::StreakFreeze)->count(),
            'freezes_consumed' => $protections->where('type', StreakProtectionType::Freeze)->count(),
            'repair_purchases' => $ledger->where('reason', MilesReason::StreakRepair)->count(),
            'repairs_applied' => $protections->where('type', StreakProtectionType::Repair)->count(),
            'weekly_graces' => $protections->where('type', StreakProtectionType::WeeklyGrace)->count(),
            'cosmetics_purchased' => UserCosmetic::query()
                ->whereIn('user_id', $customerIds)
                ->where('acquired_at', '>=', $rangeStart)
                ->count(),
        ];
    }

    /** @param Collection<int, int> $customerIds
     * @param  Collection<int, MileLedgerEntry>  $ledger
     * @return array<string, int>
     */
    private function referrals(Collection $customerIds, Collection $ledger, CarbonInterface $rangeStart): array
    {
        $referrals = Referral::query()
            ->whereIn('referrer_id', $customerIds)
            ->where('attributed_at', '>=', $rangeStart)
            ->get();
        $rewards = ReferralReward::query()
            ->whereIn('referral_id', $referrals->pluck('id'))
            ->where('awarded_at', '>=', $rangeStart)
            ->get();

        return [
            'signups' => $referrals->count(),
            'activated' => $rewards->where('stage', ReferralStage::Activated)->count(),
            'retained' => $rewards->where('stage', ReferralStage::Retained)->count(),
            'habit' => $rewards->where('stage', ReferralStage::Habit)->count(),
            'review_holds' => $referrals->where('status', 'review')->count(),
            'rejections' => $referrals->where('status', 'rejected')->count(),
            'gifts' => $ledger->where('reason', MilesReason::ReferralGiftSent)->count(),
        ];
    }

    /** @param Collection<int, int> $customerIds
     * @param  Collection<int, MileLedgerEntry>  $ledger
     * @return array<string, int|float|null>
     */
    private function advisor(Collection $customerIds, Collection $ledger, CarbonInterface $rangeStart): array
    {
        $events = ServiceUsageEvent::query()
            ->whereIn('user_id', $customerIds)
            ->where('service', 'advisor')
            ->where('created_at', '>=', $rangeStart)
            ->get();
        $costs = $events->whereNotNull('provider_cost_usd')->pluck('provider_cost_usd')->map(fn ($value): float => (float) $value)->all();
        $latencies = $events->whereNotNull('latency_ms')->pluck('latency_ms')->map(fn ($value): float => (float) $value)->all();
        $advisorLedger = MileLedgerEntry::query()
            ->whereIn('user_id', $customerIds)
            ->whereIn('reason', [MilesReason::AdvisorReservation, MilesReason::AdvisorRefund])
            ->get(['reason', 'amount', 'source_type', 'source_id']);
        $terminalFailures = $events->whereIn('outcome', ['failure', 'failed', 'expired'])->count();

        return [
            'operations' => $events->count(),
            'successful_recommendations' => $events->where('operation', 'recommendation')->where('outcome', 'success')->count(),
            'terminal_failures' => $terminalFailures,
            'terminal_failure_rate' => $events->isNotEmpty() ? round(($terminalFailures / $events->count()) * 100, 1) : 0,
            'provider_cost_p50_usd' => $this->percentile($costs, 50),
            'provider_cost_p95_usd' => $this->percentile($costs, 95),
            'latency_p50_ms' => $this->percentile($latencies, 50),
            'latency_p95_ms' => $this->percentile($latencies, 95),
            'shadow_miles' => (int) $events->sum('shadow_miles'),
            'charged_miles' => (int) $events->sum('charged_miles'),
            'refunded_miles' => (int) $ledger->where('reason', MilesReason::AdvisorRefund)->sum('amount'),
            'reconciliation_mismatches' => $this->advisorReconciliationMismatches($events, $advisorLedger),
        ];
    }

    /** @param Collection<int, ServiceUsageEvent> $events
     * @param  Collection<int, MileLedgerEntry>  $ledger
     */
    private function advisorReconciliationMismatches(Collection $events, Collection $ledger): int
    {
        return $events
            ->where('operation', 'recommendation')
            ->whereNotNull('source_id')
            ->groupBy(fn (ServiceUsageEvent $event): string => "{$event->source_type}:{$event->source_id}")
            ->filter(function (Collection $sourceEvents, string $sourceKey) use ($ledger): bool {
                [$sourceType, $sourceId] = explode(':', $sourceKey, 2);
                $expected = (int) $sourceEvents->max('charged_miles');
                $netDebit = abs((int) $ledger
                    ->where('source_type', $sourceType)
                    ->where('source_id', $sourceId)
                    ->whereIn('reason', [MilesReason::AdvisorReservation, MilesReason::AdvisorRefund])
                    ->sum('amount'));

                return $expected !== $netDebit;
            })
            ->count();
    }

    private function customers(): Builder
    {
        return User::query()->whereRaw('LOWER(email) != ?', [strtolower((string) config('app.admin_email'))]);
    }

    private function average(int $total, int $count): float
    {
        return $count > 0 ? round($total / $count, 1) : 0;
    }

    /** @param array<int, int|float> $values */
    private function median(array $values): float|int|null
    {
        return $this->percentile($values, 50);
    }

    /** @param array<int, int|float> $values */
    private function percentile(array $values, int $percentile): float|int|null
    {
        if ($values === []) {
            return null;
        }

        sort($values, SORT_NUMERIC);
        $index = (int) ceil(($percentile / 100) * count($values)) - 1;
        $value = $values[max(0, $index)];

        return is_float($value) ? round($value, 8) : $value;
    }
}
