<?php

namespace App\Actions\Admin;

use App\Enums\MilesReason;
use App\Enums\ReferralStage;
use App\Enums\StreakProtectionType;
use App\Models\AdvisorRecommendation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The Miles economy, summarised for the admin dashboard.
 *
 * Everything is counted in the database rather than in PHP. The ledger is the
 * fastest-growing table in the app, and a dashboard that loads a year of it to
 * count rows stops working long before the economy does.
 */
final class BuildMilesAnalytics
{
    /** Users held in memory at once while walking cohorts. */
    private const CHUNK = 500;

    /** @return array<string, mixed> */
    public function __invoke(CarbonInterface $rangeStart): array
    {
        $ledger = $this->ledgerByReason($rangeStart);

        return [
            'overview' => $this->overview($ledger),
            'retention' => $this->retention($rangeStart),
            'economy' => $this->economy($ledger),
            'engagement' => $this->engagement($rangeStart),
            'unlocks' => $this->unlocks($rangeStart),
            'protections' => $this->protections($ledger, $rangeStart),
            'referrals' => $this->referrals($ledger, $rangeStart),
            'advisor' => $this->advisor($ledger, $rangeStart),
        ];
    }

    /**
     * Every customer's id as a subquery.
     *
     * Returned unexecuted so it composes into a WHERE IN rather than arriving
     * as a list of every id in the table. A fresh builder each call, since
     * reusing one would carry the previous query's bindings.
     */
    private function customerIds(): Builder
    {
        return $this->customers()->select('id');
    }

    private function customers(): Builder
    {
        return User::query()->whereRaw('LOWER(email) != ?', [strtolower((string) config('app.admin_email'))]);
    }

    /**
     * Issued, spent and entry count per reason, in one grouped query.
     *
     * @return Collection<string, object>
     */
    private function ledgerByReason(CarbonInterface $rangeStart): Collection
    {
        return DB::table('mile_ledger_entries')
            ->whereIn('user_id', $this->customerIds())
            ->where('created_at', '>=', $rangeStart)
            ->groupBy('reason')
            ->selectRaw('reason')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as issued')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END), 0) as spent')
            ->selectRaw('COUNT(*) as entries')
            ->get()
            ->keyBy('reason');
    }

    /** @param Collection<string, object> $ledger */
    private function issued(Collection $ledger, MilesReason $reason): int
    {
        return (int) ($ledger->get($reason->value)->issued ?? 0);
    }

    /** @param Collection<string, object> $ledger */
    private function spent(Collection $ledger, MilesReason $reason): int
    {
        return (int) ($ledger->get($reason->value)->spent ?? 0);
    }

    /** @param Collection<string, object> $ledger */
    private function entries(Collection $ledger, MilesReason $reason): int
    {
        return (int) ($ledger->get($reason->value)->entries ?? 0);
    }

    /**
     * @param  Collection<string, object>  $ledger
     * @return array<string, int>
     */
    private function overview(Collection $ledger): array
    {
        $wallets = DB::table('mile_wallets')
            ->whereIn('user_id', $this->customerIds())
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(balance), 0) as outstanding')
            ->first();

        return [
            'wallets' => (int) $wallets->total,
            'outstanding' => (int) $wallets->outstanding,
            // A gift moves Miles rather than making them, so its two halves are
            // reported as transferred instead of issued and spent.
            'issued' => (int) $ledger->sum('issued') - $this->issued($ledger, MilesReason::ReferralGiftReceived),
            'spent' => (int) $ledger->sum('spent') - $this->spent($ledger, MilesReason::ReferralGiftSent),
            'transferred' => $this->spent($ledger, MilesReason::ReferralGiftSent),
        ];
    }

    /**
     * @param  Collection<string, object>  $ledger
     * @return array{labels: array<int, string>, issued: array<int, int>, spent: array<int, int>}
     */
    private function economy(Collection $ledger): array
    {
        $labels = $ledger->keys()->map(fn (mixed $reason): string => (string) $reason)->sort()->values();

        return [
            'labels' => $labels->all(),
            'issued' => $labels->map(fn (string $reason): int => (int) $ledger->get($reason)->issued)->all(),
            'spent' => $labels->map(fn (string $reason): int => (int) $ledger->get($reason)->spent)->all(),
        ];
    }

    /** @return array<string, int|float> */
    private function engagement(CarbonInterface $rangeStart): array
    {
        $totals = DB::table('mile_days')
            ->whereIn('user_id', $this->customerIds())
            ->where('local_date', '>=', $rangeStart->toDateString())
            ->selectRaw('COUNT(CASE WHEN claimed_at IS NOT NULL THEN 1 END) as claims')
            ->selectRaw('COUNT(DISTINCT CASE WHEN claimed_at IS NOT NULL THEN user_id END) as claimers')
            ->selectRaw('COUNT(CASE WHEN claimed_at IS NOT NULL AND claim_step = 7 THEN 1 END) as completed_cycles')
            ->selectRaw('COUNT(CASE WHEN activity_miles > 0 THEN 1 END) as activity_days')
            ->selectRaw('COUNT(DISTINCT CASE WHEN activity_miles > 0 THEN user_id END) as active_users')
            ->first();

        return [
            'claimers' => (int) $totals->claimers,
            'claims' => (int) $totals->claims,
            'average_claims_per_claimer' => $this->average((int) $totals->claims, (int) $totals->claimers),
            'completed_cycles' => (int) $totals->completed_cycles,
            'activity_days' => (int) $totals->activity_days,
            'average_activity_days_per_active_user' => $this->average((int) $totals->activity_days, (int) $totals->active_users),
        ];
    }

    /** @return array{labels: array<int, string>, claimed: array<int, float>, never_claimed: array<int, float>} */
    private function retention(CarbonInterface $rangeStart): array
    {
        $claimed = [];
        $neverClaimed = [];

        foreach ([1, 7, 30] as $day) {
            [$claimed[], $neverClaimed[]] = $this->retentionForDay($day, $rangeStart);
        }

        return ['labels' => ['D1', 'D7', 'D30'], 'claimed' => $claimed, 'never_claimed' => $neverClaimed];
    }

    /**
     * Retention on a given day, split by whether the user ever claimed.
     *
     * Walked in chunks: the target date depends on each user's own timezone, so
     * the comparison cannot be pushed into SQL, but nothing more than a chunk
     * of the cohort is ever held.
     *
     * @return array{0: float, 1: float}
     */
    private function retentionForDay(int $day, CarbonInterface $rangeStart): array
    {
        $totals = ['claimed' => 0, 'never' => 0];
        $retained = ['claimed' => 0, 'never' => 0];

        $this->customers()
            ->where('created_at', '>=', $rangeStart)
            ->where('created_at', '<=', now()->subDays($day))
            ->select(['id', 'created_at', 'timezone'])
            ->chunkById(self::CHUNK, function (Collection $users) use ($day, &$totals, &$retained): void {
                $ids = $users->pluck('id')->all();
                $claimers = DB::table('mile_days')
                    ->whereIn('user_id', $ids)
                    ->whereNotNull('claimed_at')
                    ->distinct()
                    ->pluck('user_id')
                    ->flip();

                // Signup is UTC, mile_days is the user's own date. Comparing
                // them raw marks anyone who signed up near midnight as churned.
                $targets = $users->mapWithKeys(fn (User $user): array => [$user->id => $user->created_at
                    ->copy()
                    ->setTimezone($user->resolvedTimezone())
                    ->addDays($day)
                    ->toDateString()]);

                $covered = DB::table('mile_days')
                    ->whereIn('user_id', $ids)
                    ->whereIn('local_date', $targets->values()->unique()->all())
                    ->where(fn (QueryBuilder $query): QueryBuilder => $query
                        ->whereNotNull('claimed_at')
                        ->orWhere('activity_miles', '>', 0))
                    ->get(['user_id', 'local_date'])
                    ->mapWithKeys(fn (object $row): array => [
                        "{$row->user_id}:".CarbonImmutable::parse($row->local_date)->toDateString() => true,
                    ]);

                foreach ($users as $user) {
                    $bucket = $claimers->has($user->id) ? 'claimed' : 'never';
                    $totals[$bucket]++;

                    if ($covered->has("{$user->id}:{$targets[$user->id]}")) {
                        $retained[$bucket]++;
                    }
                }
            });

        return [
            $this->percentageOf($retained['claimed'], $totals['claimed']),
            $this->percentageOf($retained['never'], $totals['never']),
        ];
    }

    /** @return array<string, int|float|null> */
    private function unlocks(CarbonInterface $rangeStart): array
    {
        $paidModules = count((array) config('miles.paid_modules'));

        // One row per user rather than one per unlock: the medians need a
        // distribution across users, not the unlocks themselves.
        $perUser = DB::table('user_feature_unlocks')
            ->join('users', 'users.id', '=', 'user_feature_unlocks.user_id')
            ->whereIn('user_feature_unlocks.user_id', $this->customerIds())
            ->where('user_feature_unlocks.unlocked_at', '>=', $rangeStart)
            ->groupBy('user_feature_unlocks.user_id', 'users.created_at')
            ->selectRaw('user_feature_unlocks.user_id as user_id')
            ->selectRaw('users.created_at as signed_up_at')
            ->selectRaw('COUNT(*) as unlocks')
            ->selectRaw('MIN(user_feature_unlocks.unlocked_at) as first_unlocked_at')
            ->get();

        $first = $perUser
            ->map(fn (object $row): float => $this->hoursBetween($row->signed_up_at, $row->first_unlocked_at))
            ->all();

        return [
            'total' => (int) $perUser->sum('unlocks'),
            'median_hours_to_first' => $this->median($first),
            'median_hours_to_seventh' => $this->median($this->hoursToNthUnlock($perUser, $paidModules, $rangeStart)),
        ];
    }

    /**
     * Hours from signup to the Nth unlock, for the users who reached it.
     *
     * Only those users' rows are read, which is a small slice of the table -
     * everyone still working through the catalogue is excluded by the count.
     *
     * @param  Collection<int, object>  $perUser
     * @return array<int, float>
     */
    private function hoursToNthUnlock(Collection $perUser, int $nth, CarbonInterface $rangeStart): array
    {
        $completers = $perUser->where('unlocks', '>=', $nth)->keyBy('user_id');

        if ($completers->isEmpty()) {
            return [];
        }

        return DB::table('user_feature_unlocks')
            ->whereIn('user_id', $completers->keys()->all())
            ->where('unlocked_at', '>=', $rangeStart)
            ->orderBy('user_id')
            ->orderBy('unlocked_at')
            ->get(['user_id', 'unlocked_at'])
            ->groupBy('user_id')
            ->map(fn (Collection $rows, int|string $userId): ?float => $rows->count() >= $nth
                ? $this->hoursBetween($completers[$userId]->signed_up_at, $rows->values()->get($nth - 1)->unlocked_at)
                : null)
            ->filter(fn (?float $hours): bool => $hours !== null)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<string, object>  $ledger
     * @return array<string, int>
     */
    private function protections(Collection $ledger, CarbonInterface $rangeStart): array
    {
        $applied = DB::table('streak_protections')
            ->whereIn('user_id', $this->customerIds())
            ->where('created_at', '>=', $rangeStart)
            ->groupBy('type')
            ->selectRaw('type, COUNT(*) as total')
            ->pluck('total', 'type');

        return [
            'freeze_purchases' => $this->entries($ledger, MilesReason::StreakFreeze),
            'freezes_consumed' => (int) ($applied[StreakProtectionType::Freeze->value] ?? 0),
            'repair_purchases' => $this->entries($ledger, MilesReason::StreakRepair),
            'repairs_applied' => (int) ($applied[StreakProtectionType::Repair->value] ?? 0),
            'weekly_graces' => (int) ($applied[StreakProtectionType::WeeklyGrace->value] ?? 0),
            'cosmetics_purchased' => DB::table('user_cosmetics')
                ->whereIn('user_id', $this->customerIds())
                ->where('acquired_at', '>=', $rangeStart)
                ->count(),
        ];
    }

    /**
     * @param  Collection<string, object>  $ledger
     * @return array<string, int>
     */
    private function referrals(Collection $ledger, CarbonInterface $rangeStart): array
    {
        $referrals = DB::table('referrals')
            ->whereIn('referrer_id', $this->customerIds())
            ->where('attributed_at', '>=', $rangeStart)
            ->selectRaw('COUNT(*) as signups')
            ->selectRaw("COUNT(CASE WHEN status = 'review' THEN 1 END) as review_holds")
            ->selectRaw("COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejections")
            ->first();

        $stages = DB::table('referral_rewards')
            ->whereIn('referral_id', $this->referralIds($rangeStart))
            ->where('awarded_at', '>=', $rangeStart)
            ->groupBy('stage')
            ->selectRaw('stage, COUNT(*) as total')
            ->pluck('total', 'stage');

        return [
            'signups' => (int) $referrals->signups,
            'activated' => (int) ($stages[ReferralStage::Activated->value] ?? 0),
            'retained' => (int) ($stages[ReferralStage::Retained->value] ?? 0),
            'habit' => (int) ($stages[ReferralStage::Habit->value] ?? 0),
            'review_holds' => (int) $referrals->review_holds,
            'rejections' => (int) $referrals->rejections,
            'gifts' => $this->entries($ledger, MilesReason::ReferralGiftSent),
        ];
    }

    private function referralIds(CarbonInterface $rangeStart): QueryBuilder
    {
        return DB::table('referrals')
            ->select('id')
            ->whereIn('referrer_id', $this->customerIds())
            ->where('attributed_at', '>=', $rangeStart);
    }

    /**
     * @param  Collection<string, object>  $ledger
     * @return array<string, int|float|null>
     */
    private function advisor(Collection $ledger, CarbonInterface $rangeStart): array
    {
        $totals = $this->advisorEvents($rangeStart)
            ->selectRaw('COUNT(*) as operations')
            ->selectRaw('COALESCE(SUM(shadow_miles), 0) as shadow_miles')
            ->selectRaw('COALESCE(SUM(charged_miles), 0) as charged_miles')
            ->first();
        $outcomes = $this->terminalOutcomes($rangeStart);
        $failures = (int) $outcomes->failures;
        $recommendations = (int) $outcomes->total;

        return [
            'operations' => (int) $totals->operations,
            'recommendations' => $recommendations,
            'successful_recommendations' => (int) $outcomes->successes,
            'terminal_failures' => $failures,
            'terminal_failure_rate' => $this->percentageOf($failures, $recommendations),
            'provider_cost_p50_usd' => $this->percentile($rangeStart, 'provider_cost_usd', 50),
            'provider_cost_p95_usd' => $this->percentile($rangeStart, 'provider_cost_usd', 95),
            'latency_p50_ms' => $this->percentile($rangeStart, 'latency_ms', 50),
            'latency_p95_ms' => $this->percentile($rangeStart, 'latency_ms', 95),
            'shadow_miles' => (int) $totals->shadow_miles,
            'charged_miles' => (int) $totals->charged_miles,
            'refunded_miles' => $this->issued($ledger, MilesReason::AdvisorRefund),
            'reconciliation_mismatches' => $this->advisorReconciliationMismatches($rangeStart),
        ];
    }

    private function advisorEvents(CarbonInterface $rangeStart): QueryBuilder
    {
        return DB::table('service_usage_events')
            ->whereIn('user_id', $this->customerIds())
            ->where('service', 'advisor')
            ->where('created_at', '>=', $rangeStart);
    }

    /** One settled domain outcome per recommendation, counted in the database. */
    private function terminalOutcomes(CarbonInterface $rangeStart): object
    {
        return DB::table('advisor_recommendations')
            ->whereIn('user_id', $this->customerIds())
            ->where('miles_settled_at', '>=', $rangeStart)
            ->whereIn('miles_outcome', ['full', 'guidance', 'failure'])
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COUNT(CASE WHEN miles_outcome IN ('full', 'guidance') THEN 1 END) as successes")
            ->selectRaw("COUNT(CASE WHEN miles_outcome = 'failure' THEN 1 END) as failures")
            ->first();
    }

    /**
     * Recommendations whose charged Miles do not match what the ledger moved.
     *
     * Both sides are grouped in the database and compared per recommendation,
     * which is the cardinality this metric is about.
     */
    private function advisorReconciliationMismatches(CarbonInterface $rangeStart): int
    {
        $sourceType = (new AdvisorRecommendation)->getMorphClass();
        $expected = DB::table('advisor_recommendations')
            ->whereIn('user_id', $this->customerIds())
            ->where('miles_settled_at', '>=', $rangeStart)
            ->selectRaw('? as source_type, id as source_id, charged_miles as expected', [$sourceType])
            ->get()
            ->keyBy(fn (object $row): string => "{$row->source_type}:{$row->source_id}");

        if ($expected->isEmpty()) {
            return 0;
        }

        $moved = DB::table('mile_ledger_entries')
            ->whereIn('user_id', $this->customerIds())
            ->whereIn('reason', [MilesReason::AdvisorReservation->value, MilesReason::AdvisorRefund->value])
            ->whereNotNull('source_id')
            ->groupBy('source_type', 'source_id')
            ->selectRaw('source_type, source_id')
            ->selectRaw('COALESCE(SUM(amount), 0) as net')
            ->get()
            ->keyBy(fn (object $row): string => "{$row->source_type}:{$row->source_id}");

        return $expected
            ->filter(fn (object $row, string $key): bool => (int) $row->expected !== abs((int) ($moved->get($key)->net ?? 0)))
            ->count();
    }

    /**
     * A percentile read by offset rather than by sorting the column in memory.
     */
    private function percentile(CarbonInterface $rangeStart, string $column, int $percentile): float|int|null
    {
        $count = $this->advisorEvents($rangeStart)->whereNotNull($column)->count();

        if ($count === 0) {
            return null;
        }

        $value = $this->advisorEvents($rangeStart)
            ->whereNotNull($column)
            ->orderBy($column)
            ->offset(max(0, (int) ceil(($percentile / 100) * $count) - 1))
            ->limit(1)
            ->value($column);

        return $value === null ? null : round((float) $value, 8);
    }

    /** @param array<int, int|float> $values */
    private function median(array $values): float|int|null
    {
        if ($values === []) {
            return null;
        }

        sort($values, SORT_NUMERIC);

        return round((float) $values[max(0, (int) ceil(0.5 * count($values)) - 1)], 8);
    }

    private function hoursBetween(mixed $from, mixed $to): float
    {
        return (float) CarbonImmutable::parse((string) $from)->diffInHours(CarbonImmutable::parse((string) $to));
    }

    private function average(int $total, int $count): float
    {
        return $count > 0 ? round($total / $count, 1) : 0;
    }

    private function percentageOf(int $part, int $whole): float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 1) : 0;
    }
}
