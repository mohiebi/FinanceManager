<?php

use App\Actions\Miles\AdjustMiles;
use App\Actions\Miles\EnsureWelcomeMiles;
use App\Enums\Feature;
use App\Enums\MilesReason;
use App\Exceptions\InsufficientMiles;
use App\Jobs\ReconcileMileWalletsJob;
use App\Models\MileLedgerEntry;
use App\Models\User;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

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

test('reconciliation repairs a drifted wallet from the ledger and never the reverse', function () {
    $user = User::factory()->create();
    app(AdjustMiles::class)($user, 150, MilesReason::Welcome, 'welcome-drift');
    app(AdjustMiles::class)($user, -25, MilesReason::ModuleUnlock, 'unlock-drift');

    $wallet = $user->mileWallet()->sole();
    $entriesBefore = $user->mileLedgerEntries()->count();

    // However it happened, the cache no longer agrees with the entries.
    $wallet->forceFill(['balance' => 999, 'lifetime_earned' => 1, 'lifetime_spent' => 0])->save();

    Log::spy();
    app(ReconcileMileWalletsJob::class)->handle();

    expect($wallet->fresh()->balance)->toBe(125)
        ->and($wallet->fresh()->lifetime_earned)->toBe(150)
        ->and($wallet->fresh()->lifetime_spent)->toBe(25)
        // Correcting the record to justify the cache would be backwards, so the
        // ledger is left exactly as it was.
        ->and($user->mileLedgerEntries()->count())->toBe($entriesBefore);

    Log::shouldHaveReceived('error')->atLeast()->once();
});

test('reconciliation leaves a wallet that already agrees with its ledger alone', function () {
    $user = User::factory()->create();
    app(AdjustMiles::class)($user, 150, MilesReason::Welcome, 'welcome-clean');
    $before = $user->mileWallet()->sole()->updated_at;

    Log::spy();
    app(ReconcileMileWalletsJob::class)->handle();

    expect($user->mileWallet()->sole()->balance)->toBe(150)
        ->and($user->mileWallet()->sole()->updated_at->eq($before))->toBeTrue();

    Log::shouldNotHaveReceived('error');
});

test('a spend landing before the lock is counted rather than erased', function () {
    $user = User::factory()->create();
    app(AdjustMiles::class)($user, 150, MilesReason::Welcome, 'welcome-race');

    $wallet = $user->mileWallet()->sole();
    $wallet->forceFill(['balance' => 999])->save();

    // The sweep shortlists this wallet, then opens its transaction to correct
    // it. A real spend committing in that window used to be overwritten by the
    // total read before it, leaving the wallet ahead of its own ledger.
    $spent = false;
    Event::listen(TransactionBeginning::class, function () use ($user, &$spent): void {
        if ($spent) {
            return;
        }

        $spent = true;
        MileLedgerEntry::query()->create([
            'user_id' => $user->getKey(),
            'amount' => -25,
            'balance_after' => 125,
            'reason' => MilesReason::ModuleUnlock,
            'idempotency_key' => 'race-spend',
        ]);
    });

    app(ReconcileMileWalletsJob::class)->handle();

    expect($spent)->toBeTrue()
        ->and($user->mileWallet()->sole()->balance)->toBe(125)
        ->and($user->mileLedgerEntries()->sum('amount'))->toBe(125);
});
