<?php

use App\Actions\Admin\BuildMilesAnalytics;
use App\Enums\Feature;
use App\Enums\MilesReason;
use App\Enums\ReferralStage;
use App\Enums\StreakProtectionType;
use App\Models\AdvisorRecommendation;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['app.admin_email' => 'admin@example.com']);
});

test('it reports the Miles economy retention sinks referrals and advisor costs', function () {
    Carbon::setTestNow('2026-07-15 12:00:00');

    try {
        User::factory()->create(['email' => 'admin@example.com']);
        $claimer = User::factory()->create([
            'email' => 'claimer@example.com',
            'created_at' => '2026-06-01 00:00:00',
        ]);
        $nonClaimer = User::factory()->create([
            'email' => 'logger@example.com',
            'created_at' => '2026-06-01 00:00:00',
        ]);
        MileWallet::factory()->create([
            'user_id' => $claimer,
            'balance' => 85,
            'lifetime_earned' => 150,
            'lifetime_spent' => 65,
        ]);
        MileWallet::factory()->create([
            'user_id' => $nonClaimer,
            'balance' => 10,
            'lifetime_earned' => 10,
        ]);

        MileLedgerEntry::factory()->create([
            'user_id' => $claimer,
            'reason' => MilesReason::Welcome,
            'amount' => 150,
            'balance_after' => 150,
            'created_at' => now()->subDays(20),
        ]);
        MileLedgerEntry::factory()->create([
            'user_id' => $claimer,
            'reason' => MilesReason::StreakFreeze,
            'amount' => -40,
            'balance_after' => 110,
            'created_at' => now()->subDays(10),
        ]);
        MileLedgerEntry::factory()->create([
            'user_id' => $claimer,
            'reason' => MilesReason::ReferralGiftSent,
            'amount' => -25,
            'balance_after' => 85,
            'transfer_id' => (string) str()->ulid(),
            'created_at' => now()->subDay(),
        ]);

        MileDay::factory()->create([
            'user_id' => $claimer,
            'local_date' => '2026-06-02',
            'claim_step' => 7,
            'claim_miles' => 8,
            'claimed_at' => '2026-06-02 12:00:00',
        ]);
        MileDay::factory()->create([
            'user_id' => $claimer,
            'local_date' => '2026-06-08',
            'activity_miles' => 2,
        ]);
        MileDay::factory()->create([
            'user_id' => $claimer,
            'local_date' => '2026-07-01',
            'activity_miles' => 2,
        ]);
        MileDay::factory()->create([
            'user_id' => $nonClaimer,
            'local_date' => '2026-06-02',
            'activity_miles' => 2,
        ]);

        foreach (config('miles.paid_modules') as $index => $feature) {
            UserFeatureUnlock::query()->create([
                'user_id' => $claimer->id,
                'feature' => Feature::from($feature),
                'unlocked_at' => $claimer->created_at->copy()->addHours($index + 1),
            ]);
        }

        StreakProtection::factory()->create([
            'user_id' => $claimer,
            'type' => StreakProtectionType::Freeze,
            'created_at' => now()->subDays(5),
        ]);
        UserCosmetic::factory()->create([
            'user_id' => $claimer,
            'acquired_at' => now()->subDays(4),
        ]);

        $referral = Referral::factory()->create([
            'referrer_id' => $claimer,
            'referred_user_id' => $nonClaimer,
            'status' => 'active',
            'attributed_at' => now()->subDays(15),
        ]);
        ReferralReward::factory()->create([
            'referral_id' => $referral,
            'stage' => ReferralStage::Activated,
            'awarded_at' => now()->subDays(3),
        ]);
        $successfulRecommendation = AdvisorRecommendation::factory()->for($claimer)->create([
            'miles_outcome' => 'full',
            'miles_settled_at' => now()->subDays(2),
        ]);
        $failedRecommendation = AdvisorRecommendation::factory()->for($claimer)->create([
            'miles_outcome' => 'failure',
            'miles_settled_at' => now()->subDay(),
        ]);
        ServiceUsageEvent::factory()->create([
            'user_id' => $claimer,
            'provider_cost_usd' => 0.1,
            'latency_ms' => 100,
            'shadow_miles' => 175,
            'charged_miles' => 0,
            // A real advisor event always names the recommendation it belongs
            // to; the gate counts one terminal outcome per distinct source.
            'source_type' => 'advisor_recommendation',
            'source_id' => $successfulRecommendation->id,
            'created_at' => now()->subDays(2),
        ]);
        ServiceUsageEvent::factory()->create([
            'user_id' => $claimer,
            'provider_cost_usd' => 0.5,
            'latency_ms' => 500,
            'outcome' => 'failure',
            'shadow_miles' => 250,
            'charged_miles' => 0,
            'source_type' => 'advisor_recommendation',
            'source_id' => $failedRecommendation->id,
            'created_at' => now()->subDay(),
        ]);

        $analytics = app(BuildMilesAnalytics::class)(now()->subDays(90));

        expect($analytics['overview'])->toMatchArray([
            'wallets' => 2,
            'outstanding' => 95,
            'issued' => 150,
            'spent' => 40,
            'transferred' => 25,
        ])->and($analytics['retention'])->toMatchArray([
            'labels' => ['D1', 'D7', 'D30'],
            'claimed' => [100.0, 100.0, 100.0],
            'never_claimed' => [100.0, 0.0, 0.0],
        ])->and($analytics['engagement'])->toMatchArray([
            'claimers' => 1,
            'claims' => 1,
            'completed_cycles' => 1,
            'activity_days' => 3,
        ])->and($analytics['unlocks'])->toMatchArray([
            'total' => 7,
            'median_hours_to_first' => 1.0,
            'median_hours_to_seventh' => 7.0,
        ])->and($analytics['protections'])->toMatchArray([
            'freeze_purchases' => 1,
            'freezes_consumed' => 1,
            'cosmetics_purchased' => 1,
        ])->and($analytics['referrals'])->toMatchArray([
            'signups' => 1,
            'activated' => 1,
            'gifts' => 1,
        ])->and($analytics['advisor'])->toMatchArray([
            'operations' => 2,
            'recommendations' => 2,
            'successful_recommendations' => 1,
            'terminal_failures' => 1,
            'terminal_failure_rate' => 50.0,
            'provider_cost_p50_usd' => 0.1,
            'provider_cost_p95_usd' => 0.5,
            'latency_p50_ms' => 100.0,
            'latency_p95_ms' => 500.0,
            'shadow_miles' => 425,
            'charged_miles' => 0,
            'reconciliation_mismatches' => 0,
        ]);
    } finally {
        Carbon::setTestNow();
    }
});

test('the advisor launch gate counts recommendations rather than provider calls', function () {
    $user = User::factory()->create();

    $successful = AdvisorRecommendation::factory()->for($user)->create([
        'miles_outcome' => 'full',
        'miles_settled_at' => now()->subDay(),
    ]);
    $failed = AdvisorRecommendation::factory()->for($user)->create([
        'miles_outcome' => 'failure',
        'miles_settled_at' => now()->subDay(),
    ]);
    $unsettled = AdvisorRecommendation::factory()->for($user)->create([
        'miles_outcome' => null,
        'miles_settled_at' => null,
    ]);

    $event = function (?string $recommendationId, string $outcome, int $minute, string $operation = 'recommendation') use ($user): void {
        ServiceUsageEvent::factory()->create([
            'user_id' => $user,
            'operation' => $operation,
            'outcome' => $outcome,
            'source_type' => $recommendationId === null ? null : 'advisor_recommendation',
            'source_id' => $recommendationId,
            'created_at' => now()->subDay()->addMinutes($minute),
        ]);
    };

    // One recommendation, three provider calls: the opening one, a
    // clarification, then the repair that produced a usable plan.
    $event($successful->id, 'success', 0);
    $event($successful->id, 'failure', 1, 'repair');
    $event($successful->id, 'success', 2, 'guidance');

    // Provider telemetry says success, but the settled domain outcome says the
    // recommendation ultimately failed.
    $event($failed->id, 'success', 0);

    // A provider response is not terminal until its recommendation is settled.
    $event($unsettled->id, 'success', 0);

    // Neither of these is a recommendation, so neither may dilute the rate.
    $event(null, 'success', 0, 'assessment');
    $event(null, 'success', 0, 'consultation');

    $advisor = app(BuildMilesAnalytics::class)(now()->subDays(90))['advisor'];

    expect($advisor['operations'])->toBe(7)
        ->and($advisor['recommendations'])->toBe(2)
        ->and($advisor['successful_recommendations'])->toBe(1)
        ->and($advisor['terminal_failures'])->toBe(1)
        ->and($advisor['terminal_failure_rate'])->toBe(50.0);
});

test('an immediate seventh unlock remains in the median', function () {
    $user = User::factory()->create(['created_at' => now()->subHour()]);

    foreach (config('miles.paid_modules') as $feature) {
        UserFeatureUnlock::query()->create([
            'user_id' => $user->id,
            'feature' => Feature::from($feature),
            'unlocked_at' => $user->created_at,
        ]);
    }

    $unlocks = app(BuildMilesAnalytics::class)(now()->subDays(90))['unlocks'];

    expect($unlocks['median_hours_to_seventh'])->toBe(0.0);
});

test('retention reads a signup date on the same clock as the activity it counts', function () {
    Carbon::setTestNow('2026-07-15 12:00:00');

    try {
        // 21:00 UTC is already the 2nd in Tehran, so this account's own first
        // day is the 2nd and its D1 is the 3rd. Reading the UTC date instead
        // looks for activity on the 2nd and calls a retained user churned.
        $user = User::factory()->create([
            'timezone' => 'Asia/Tehran',
            'created_at' => '2026-07-01 21:00:00',
        ]);
        MileDay::factory()->create([
            'user_id' => $user,
            'local_date' => '2026-07-03',
            'claimed_at' => now()->subDays(10),
            'activity_miles' => 2,
        ]);

        $retention = app(BuildMilesAnalytics::class)(now()->subDays(90))['retention'];

        expect($retention['claimed'][0])->toBe(100.0);
    } finally {
        Carbon::setTestNow();
    }
});

/** Builds a customer with a claim, an activity day and a spread of ledger rows. */
function analyticsCustomer(int $index): User
{
    $user = User::factory()->create([
        'email' => "customer{$index}@example.com",
        'created_at' => now()->subDays(40),
    ]);
    MileWallet::factory()->create(['user_id' => $user, 'balance' => 25]);

    foreach ([MilesReason::Welcome, MilesReason::DailyClaim, MilesReason::ModuleUnlock] as $offset => $reason) {
        MileLedgerEntry::query()->create([
            'user_id' => $user->id,
            'amount' => $reason === MilesReason::ModuleUnlock ? -25 : 10,
            'balance_after' => 25,
            'reason' => $reason,
            'idempotency_key' => "seed:{$index}:{$offset}",
        ]);
    }

    foreach ([1, 2] as $day) {
        MileDay::factory()->create([
            'user_id' => $user,
            'local_date' => now()->subDays($day)->toDateString(),
            'claimed_at' => now()->subDays($day),
            'activity_miles' => 2,
        ]);
    }

    return $user;
}

test('the dashboard costs the same number of queries however much data it summarises', function () {
    config(['app.admin_email' => 'admin@example.com']);

    $count = function (): array {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query;
        });
        app(BuildMilesAnalytics::class)(now()->subDays(90));
        DB::flushQueryLog();

        return $queries;
    };

    foreach (range(1, 3) as $index) {
        analyticsCustomer($index);
    }
    $small = $count();

    foreach (range(4, 15) as $index) {
        analyticsCustomer($index);
    }
    $large = $count();

    // Five times the rows, the same work: nothing here iterates the data.
    expect(count($large))->toBe(count($small));

    // The ledger and the usage log are the tables that grow without bound, so
    // neither may be read row by row - every touch aggregates, or takes a
    // single row by limit. Reading a year of either to count it is the failure
    // this guards.
    $rowWise = collect($large)
        ->filter(fn ($query): bool => str_contains($query->sql, 'mile_ledger_entries')
            || str_contains($query->sql, 'service_usage_events'))
        ->reject(fn ($query): bool => (bool) preg_match('/(sum|count|max|min)\s*\(/i', $query->sql)
            || str_contains(strtolower($query->sql), 'limit'))
        ->pluck('sql')
        ->all();

    expect($rowWise)->toBe([]);
});
