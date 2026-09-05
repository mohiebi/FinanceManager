<?php

use App\Enums\Feature;
use App\Enums\MilesReason;
use App\Models\MileLedgerEntry;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Models\UserFeatureUnlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-09-05 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('manual adjustments are signed auditable and idempotent', function () {
    $user = User::factory()->create(['email' => 'pilot@example.com']);

    $arguments = [
        'email' => $user->email,
        'amount' => 75,
        'idempotency' => 'support-ticket:1042',
        '--note' => 'Launch courtesy',
    ];

    $this->artisan('miles:adjust', $arguments)->assertSuccessful();
    $this->artisan('miles:adjust', $arguments)->assertSuccessful();

    expect($user->mileWallet()->value('balance'))->toBe(75)
        ->and($user->mileLedgerEntries()->count())->toBe(1)
        ->and($user->mileLedgerEntries()->sole()->metadata)->toMatchArray([
            'note' => 'Launch courtesy',
            'operator' => 'artisan',
        ]);

    $this->artisan('miles:adjust', [
        'email' => $user->email,
        'amount' => -100,
        'idempotency' => 'support-ticket:1043',
    ])->assertFailed();

    expect($user->mileWallet()->value('balance'))->toBe(75);
});

test('rollout grants eligible accounts and grandfathers enabled paid modules once', function () {
    $eligible = User::factory()->withModules(Feature::Budgets)->create();
    User::factory()->unverified()->create();
    User::factory()->create(['birthdate' => null]);

    $this->artisan('miles:rollout', ['--execute' => true])->assertSuccessful();
    $this->artisan('miles:rollout', ['--execute' => true])->assertSuccessful();

    expect($eligible->mileWallet()->value('balance'))->toBe(150)
        ->and(MileLedgerEntry::query()->where('reason', MilesReason::Welcome)->count())->toBe(1)
        ->and(UserFeatureUnlock::query()
            ->where('user_id', $eligible->getKey())
            ->where('feature', Feature::Budgets->value)
            ->count())->toBe(1)
        ->and(UserFeatureUnlock::query()->count())->toBe(1);
});

test('rollout execution stops while a payment remains in flight', function () {
    $user = User::factory()->create();
    SubscriptionPayment::factory()->create(['user_id' => $user->getKey()]);

    $this->artisan('miles:rollout', ['--execute' => true])->assertFailed();

    expect(MileLedgerEntry::query()->count())->toBe(0);
});

test('small Pro populations are listed for individual conversion without mutation', function () {
    User::factory()->pro('2026-10-06 12:00:00')->create();

    $this->artisan('miles:convert-pro', ['--execute' => true])
        ->expectsOutputToContain('use individual miles:adjust commands')
        ->assertSuccessful();

    expect(MileLedgerEntry::query()->count())->toBe(0);
});

test('large Pro populations are batch converted by started thirty day blocks once', function () {
    $first = User::factory()->pro('2026-09-06 12:00:00')->create();
    User::factory()->count(19)->pro('2026-10-06 12:00:00')->create();

    $this->artisan('miles:convert-pro', ['--execute' => true])->assertSuccessful();
    $this->artisan('miles:convert-pro', ['--execute' => true])->assertSuccessful();

    expect($first->mileWallet()->value('balance'))->toBe(250)
        ->and(MileLedgerEntry::query()->where('reason', MilesReason::LegacyProConversion)->count())->toBe(20)
        ->and(MileLedgerEntry::query()->where('reason', MilesReason::LegacyProConversion)->sum('amount'))->toBe(9750);
});
