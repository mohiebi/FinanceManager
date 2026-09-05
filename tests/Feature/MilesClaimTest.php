<?php

use App\Actions\Miles\AwardDailyActivity;
use App\Actions\Miles\ClaimDailyMiles;
use App\Models\User;
use Illuminate\Support\Carbon;

test('daily claims follow the seven day schedule without a twenty hour cooldown', function () {
    Carbon::setTestNow('2026-09-01 23:00:00');
    $user = User::factory()->create(['timezone' => 'UTC']);
    $claim = app(ClaimDailyMiles::class);

    $first = $claim($user);
    expect($claim($user)->is($first))->toBeTrue();

    Carbon::setTestNow('2026-09-02 00:01:00');
    $second = $claim($user);

    expect($first->claim_step)->toBe(1)
        ->and($second->claim_step)->toBe(2)
        ->and($user->mileWallet()->value('balance'))->toBe(6);

    Carbon::setTestNow();
});

test('a qualifying activity awards two miles only once per local day', function () {
    $user = User::factory()->create();
    $award = app(AwardDailyActivity::class);

    $award($user, 'bill');
    $award($user, 'investment');

    expect($user->mileDays()->sole()->activity_miles)->toBe(2)
        ->and($user->mileLedgerEntries()->where('reason', 'daily_activity')->count())->toBe(1)
        ->and($user->mileWallet()->value('balance'))->toBe(7);
});

test('the claim schedule totals thirty miles', function () {
    expect(array_sum(config('miles.daily_claims')))->toBe(30);
});

test('moving the clock backwards through a timezone change cannot mint a second claim', function () {
    // 00:30 UTC is already the 5th in Kiritimati (UTC+14) but still the 4th in
    // Midway (UTC-11), so switching between them rewinds the user's local date.
    Carbon::setTestNow('2026-09-05 00:30:00');
    $user = User::factory()->create(['timezone' => 'Pacific/Kiritimati']);
    $claim = app(ClaimDailyMiles::class);

    $claim($user);
    $balanceAfterFirstClaim = $user->mileWallet()->value('balance');

    $user->forceFill(['timezone' => 'Pacific/Midway'])->save();
    $claim($user->refresh());

    expect($user->localToday()->toDateString())->toBe('2026-09-04')
        ->and($user->mileWallet()->value('balance'))->toBe($balanceAfterFirstClaim)
        ->and($user->mileDays()->whereNotNull('claimed_at')->count())->toBe(1);

    Carbon::setTestNow();
});
