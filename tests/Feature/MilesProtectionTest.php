<?php

use App\Actions\Miles\AdjustMiles;
use App\Actions\Miles\ClaimDailyMiles;
use App\Actions\Miles\PurchaseStreakFreeze;
use App\Actions\Miles\RepairStreak;
use App\Enums\MilesReason;
use App\Enums\StreakProtectionType;
use App\Models\MileDay;
use App\Models\MileWallet;
use App\Models\StreakProtection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-09-10 12:00:00 UTC');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('buys up to two freezes from the locked wallet', function () {
    $user = User::factory()->create();
    app(AdjustMiles::class)($user, 120, MilesReason::AdminAdjustment, 'seed');

    app(PurchaseStreakFreeze::class)($user);
    app(PurchaseStreakFreeze::class)($user);

    expect($user->mileWallet()->first()->freezes_held)->toBe(2)
        ->and($user->mileWallet()->first()->balance)->toBe(40);

    expect(fn () => app(PurchaseStreakFreeze::class)($user))->toThrow(ValidationException::class);
});

it('uses weekly grace before a freeze and preserves the claim cycle', function () {
    $user = User::factory()->create(['created_at' => now()->subMonth()]);
    MileWallet::factory()->for($user)->create(['freezes_held' => 1]);
    MileDay::query()->create([
        'user_id' => $user->id,
        'local_date' => '2026-09-07',
        'timezone' => 'UTC',
        'claim_step' => 3,
        'claim_miles' => 3,
        'claimed_at' => now()->subDays(3),
        // Recorded, not merely claimed: protection bridges a gap in the record,
        // so there has to be a record on the far side of it to protect.
        'activity_miles' => 2,
    ]);

    $day = app(ClaimDailyMiles::class)($user);

    expect($day->claim_step)->toBe(4)
        ->and($user->mileWallet()->first()->freezes_held)->toBe(0)
        ->and(StreakProtection::query()->where('type', StreakProtectionType::WeeklyGrace)->count())->toBe(1)
        ->and(StreakProtection::query()->where('type', StreakProtectionType::Freeze)->count())->toBe(1);
});

it('never wastes freezes on a gap they cannot fully protect', function () {
    $user = User::factory()->create(['created_at' => now()->subMonth()]);
    MileWallet::factory()->for($user)->create(['freezes_held' => 1]);
    MileDay::query()->create([
        'user_id' => $user->id,
        'local_date' => '2026-09-06',
        'timezone' => 'UTC',
        'claim_step' => 6,
        'claim_miles' => 5,
        'claimed_at' => now()->subDays(4),
    ]);

    $day = app(ClaimDailyMiles::class)($user);

    expect($day->claim_step)->toBe(1)
        ->and($user->mileWallet()->first()->freezes_held)->toBe(1)
        ->and(StreakProtection::query()->count())->toBe(0);
});

it('charges for a repair only when it reconnects the current run', function () {
    $user = User::factory()->create(['created_at' => now()->subMonth()]);
    app(AdjustMiles::class)($user, 120, MilesReason::AdminAdjustment, 'seed');
    MileDay::query()->create([
        'user_id' => $user->id,
        'local_date' => '2026-09-07',
        'timezone' => 'UTC',
        'activity_miles' => 2,
    ]);
    MileDay::query()->create([
        'user_id' => $user->id,
        'local_date' => '2026-09-10',
        'timezone' => 'UTC',
        'activity_miles' => 2,
    ]);

    $protection = app(RepairStreak::class)($user, CarbonImmutable::parse('2026-09-09'));

    expect($protection->type)->toBe(StreakProtectionType::Repair)
        ->and($user->mileWallet()->first()->balance)->toBe(20);
});
