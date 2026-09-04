<?php

use App\Actions\Miles\AdjustMiles;
use App\Actions\Miles\EnsureWelcomeMiles;
use App\Enums\Feature;
use App\Enums\MilesReason;
use App\Exceptions\InsufficientMiles;
use App\Models\User;

test('the economy configuration buys all but one optional module', function () {
    expect(config('miles.welcome_grant'))
        ->toBe((count(config('miles.paid_modules')) - 1) * config('miles.unlock_price'))
        ->and(config('miles.paid_modules'))->not->toContain(Feature::Vault->value)
        ->and(config('miles.never_reward_financial_amounts'))->toBeTrue();
});

test('wallet adjustments are idempotent and never become negative', function () {
    $user = User::factory()->create();
    $adjust = app(AdjustMiles::class);

    $first = $adjust($user, 50, MilesReason::AdminAdjustment, 'credit-once');
    $again = $adjust($user, 50, MilesReason::AdminAdjustment, 'credit-once');
    $adjust($user, -20, MilesReason::ModuleUnlock, 'spend-once');

    expect($again->is($first))->toBeTrue()
        ->and($user->mileWallet()->value('balance'))->toBe(30)
        ->and($user->mileWallet()->value('lifetime_earned'))->toBe(50)
        ->and($user->mileWallet()->value('lifetime_spent'))->toBe(20)
        ->and($user->mileLedgerEntries()->count())->toBe(2);

    expect(fn () => $adjust($user, -31, MilesReason::ModuleUnlock, 'too-much'))
        ->toThrow(InsufficientMiles::class);
});

test('welcome miles require verified email and a completed profile', function () {
    $eligible = User::factory()->create();
    $unverified = User::factory()->unverified()->create();
    $incomplete = User::factory()->create(['birthdate' => null]);
    $grant = app(EnsureWelcomeMiles::class);

    $grant($eligible);
    $grant($eligible);
    $grant($unverified);
    $grant($incomplete);

    expect($eligible->mileWallet()->value('balance'))->toBe(150)
        ->and($eligible->mileLedgerEntries()->count())->toBe(1)
        ->and($unverified->mileWallet()->exists())->toBeFalse()
        ->and($incomplete->mileWallet()->exists())->toBeFalse();
});
