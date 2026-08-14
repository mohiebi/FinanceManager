<?php

namespace App\Actions\Admin;

use App\Models\DailyStat;
use App\Models\SocialAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Admin\McpTokenQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class BuildAdminAnalytics
{
    /**
     * Supported analysis ranges. The first entry is the default.
     *
     * @var array<int, string>
     */
    public const RANGES = ['12m', '30d', '90d'];

    private const CACHE_MINUTES = 5;

    /** @var array<int, array<string, int|float|null>> */
    private array $cohortCache = [];

    /**
     * @return array<string, int|float|null>
     */
    public function summary(string $range = '12m'): array
    {
        return Cache::remember(
            "admin-analytics:summary:{$range}",
            now()->addMinutes(self::CACHE_MINUTES),
            fn (): array => $this->buildSummary($range),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function charts(string $range = '12m'): array
    {
        return Cache::remember(
            "admin-analytics:charts:{$range}",
            now()->addMinutes(self::CACHE_MINUTES),
            fn (): array => $this->buildCharts($range),
        );
    }

    /**
     * Column values persisted by the daily snapshot job.
     *
     * @return array<string, int>
     */
    public function snapshot(): array
    {
        $now = now();

        return [
            'total_customers' => $this->customers()->count(),
            'new_customers' => $this->customers()
                ->whereBetween('created_at', [$now->copy()->subDay(), $now])
                ->count(),
            'active_customers_7d' => $this->customers()
                ->where('last_active_at', '>=', $now->copy()->subDays(7))
                ->count(),
            'active_customers_30d' => $this->customers()
                ->where('last_active_at', '>=', $now->copy()->subDays(30))
                ->count(),
            'telegram_customers' => $this->customers()->whereNotNull('telegram_chat_id')->count(),
            // Captured daily so the period-over-period deltas above have a
            // baseline; without a stored history they could only ever read null.
            'pro_customers' => $this->customers()->where('pro_until', '>', $now)->count(),
            'mcp_customers' => $this->customers()->tap(McpTokenQuery::connected(...))->count(),
            'verified_customers' => $this->customers()->whereNotNull('email_verified_at')->count(),
            'completed_profiles' => $this->customers()->whereNotNull('birthdate')->count(),
        ];
    }

    /**
     * @return array<string, int|float|null>
     */
    private function buildSummary(string $range): array
    {
        $now = now();
        $rangeStart = $this->rangeStart($range, $now);
        $previousRangeStart = $this->previousRangeStart($range, $now);
        $totalCustomers = $this->customers()->count();
        $newCustomers = $this->customers()
            ->where('created_at', '>=', $rangeStart)
            ->count();
        $previousCustomers = $this->customers()
            ->where('created_at', '>=', $previousRangeStart)
            ->where('created_at', '<', $rangeStart)
            ->count();
        $newLast30 = $this->customers()
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->count();
        $activeCustomers7d = $this->customers()
            ->where('last_active_at', '>=', $now->copy()->subDays(7))
            ->count();
        $activeCustomers30d = $this->customers()
            ->where('last_active_at', '>=', $now->copy()->subDays(30))
            ->count();
        $telegramCustomers = $this->customers()->whereNotNull('telegram_chat_id')->count();
        $proCustomers = $this->customers()->where('pro_until', '>', $now)->count();
        $mcpCustomers = $this->customers()->tap(McpTokenQuery::connected(...))->count();
        $verifiedCustomers = $this->customers()->whereNotNull('email_verified_at')->count();
        $completedProfiles = $this->customers()->whereNotNull('birthdate')->count();
        $cohort = $this->signupCohort($rangeStart);
        $baseline = $this->baselineSnapshot($now);

        return [
            'total_customers' => $totalCustomers,
            'total_customers_change' => $this->percentChange($totalCustomers, $totalCustomers - $newLast30),
            'new_customers' => $newCustomers,
            'new_customers_change' => $this->percentChange($newCustomers, $previousCustomers),
            'online_customers' => $this->customers()
                ->where('last_active_at', '>=', $now->copy()->subMinutes(15))
                ->count(),
            'active_customers_7d' => $activeCustomers7d,
            'active_customers_30d' => $activeCustomers30d,
            'active_customers_30d_change' => $baseline instanceof DailyStat
                ? $this->percentChange($activeCustomers30d, $baseline->active_customers_30d)
                : null,
            'stickiness' => $activeCustomers30d > 0
                ? round(($activeCustomers7d / $activeCustomers30d) * 100, 1)
                : null,
            'telegram_customers' => $telegramCustomers,
            'telegram_adoption' => $this->percentage($telegramCustomers, $totalCustomers),
            'telegram_customers_change' => $baseline instanceof DailyStat
                ? $this->percentChange($telegramCustomers, $baseline->telegram_customers)
                : null,
            // Scoped to customers(), which excludes the configured admin — so
            // this figure is deliberately narrower than the one on the billing
            // console, which counts every Pro account including the operator's.
            'pro_customers' => $proCustomers,
            'pro_adoption' => $this->percentage($proCustomers, $totalCustomers),
            'pro_customers_change' => $baseline instanceof DailyStat
                ? $this->percentChange($proCustomers, $baseline->pro_customers)
                : null,
            'mcp_customers' => $mcpCustomers,
            'mcp_adoption' => $this->percentage($mcpCustomers, $totalCustomers),
            'mcp_customers_change' => $baseline instanceof DailyStat
                ? $this->percentChange($mcpCustomers, $baseline->mcp_customers)
                : null,
            'verified_customers' => $verifiedCustomers,
            'verification_rate' => $this->percentage($verifiedCustomers, $totalCustomers),
            'verified_customers_change' => $baseline instanceof DailyStat
                ? $this->percentChange($verifiedCustomers, $baseline->verified_customers)
                : null,
            'completed_profiles' => $completedProfiles,
            'profile_completion_rate' => $this->percentage($completedProfiles, $totalCustomers),
            'activated_customers' => $cohort['activated'],
            'activation_rate' => $cohort['signups'] > 0
                ? $this->percentage($cohort['activated'], $cohort['signups'])
                : null,
            'median_days_to_first_transaction' => $cohort['median_days_to_first_transaction'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCharts(string $range): array
    {
        return [
            'growth' => $this->growth($range),
            'funnel' => $this->funnel($range),
            'acquisition' => $this->acquisition($range),
            'product_adoption' => $this->productAdoption(),
            'authentication_mix' => $this->authenticationMix(),
            'locales' => $this->locales(),
            'retention_segments' => $this->retentionSegments(),
            'engagement_trend' => $this->engagementTrend($range),
        ];
    }

    /**
     * @return array{labels: array<int, string>, new_customers: array<int, int>, cumulative_customers: array<int, int>}
     */
    private function growth(string $range): array
    {
        [$bucketStarts, $bucketKey, $labelFormat] = $this->growthBuckets($range);
        $windowStart = $bucketStarts[0];
        $cumulative = $this->customers()->where('created_at', '<', $windowStart)->count();
        $counts = [];

        $this->customers()
            ->where('created_at', '>=', $windowStart)
            ->toBase()
            ->pluck('created_at')
            ->each(function ($createdAt) use (&$counts, $bucketKey): void {
                $key = $bucketKey(Carbon::parse($createdAt));
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            });

        $labels = [];
        $newCustomers = [];
        $cumulativeCustomers = [];

        foreach ($bucketStarts as $bucketStart) {
            $count = $counts[$bucketKey($bucketStart)] ?? 0;
            $cumulative += $count;
            $labels[] = $bucketStart->format($labelFormat);
            $newCustomers[] = $count;
            $cumulativeCustomers[] = $cumulative;
        }

        return [
            'labels' => $labels,
            'new_customers' => $newCustomers,
            'cumulative_customers' => $cumulativeCustomers,
        ];
    }

    /**
     * @return array{0: array<int, CarbonInterface>, 1: callable(CarbonInterface): string, 2: string}
     */
    private function growthBuckets(string $range): array
    {
        $today = now()->startOfDay();

        return match ($range) {
            '30d' => [
                collect(range(29, 0))->map(fn (int $daysAgo): CarbonInterface => $today->copy()->subDays($daysAgo))->all(),
                fn (CarbonInterface $date): string => $date->format('Y-m-d'),
                'M j',
            ],
            '90d' => [
                collect(range(12, 0))->map(fn (int $weeksAgo): CarbonInterface => $today->copy()->startOfWeek()->subWeeks($weeksAgo))->all(),
                fn (CarbonInterface $date): string => $date->copy()->startOfWeek()->format('Y-m-d'),
                'M j',
            ],
            default => [
                collect(range(11, 0))->map(fn (int $monthsAgo): CarbonInterface => $today->copy()->startOfMonth()->subMonths($monthsAgo))->all(),
                fn (CarbonInterface $date): string => $date->format('Y-m'),
                'M Y',
            ],
        };
    }

    /**
     * Signup-to-value funnel for customers who joined within the range.
     *
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function funnel(string $range): array
    {
        $cohort = $this->signupCohort($this->rangeStart($range, now()));

        return [
            'labels' => [
                'Signed up',
                'Verified email',
                'Completed profile',
                'First transaction',
                'Active after first week',
            ],
            'values' => [
                $cohort['signups'],
                $cohort['verified'],
                $cohort['profiled'],
                $cohort['transacted'],
                $cohort['returned'],
            ],
        ];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function acquisition(string $range): array
    {
        $counts = $this->customers()
            ->where('created_at', '>=', $this->rangeStart($range, now()))
            ->selectRaw("COALESCE(NULLIF(TRIM(signup_source), ''), 'direct') as source, COUNT(*) as aggregate")
            ->groupBy('source')
            ->orderByDesc('aggregate')
            ->pluck('aggregate', 'source');

        $labels = [];
        $values = [];
        $other = 0;

        foreach ($counts as $source => $aggregate) {
            if (count($labels) < 5) {
                $labels[] = $source === 'direct' ? 'Direct / unknown' : (string) $source;
                $values[] = (int) $aggregate;

                continue;
            }

            $other += (int) $aggregate;
        }

        if ($other > 0) {
            $labels[] = 'Other';
            $values[] = $other;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function productAdoption(): array
    {
        return [
            'labels' => ['Transactions', 'Investments', 'Bills', 'Telegram'],
            'values' => [
                $this->customers()->whereHas('transactions')->count(),
                $this->customers()->whereHas('investments')->count(),
                $this->customers()->whereHas('bills')->count(),
                $this->customers()->whereNotNull('telegram_chat_id')->count(),
            ],
        ];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function authenticationMix(): array
    {
        $googleAccount = fn (Builder $query): Builder => $query->where('provider', SocialAccount::ProviderGoogle);

        return [
            'labels' => ['Password only', 'Google only', 'Password and Google'],
            'values' => [
                $this->customers()->whereNotNull('password')->whereDoesntHave('socialAccounts', $googleAccount)->count(),
                $this->customers()->whereNull('password')->whereHas('socialAccounts', $googleAccount)->count(),
                $this->customers()->whereNotNull('password')->whereHas('socialAccounts', $googleAccount)->count(),
            ],
        ];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function locales(): array
    {
        $localeNames = [
            'en' => 'English',
            'fa' => 'Persian',
            'de' => 'German',
        ];
        $counts = $this->customers()
            ->selectRaw('locale, COUNT(*) as aggregate')
            ->groupBy('locale')
            ->pluck('aggregate', 'locale');

        return [
            'labels' => collect($localeNames)->keys()->map(fn (string $locale): string => $localeNames[$locale])->all(),
            'values' => collect($localeNames)->keys()->map(fn (string $locale): int => (int) ($counts[$locale] ?? 0))->all(),
        ];
    }

    /**
     * Thirty-day retention (share still active in the last 30 days) for
     * customers old enough to be measured, split by segment.
     *
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    private function retentionSegments(): array
    {
        $eligibleCutoff = now()->subDays(30);
        $activeCutoff = now()->subDays(30);
        $localeNames = ['en' => 'English', 'fa' => 'Persian', 'de' => 'German'];

        $eligible = fn (): Builder => $this->customers()->where('created_at', '<=', $eligibleCutoff);
        $eligibleByLocale = $eligible()
            ->selectRaw('locale, COUNT(*) as aggregate')
            ->groupBy('locale')
            ->pluck('aggregate', 'locale');
        $activeByLocale = $eligible()
            ->where('last_active_at', '>=', $activeCutoff)
            ->selectRaw('locale, COUNT(*) as aggregate')
            ->groupBy('locale')
            ->pluck('aggregate', 'locale');

        $labels = [];
        $values = [];

        foreach ($localeNames as $locale => $name) {
            $eligibleCount = (int) ($eligibleByLocale[$locale] ?? 0);

            if ($eligibleCount === 0) {
                continue;
            }

            $labels[] = $name;
            $values[] = $this->percentage((int) ($activeByLocale[$locale] ?? 0), $eligibleCount);
        }

        $telegramSegments = [
            'Telegram linked' => fn (Builder $query): Builder => $query->whereNotNull('telegram_chat_id'),
            'No Telegram' => fn (Builder $query): Builder => $query->whereNull('telegram_chat_id'),
            'Pro' => fn (Builder $query): Builder => $query->where('pro_until', '>', now()),
            'Free' => fn (Builder $query): Builder => $query->where(fn (Builder $inner) => $inner
                ->whereNull('pro_until')
                ->orWhere('pro_until', '<=', now())),
            'AI assistant connected' => fn (Builder $query): Builder => $query->tap(McpTokenQuery::connected(...)),
            'No AI assistant' => fn (Builder $query): Builder => $query->tap(McpTokenQuery::disconnected(...)),
        ];

        foreach ($telegramSegments as $label => $constraint) {
            $eligibleCount = $constraint($eligible())->count();

            if ($eligibleCount === 0) {
                continue;
            }

            $labels[] = $label;
            $values[] = $this->percentage(
                $constraint($eligible())->where('last_active_at', '>=', $activeCutoff)->count(),
                $eligibleCount,
            );
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Historical activity from the daily snapshots. Empty until the scheduled
     * snapshot job has captured its first days of data.
     *
     * @return array{labels: array<int, string>, active_7d: array<int, int>, active_30d: array<int, int>}
     */
    private function engagementTrend(string $range): array
    {
        $stats = DailyStat::query()
            ->where('date', '>=', $this->rangeStart($range, now())->toDateString())
            ->orderBy('date')
            ->get();

        return [
            'labels' => $stats->map(fn (DailyStat $stat): string => $stat->date->format('M j'))->all(),
            'active_7d' => $stats->pluck('active_customers_7d')->all(),
            'active_30d' => $stats->pluck('active_customers_30d')->all(),
        ];
    }

    /**
     * Activation and funnel metrics for customers who joined on or after the
     * given date. Activation means a first transaction within seven days of
     * signing up; "returned" means activity beyond the first week.
     *
     * @return array{signups: int, verified: int, profiled: int, transacted: int, returned: int, activated: int, median_days_to_first_transaction: float|null}
     */
    private function signupCohort(CarbonInterface $start): array
    {
        if (isset($this->cohortCache[$start->timestamp])) {
            return $this->cohortCache[$start->timestamp];
        }

        $signups = $this->customers()
            ->where('created_at', '>=', $start)
            ->get(['id', 'created_at', 'email_verified_at', 'birthdate', 'last_active_at']);
        $firstTransactionAt = Transaction::query()
            ->join('users', 'users.id', '=', 'transactions.user_id')
            ->where('users.created_at', '>=', $start)
            ->groupBy('transactions.user_id')
            ->selectRaw('transactions.user_id as user_id, MIN(transactions.created_at) as first_transaction_at')
            ->pluck('first_transaction_at', 'user_id');

        $verified = 0;
        $profiled = 0;
        $transacted = 0;
        $returned = 0;
        $activated = 0;
        $daysToFirstTransaction = [];

        foreach ($signups as $user) {
            $verified += $user->email_verified_at !== null ? 1 : 0;
            $profiled += $user->birthdate !== null ? 1 : 0;
            $returned += $user->last_active_at?->gt($user->created_at->copy()->addDays(7)) ? 1 : 0;

            $firstAt = $firstTransactionAt[$user->id] ?? null;

            if ($firstAt === null) {
                continue;
            }

            $transacted++;
            $days = $user->created_at->diffInDays(Carbon::parse($firstAt), absolute: true);
            $daysToFirstTransaction[] = $days;
            $activated += $days <= 7 ? 1 : 0;
        }

        return $this->cohortCache[$start->timestamp] = [
            'signups' => $signups->count(),
            'verified' => $verified,
            'profiled' => $profiled,
            'transacted' => $transacted,
            'returned' => $returned,
            'activated' => $activated,
            'median_days_to_first_transaction' => $this->median($daysToFirstTransaction),
        ];
    }

    private function rangeStart(string $range, CarbonInterface $now): CarbonInterface
    {
        return match ($range) {
            '30d' => $now->copy()->subDays(30),
            '90d' => $now->copy()->subDays(90),
            default => $now->copy()->startOfMonth()->subMonths(11),
        };
    }

    private function previousRangeStart(string $range, CarbonInterface $now): CarbonInterface
    {
        $rangeStart = $this->rangeStart($range, $now);

        return match ($range) {
            '30d' => $rangeStart->copy()->subDays(30),
            '90d' => $rangeStart->copy()->subDays(90),
            default => $rangeStart->copy()->subMonths(12),
        };
    }

    private function baselineSnapshot(CarbonInterface $now): ?DailyStat
    {
        return DailyStat::query()
            ->where('date', '<=', $now->copy()->subDays(30)->toDateString())
            ->orderByDesc('date')
            ->first();
    }

    /**
     * @return Builder<User>
     */
    private function customers(): Builder
    {
        return User::query()->customers();
    }

    private function percentage(int $value, int $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 1) : 0.0;
    }

    private function percentChange(int $current, int $previous): ?float
    {
        return $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : null;
    }

    /**
     * @param  array<int, float>  $values
     */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $middle = intdiv(count($values), 2);
        $median = count($values) % 2 === 1
            ? $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;

        return round($median, 1);
    }
}
