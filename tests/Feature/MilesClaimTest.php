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
