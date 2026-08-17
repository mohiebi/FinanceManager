<?php

use App\Actions\Billing\FinalizeScreenedPayment;
use App\Actions\Billing\VerifyPaymentOnChain;
use App\Contracts\Billing\AddressScreener;
use App\Enums\CouponRedemptionStatus;
use App\Enums\DepositAddressStatus;
use App\Enums\PaymentNetwork;
use App\Enums\PaymentStatus;
use App\Enums\ScreeningRisk;
use App\Enums\ScreeningStage;
use App\Enums\SettlementAsset;
use App\Jobs\ScreenSubscriptionPaymentJob;
use App\Jobs\VerifySubscriptionPaymentJob;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\DepositAddress;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\PaymentQuarantinedNotification;
use App\Notifications\SubscriptionActivatedNotification;
use App\Notifications\SubscriptionPaymentQuarantinedNotification;
use App\Services\Billing\ChainalysisSanctionsScreener;
use App\Services\Billing\OnChainSanctionsOracleScreener;
use App\Support\Billing\ScreeningResult;
use App\Support\Billing\ScreeningSubject;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    enableBilling([
        'billing.screening.enabled' => true,
        'billing.screening.oracle.contracts.ethereum' => '0x40c57923924b5c5c5455c48d93317139addac8fb',
    ]);
    Notification::fake();
    Http::preventStrayRequests();
});

function screeningSubject(): ScreeningSubject
{
    return new ScreeningSubject(
        network: PaymentNetwork::Ethereum,
        transactionHash: '0x'.str_repeat('a', 64),
        senderAddress: '0x2222222222222222222222222222222222222222',
        recipientAddress: TEST_RECEIVING_ADDRESS,
        asset: SettlementAsset::Usdt,
        receivedAmount: '5.001234',
    );
}

function oracleResponse(string $verdict = '0', int $chainId = 1, string $code = '0x6000'): array
{
    return [
        ['jsonrpc' => '2.0', 'id' => 2, 'result' => '0x'.str_pad($verdict, 64, '0', STR_PAD_LEFT)],
        ['jsonrpc' => '2.0', 'id' => 0, 'result' => '0x'.dechex($chainId)],
        ['jsonrpc' => '2.0', 'id' => 1, 'result' => $code],
    ];
}

function fixedScreener(ScreeningRisk $risk): AddressScreener
{
    return new class($risk) implements AddressScreener
    {
        public function __construct(private readonly ScreeningRisk $risk) {}

        public function screen(ScreeningSubject $subject): ScreeningResult
        {
            return new ScreeningResult(
                risk: $this->risk,
                provider: 'test_provider',
                categories: $this->risk->quarantinesFunds() ? ['sanctions'] : [],
                screenedAt: now()->toImmutable(),
            );
        }
    };
}

function screenablePayment(?Coupon $coupon = null): SubscriptionPayment
{
    static $sequence = 0;

    $user = User::factory()->create();
    $deposit = DepositAddress::query()->available()->where('network', 'ethereum')->firstOrFail();
    $payment = SubscriptionPayment::factory()->submitted()->create([
        'user_id' => $user->id,
        'tx_hash' => '0x'.str_pad(dechex(++$sequence), 64, 'a', STR_PAD_LEFT),
        'coupon_id' => $coupon?->id,
        'pay_to_address' => $deposit->address,
        'from_address' => '0x2222222222222222222222222222222222222222',
        'received_amount' => '5.004317',
        'confirmations' => 12,
        'block_number' => 21_000_000,
        'block_timestamp' => now()->subHour(),
        'chain_verified_at' => now(),
    ]);

    $deposit->forceFill([
        'status' => DepositAddressStatus::Assigned,
        'assigned_payment_id' => $payment->id,
        'assigned_at' => now(),
    ])->save();

    return $payment;
}

test('oracle returns no match and sanctioned only for valid chain responses', function () {
    Http::fakeSequence()
        ->push(oracleResponse())
        ->push(oracleResponse('1'));

    $screener = app(OnChainSanctionsOracleScreener::class);

    expect($screener->screen(screeningSubject())->risk)->toBe(ScreeningRisk::NoMatch)
        ->and($screener->screen(screeningSubject())->risk)->toBe(ScreeningRisk::Sanctioned);
});

test('oracle fails closed on malformed timeout wrong chain and unavailable contract responses', function () {
    Http::fake(fn () => Http::response(['unexpected' => true]));

    $screener = app(OnChainSanctionsOracleScreener::class);

    expect($screener->screen(screeningSubject())->risk)->toBe(ScreeningRisk::Unknown);

    Http::fake(fn () => throw new ConnectionException('timeout'));

    expect($screener->screen(screeningSubject())->risk)->toBe(ScreeningRisk::Unknown);

    Http::fakeSequence()
        ->push(oracleResponse(chainId: 42161))
        ->push(oracleResponse(code: '0x0000'))
        ->push(oracleResponse('2'));

    expect($screener->screen(screeningSubject())->risk)->toBe(ScreeningRisk::Unknown)
        ->and($screener->screen(screeningSubject())->risk)->toBe(ScreeningRisk::Unknown)
        ->and($screener->screen(screeningSubject())->risk)->toBe(ScreeningRisk::Unknown);
});

test('optional chainalysis http fallback handles clear sanctioned and mismatched responses', function () {
    config()->set([
        'billing.screening.chainalysis.enabled' => true,
        'billing.screening.chainalysis.url' => 'https://chainalysis.test/address',
        'billing.screening.chainalysis.api_key' => 'test-key',
    ]);
    Http::fakeSequence()
        ->push(['identifications' => []])
        ->push(['identifications' => [['category' => 'SANCTIONS', 'name' => 'Test designation']]])
        ->push(['network' => 'arbitrum', 'identifications' => []])
        ->push(['unexpected' => true]);

    $screener = app(ChainalysisSanctionsScreener::class);

    expect($screener->screen(screeningSubject())->risk)->toBe(ScreeningRisk::NoMatch)
        ->and($screener->screen(screeningSubject())->risk)->toBe(ScreeningRisk::Sanctioned)
        ->and($screener->screen(screeningSubject())->risk)->toBe(ScreeningRisk::Unknown)
        ->and($screener->screen(screeningSubject())->risk)->toBe(ScreeningRisk::Unknown);
});

test('oracle verification command requires a positive and negative result', function () {
    Http::fakeSequence()
        ->push(oracleResponse('1'))
        ->push(oracleResponse('0'));

    $this->artisan('billing:verify-screening-oracle', [
        '--network' => ['ethereum'],
        '--sanctioned' => '0x9999999999999999999999999999999999999999',
        '--clear' => '0x0000000000000000000000000000000000000000',
    ])->assertSuccessful();
});

test('chain verification persists facts and dispatches screening without granting', function () {
    Queue::fake();
    $payment = SubscriptionPayment::factory()->submitted()->create([
        'expected_amount' => '6.000000',
        'pay_to_address' => TEST_RECEIVING_ADDRESS,
    ]);
    fakeEvmChain([
        'tx_to' => '0xdac17f958d2ee523a2206206994597c13d831ec7',
        'logs' => [evmTransferLog(
            '0xdac17f958d2ee523a2206206994597c13d831ec7',
            TEST_RECEIVING_ADDRESS,
            '0x5b8d80',
        )],
    ]);

    (new VerifySubscriptionPaymentJob($payment->id))->handle(app(VerifyPaymentOnChain::class));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Submitted)
        ->and($payment->fresh()->chain_verified_at)->not->toBeNull()
        ->and($payment->user->subscriptionGrants()->count())->toBe(0);
    Queue::assertPushed(ScreenSubscriptionPaymentJob::class);
});

test('provider screening runs before the settlement transaction opens', function () {
    $payment = screenablePayment();
    $baselineTransactionLevel = DB::transactionLevel();
    $screener = new class($baselineTransactionLevel) implements AddressScreener
    {
        public function __construct(private readonly int $baselineTransactionLevel) {}

        public function screen(ScreeningSubject $subject): ScreeningResult
        {
            expect(DB::transactionLevel())->toBe($this->baselineTransactionLevel);

            return new ScreeningResult(ScreeningRisk::NoMatch, 'transaction_level_test');
        }
    };

    (new ScreenSubscriptionPaymentJob($payment->id))->handle($screener, app(FinalizeScreenedPayment::class));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed);
});

test('no match grants once consumes a coupon and records append only screening history', function () {
    $coupon = Coupon::factory()->create();
    $payment = screenablePayment($coupon);
    $redemption = CouponRedemption::create([
        'coupon_id' => $coupon->id,
        'user_id' => $payment->user_id,
        'subscription_payment_id' => $payment->id,
        'status' => CouponRedemptionStatus::Reserved,
        'discount_usd' => '1.25',
    ]);
    $job = new ScreenSubscriptionPaymentJob($payment->id);

    $job->handle(fixedScreener(ScreeningRisk::NoMatch), app(FinalizeScreenedPayment::class));
    $job->handle(fixedScreener(ScreeningRisk::NoMatch), app(FinalizeScreenedPayment::class));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->user->subscriptionGrants()->count())->toBe(1)
        ->and($redemption->fresh()->status)->toBe(CouponRedemptionStatus::Consumed)
        ->and($payment->screenings()->where('stage', ScreeningStage::Settlement->value)->count())->toBe(1);

    expect(fn () => $payment->screenings()->sole()->update(['provider' => 'rewritten']))
        ->toThrow(LogicException::class, 'append-only');

    Notification::assertSentTo($payment->user, SubscriptionActivatedNotification::class);
});

test('unknown or missing sender grants nothing and remains submitted for retry', function () {
    $unknown = screenablePayment();
    $missing = screenablePayment();
    $missing->forceFill(['from_address' => null])->save();

    (new ScreenSubscriptionPaymentJob($unknown->id))->handle(
        fixedScreener(ScreeningRisk::Unknown),
        app(FinalizeScreenedPayment::class),
    );
    (new ScreenSubscriptionPaymentJob($missing->id))->handle(
        fixedScreener(ScreeningRisk::NoMatch),
        app(FinalizeScreenedPayment::class),
    );

    expect($unknown->fresh()->status)->toBe(PaymentStatus::Submitted)
        ->and($unknown->fresh()->screening_risk)->toBe(ScreeningRisk::Unknown)
        ->and($missing->fresh()->status)->toBe(PaymentStatus::Submitted)
        ->and($missing->fresh()->screening_risk)->toBe(ScreeningRisk::Unknown)
        ->and($unknown->user->subscriptionGrants()->count())->toBe(0)
        ->and($missing->user->subscriptionGrants()->count())->toBe(0);
});

test('sanctioned payment permanently quarantines funds releases coupon and notifies both parties', function () {
    $admin = User::factory()->create(['email' => 'admin@example.test']);
    config()->set('app.admin_email', $admin->email);
    $coupon = Coupon::factory()->create();
    $payment = screenablePayment($coupon);
    $redemption = CouponRedemption::create([
        'coupon_id' => $coupon->id,
        'user_id' => $payment->user_id,
        'subscription_payment_id' => $payment->id,
        'status' => CouponRedemptionStatus::Reserved,
        'discount_usd' => '1.25',
    ]);

    (new ScreenSubscriptionPaymentJob($payment->id))->handle(
        fixedScreener(ScreeningRisk::Sanctioned),
        app(FinalizeScreenedPayment::class),
    );

    expect($payment->fresh()->status)->toBe(PaymentStatus::Quarantined)
        ->and($payment->fresh()->verified_at)->toBeNull()
        ->and($payment->depositAddress->fresh()->status)->toBe(DepositAddressStatus::Quarantined)
        ->and($redemption->fresh()->status)->toBe(CouponRedemptionStatus::Released)
        ->and($payment->user->subscriptionGrants()->count())->toBe(0);

    Notification::assertSentTo($payment->user, SubscriptionPaymentQuarantinedNotification::class);
    Notification::assertSentTo($admin, PaymentQuarantinedNotification::class);

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.billing.approve', $payment), ['note' => 'override attempt'])
        ->assertStatus(409);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Quarantined);
});
