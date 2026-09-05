<?php

use App\Actions\Admin\BuildMilesAnalytics;
use App\Enums\Feature;
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
use Illuminate\Support\Carbon;

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
        ServiceUsageEvent::factory()->create([
            'user_id' => $claimer,
            'provider_cost_usd' => 0.1,
            'latency_ms' => 100,
            'shadow_miles' => 175,
            'charged_miles' => 0,
            'created_at' => now()->subDays(2),
        ]);
        ServiceUsageEvent::factory()->create([
            'user_id' => $claimer,
            'provider_cost_usd' => 0.5,
            'latency_ms' => 500,
            'outcome' => 'failure',
            'shadow_miles' => 250,
            'charged_miles' => 0,
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
