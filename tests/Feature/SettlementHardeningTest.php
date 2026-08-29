<?php

use App\Actions\Billing\FinalizeScreenedPayment;
use App\Contracts\Billing\AddressScreener;
use App\Enums\DepositAddressStatus;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentNetwork;
use App\Enums\PaymentStatus;
use App\Enums\ScreeningRisk;
use App\Enums\SettlementStatus;
use App\Jobs\ProcessPaymentSettlementJob;
use App\Jobs\RequeueStalledSettlementsJob;
use App\Jobs\ScreenSubscriptionPaymentJob;
use App\Models\DepositAddress;
use App\Models\DepositRecovery;
use App\Models\PaymentSettlement;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\SignerLockedNotification;
use App\Services\Billing\WalletSignerClient;
use App\Support\Billing\ScreeningResult;
use App\Support\Billing\ScreeningSubject;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    enableBilling();
    config()->set(['billing.signer.secret_file' => $this->signerSecret = tempnam(sys_get_temp_dir(), 'signer-secret-')]);
    file_put_contents($this->signerSecret, str_repeat('a', 64));
});

afterEach(function () {
    if (is_string($this->signerSecret ?? null) && is_file($this->signerSecret)) {
        unlink($this->signerSecret);
    }
});

function settleablePayment(array $overrides = []): SubscriptionPayment
{
    static $sequence = 0;

    $sequence++;
    $address = '0x'.str_pad(dechex($sequence), 40, '7', STR_PAD_LEFT);
    $txHash = '0x'.str_pad(dechex($sequence), 64, 'b', STR_PAD_LEFT);
    $payment = SubscriptionPayment::factory()->submitted($txHash)->create([
        'user_id' => User::factory()->create()->getKey(),
        'pay_to_address' => $address,
        'from_address' => '0x2222222222222222222222222222222222222222',
        'chain_verified_at' => now(),
        ...$overrides,
    ]);

    DepositAddress::factory()->create([
        'network' => PaymentNetwork::Ethereum,
        'address' => $address,
        'status' => DepositAddressStatus::Assigned,
        'assigned_payment_id' => $payment->getKey(),
        'assigned_at' => now(),
    ]);

    return $payment->fresh(['depositAddress']);
}

test('the signer is told what actually arrived rather than what was quoted', function () {
    // An exchange shaved a fee off, an administrator honoured it anyway. Sending
    // the quoted figure here meant the signer compared the deposit balance
    // against a number that address could never hold, and every such settlement
    // ended in review no matter how correct the admin decision was.
    $payment = settleablePayment([
        'expected_amount' => '5.004317',
        'received_amount' => '5.003000',
        'asset_decimals' => 6,
    ]);

    expect($payment->settlementBaseUnits())->toBe('5003000')
        ->and($payment->expectedBaseUnits())->toBe('5004317');

    $captured = null;
    Http::fake(function (Request $request) use (&$captured) {
        $captured = $request->data();

        return Http::response(['status' => 'submitted']);
    });

    app(WalletSignerClient::class)->startSettlement($payment, (string) Str::uuid());

    expect($captured['verifiedAmount'])->toBe('5003000')
        ->and($captured['kind'])->toBe('settlement');
});

test('a payment with no readable amount still falls back to the quote', function () {
    $payment = settleablePayment([
        'expected_amount' => '5.004317',
        'received_amount' => null,
        'asset_decimals' => 6,
    ]);

    expect($payment->settlementBaseUnits())->toBe('5004317');
});

test('screening runs for an approved payment whose transfer amount was never readable', function () {
    // Native ether moved inside a contract call: real money, genuinely paid,
    // with no value a receipt can be read for. Requiring a received amount here
    // stranded the exact case the approve button exists for.
    Queue::fake();
    $payment = settleablePayment(['received_amount' => null]);

    app(ScreenSubscriptionPaymentJob::class, ['paymentId' => $payment->getKey()])
        ->handle(
            new class implements AddressScreener
            {
                public function screen(ScreeningSubject $subject): ScreeningResult
                {
                    return new ScreeningResult(
                        ScreeningRisk::NoMatch,
                        'test',
                        screenedAt: now()->toImmutable(),
                    );
                }
            },
            app(FinalizeScreenedPayment::class),
        );

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->fresh()->screening_risk)->toBe(ScreeningRisk::NoMatch);
});

test('stalled settlements are re-driven and ones awaiting a person are left alone', function () {
    Queue::fake();
    Http::fake(fn () => Http::response(['ok' => true, 'locked' => false]));

    $stalled = PaymentSettlement::query()->create([
        'subscription_payment_id' => settleablePayment()->getKey(),
        'operation_id' => (string) Str::uuid(),
        'status' => SettlementStatus::RetryableFailure,
    ]);
    $needsReview = PaymentSettlement::query()->create([
        'subscription_payment_id' => settleablePayment()->getKey(),
        'operation_id' => (string) Str::uuid(),
        'status' => SettlementStatus::NeedsReview,
    ]);
    $fresh = PaymentSettlement::query()->create([
        'subscription_payment_id' => settleablePayment()->getKey(),
        'operation_id' => (string) Str::uuid(),
        'status' => SettlementStatus::Processing,
    ]);

    PaymentSettlement::query()->whereKey([$stalled->getKey(), $needsReview->getKey()])
        ->update(['updated_at' => now()->subHour()]);

    app(RequeueStalledSettlementsJob::class)->handle(app(WalletSignerClient::class));

    Queue::assertPushed(ProcessPaymentSettlementJob::class, 1);
    Queue::assertPushed(
        ProcessPaymentSettlementJob::class,
        fn (ProcessPaymentSettlementJob $job) => $job->settlementId === $stalled->getKey(),
    );
    expect($fresh->fresh()->status)->toBe(SettlementStatus::Processing);
});

test('a signer left locked after a restart raises exactly one alert', function () {
    // Every container restart leaves it locked by design, and nothing else
    // notices: settlements just fail retryably and stop.
    Notification::fake();
    $admin = User::factory()->create(['email' => 'ops@example.test']);
    config()->set('app.admin_email', 'ops@example.test');
    Http::fake(fn () => Http::response(['ok' => true, 'locked' => true]));

    app(RequeueStalledSettlementsJob::class)->handle(app(WalletSignerClient::class));
    app(RequeueStalledSettlementsJob::class)->handle(app(WalletSignerClient::class));

    Notification::assertSentToTimes($admin, SignerLockedNotification::class, 1);
});

test('recovery refuses quarantined funds and any address a payment still owns', function () {
    foreach ([DepositAddressStatus::Quarantined, DepositAddressStatus::Assigned, DepositAddressStatus::Settling] as $status) {
        $address = DepositAddress::factory()->create([
            'network' => PaymentNetwork::Ethereum,
            'status' => $status,
        ]);

        $this->artisan('billing:recover-address', ['address' => $address->address, '--force' => true])
            ->assertFailed();

        expect($address->fresh()->status)->toBe($status)
            ->and(DepositRecovery::query()->whereBelongsTo($address)->exists())->toBeFalse();
    }
});

test('recovery sweeps a retired address and records the operation for resumption', function () {
    $address = DepositAddress::factory()->create([
        'network' => PaymentNetwork::Ethereum,
        'status' => DepositAddressStatus::Retired,
    ]);

    $captured = null;
    Http::fake(function (Request $request) use (&$captured) {
        $captured = $request->data();

        return Http::response(['status' => 'completed', 'stage' => 'completed', 'transactionHashes' => ['vault_sweep' => '0x'.str_repeat('a', 64)]]);
    });

    Artisan::call('billing:recover-address', ['address' => $address->address, '--force' => true]);

    expect($address->fresh()->status)->toBe(DepositAddressStatus::Recovered)
        ->and($address->fresh()->recovered_at)->not->toBeNull()
        ->and($address->recoveries()->count())->toBe(1)
        ->and($address->recoveries()->first()->status)->toBe(SettlementStatus::Completed)
        // The destination is never named by the caller — it is whatever the
        // signer's own configuration says the risk vault is.
        ->and($captured)->not->toHaveKey('destination')
        ->and($captured['derivationIndex'])->toBe($address->derivation_index);
});

test('a recovered address can be swept again when funds arrive later', function () {
    $address = DepositAddress::factory()->create([
        'network' => PaymentNetwork::Ethereum,
        'status' => DepositAddressStatus::Retired,
    ]);

    Http::fake(fn () => Http::response([
        'status' => 'completed',
        'stage' => 'completed',
        'transactionHashes' => ['vault_sweep' => '0x'.str_repeat('a', 64)],
    ]));

    $this->artisan('billing:recover-address', ['address' => $address->address, '--force' => true])
        ->assertSuccessful();
    $this->artisan('billing:recover-address', ['address' => $address->address, '--force' => true])
        ->assertSuccessful();

    $recoveries = $address->recoveries()->oldest()->get();

    expect($recoveries)->toHaveCount(2)
        ->and($recoveries->pluck('operation_id')->unique())->toHaveCount(2)
        ->and($recoveries->every(fn (DepositRecovery $recovery): bool => $recovery->status === SettlementStatus::Completed))->toBeTrue();
});

test('a retryable recovery resumes the same signer operation', function () {
    $address = DepositAddress::factory()->create([
        'network' => PaymentNetwork::Ethereum,
        'status' => DepositAddressStatus::Retired,
    ]);
    $operationIds = [];

    Http::fake(function (Request $request) use (&$operationIds) {
        $operationIds[] = $request->data()['operationId'];

        return Http::response([
            'status' => count($operationIds) === 1 ? 'retryable_failure' : 'completed',
            'stage' => count($operationIds) === 1 ? 'gas_topup' : 'completed',
            'transactionHashes' => [],
        ]);
    });

    $this->artisan('billing:recover-address', ['address' => $address->address, '--force' => true])
        ->assertFailed();
    $this->artisan('billing:recover-address', ['address' => $address->address, '--force' => true])
        ->assertSuccessful();

    expect($address->recoveries()->count())->toBe(1)
        ->and($operationIds)->toHaveCount(2)
        ->and($operationIds[0])->toBe($operationIds[1])
        ->and($address->recoveries()->first()->status)->toBe(SettlementStatus::Completed);
});

test('a plain-account vault verifies rather than being refused as undeployed', function () {
    // A Safe is a contract; a hardware or browser wallet address is not. Both
    // are valid vaults, and only the operator knows which they meant — so a
    // missing bytecode is reported, never treated as a failure.
    config()->set([
        'billing.settlement.vaults.ethereum' => '0xb7b03c8e73d66e37da23923b9b5ca2fd37a8e6b6',
        'billing.settlement.risk_vaults.ethereum' => '0x1111111111111111111111111111111111111111',
        'billing.deposit_pool.low_address_warning' => 0,
        // On, so each of these fails for the reason under test rather than
        // incidentally because screening was left off.
        'billing.screening.enabled' => true,
    ]);

    Http::fake([
        'ethereum.test/*' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x1']),
        '*' => Http::response([
            'ok' => true,
            'locked' => false,
            'vaults' => [
                'ethereum' => [
                    'configured' => true,
                    'vault' => '0xb7b03c8e73d66e37da23923b9b5ca2fd37a8e6b6',
                    'riskVault' => '0x1111111111111111111111111111111111111111',
                    'segregated' => true,
                    'vaultHasCode' => false,
                    'riskVaultHasCode' => false,
                ],
            ],
        ]),
    ]);

    $this->artisan('billing:verify-settlement')
        ->expectsOutputToContain('plain account')
        ->assertSuccessful();
});

test('a vault the signer disagrees about is still fatal', function () {
    config()->set([
        'billing.settlement.vaults.ethereum' => '0xb7b03c8e73d66e37da23923b9b5ca2fd37a8e6b6',
        'billing.settlement.risk_vaults.ethereum' => '0x1111111111111111111111111111111111111111',
        'billing.deposit_pool.low_address_warning' => 0,
        // On, so each of these fails for the reason under test rather than
        // incidentally because screening was left off.
        'billing.screening.enabled' => true,
    ]);

    Http::fake([
        'ethereum.test/*' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x1']),
        '*' => Http::response([
            'ok' => true,
            'locked' => false,
            'vaults' => [
                'ethereum' => [
                    'configured' => true,
                    'vault' => '0x9999999999999999999999999999999999999999',
                    'riskVault' => '0x9999999999999999999999999999999999999999',
                    'segregated' => false,
                    'vaultHasCode' => true,
                    'riskVaultHasCode' => true,
                ],
            ],
        ]),
    ]);

    $this->artisan('billing:verify-settlement')
        ->expectsOutputToContain('MISMATCH')
        ->assertFailed();
});

test('infrastructure verification refuses a missing or shared risk vault', function () {
    $mainVault = '0xb7b03c8e73d66e37da23923b9b5ca2fd37a8e6b6';
    config()->set([
        'billing.settlement.vaults.ethereum' => $mainVault,
        'billing.settlement.risk_vaults.ethereum' => $mainVault,
        'billing.deposit_pool.low_address_warning' => 0,
        // On, so each of these fails for the reason under test rather than
        // incidentally because screening was left off.
        'billing.screening.enabled' => true,
    ]);

    Http::fake([
        'ethereum.test/*' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x1']),
        '*' => Http::response([
            'ok' => true,
            'locked' => false,
            'vaults' => [
                'ethereum' => [
                    'configured' => true,
                    'vault' => $mainVault,
                    'riskVault' => $mainVault,
                    'segregated' => false,
                    'vaultHasCode' => false,
                    'riskVaultHasCode' => false,
                ],
            ],
        ]),
    ]);

    $this->artisan('billing:verify-settlement')
        ->expectsOutputToContain('must be valid, distinct addresses')
        ->assertFailed();
});

test('rejecting a payment screening never answered releases it and its address', function () {
    // The deadlock this closes: screening returns Unknown until the job gives
    // up, which parks the payment at Submitted with ScreeningUnavailable — a
    // reason needsReview() deliberately excludes, so approve refused it, and
    // reject refused it too because the transfer was already chain verified.
    // Nothing an operator could press moved it, and the buyer's money stayed at
    // a deposit address the recovery command would not touch either.
    Queue::fake();
    config()->set('app.admin_email', 'boss@example.com');
    $admin = User::factory()->create(['email' => 'boss@example.com']);
    $payment = settleablePayment(['failure_reason' => PaymentFailureReason::ScreeningUnavailable]);

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.billing.reject', $payment), ['note' => 'Oracle down for two days; refunding off-platform.'])
        ->assertRedirect();

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Failed)
        ->and($payment->failure_reason)->toBe(PaymentFailureReason::AdminRejected)
        // Retired is what makes the funds reachable: billing:recover-address
        // sweeps only retired, swept and already-recovered addresses.
        ->and($payment->depositAddress->fresh()->status)->toBe(DepositAddressStatus::Retired);
});

test('a chain-verified payment that is not stuck on screening still cannot be rejected', function () {
    // The escape hatch is narrow on purpose. A flagged sender has a risk case
    // with its own authorize, grant and expiry path, and a clean transfer that
    // simply has not been screened yet is still in flight.
    config()->set('app.admin_email', 'boss@example.com');
    $admin = User::factory()->create(['email' => 'boss@example.com']);

    foreach ([PaymentFailureReason::FlaggedSender, null] as $reason) {
        $payment = settleablePayment(['failure_reason' => $reason]);

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.billing.reject', $payment), ['note' => 'let me through'])
            ->assertStatus(409);

        expect($payment->fresh()->status)->toBe(PaymentStatus::Submitted);
    }
});
