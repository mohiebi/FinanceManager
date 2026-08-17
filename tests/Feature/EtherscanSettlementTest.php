<?php

use App\Actions\Billing\GrantProAccess;
use App\Actions\Billing\VerifyPaymentOnChain;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentStatus;
use App\Jobs\VerifySubscriptionPaymentJob;
use App\Models\SubscriptionPayment;
use App\Support\Billing\TokenAmount;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/** Ten ether in wei, decimal — the shape Etherscan reports internal values in. */
const TEN_ETHER_WEI = '10000000000000000000';

const CONTRACT_SENDER = '0x7777777777777777777777777777777777777777';

beforeEach(function () {
    enableBilling();
    Http::preventStrayRequests();
    Notification::fake();

    config()->set([
        'billing.etherscan.enabled' => true,
        'billing.etherscan.url' => 'https://etherscan.test/v2/api',
        'billing.etherscan.api_key' => 'test-key',
    ]);
});

/**
 * A native payment expecting exactly ten ether.
 */
function nativePayment(array $attributes = []): SubscriptionPayment
{
    static $sequence = 0;

    return SubscriptionPayment::factory()->native()->submitted()->create([
        'tx_hash' => '0x'.str_pad((string) ++$sequence, 64, 'e', STR_PAD_LEFT),
        'expected_amount' => TokenAmount::toDecimal(TEN_ETHER_WEI, 18),
        'pay_to_address' => mb_strtolower(TEST_RECEIVING_ADDRESS),
        ...$attributes,
    ]);
}

function settle(SubscriptionPayment $payment): void
{
    (new VerifySubscriptionPaymentJob($payment->id))->handle(
        app(VerifyPaymentOnChain::class),
        app(GrantProAccess::class),
    );

    passPaymentScreening($payment);
}

test('ether forwarded by a contract now settles on its own', function () {
    $payment = nativePayment();

    // The exact case that used to need a human every time: an exchange
    // withdrawal where `to` is the exchange's contract and the ether reaches us
    // internally, leaving nothing in the receipt to read.
    fakeEvmChain([
        'tx_to' => CONTRACT_SENDER,
        'value' => '0x0',
        'internal' => [[
            'to' => mb_strtolower(TEST_RECEIVING_ADDRESS),
            'value' => TEN_ETHER_WEI,
            'isError' => '0',
        ]],
    ]);

    settle($payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->fresh()->received_amount)->toBe('10.000000000000000000')
        ->and($payment->user->fresh()->isPro())->toBeTrue();
});

test('several internal transfers in one transaction are added together', function () {
    $payment = nativePayment();

    fakeEvmChain([
        'tx_to' => CONTRACT_SENDER,
        'value' => '0x0',
        'internal' => [
            ['to' => mb_strtolower(TEST_RECEIVING_ADDRESS), 'value' => '6000000000000000000', 'isError' => '0'],
            ['to' => mb_strtolower(TEST_RECEIVING_ADDRESS), 'value' => '4000000000000000000', 'isError' => '0'],
        ],
    ]);

    settle($payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed);
});

test('a failed internal call moved nothing and is not counted', function () {
    $payment = nativePayment();

    fakeEvmChain([
        'tx_to' => CONTRACT_SENDER,
        'value' => '0x0',
        'internal' => [
            ['to' => mb_strtolower(TEST_RECEIVING_ADDRESS), 'value' => TEN_ETHER_WEI, 'isError' => '1'],
        ],
    ]);

    settle($payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed);
});

test('internal transfers to somebody else are not counted', function () {
    $payment = nativePayment();

    fakeEvmChain([
        'tx_to' => CONTRACT_SENDER,
        'value' => '0x0',
        'internal' => [
            ['to' => '0x8888888888888888888888888888888888888888', 'value' => TEN_ETHER_WEI, 'isError' => '0'],
        ],
    ]);

    settle($payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->fresh()->failure_reason)->toBe(PaymentFailureReason::WrongRecipient);
});

test('the amount still has to match exactly, trace or no trace', function () {
    // Etherscan changes what we can see, never what counts as payment. The
    // nonce guard is untouched.
    $payment = nativePayment();

    fakeEvmChain([
        'tx_to' => CONTRACT_SENDER,
        'value' => '0x0',
        'internal' => [['to' => mb_strtolower(TEST_RECEIVING_ADDRESS), 'value' => '9999999999999999999', 'isError' => '0']],
    ]);

    settle($payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->fresh()->failure_reason)->toBe(PaymentFailureReason::AmountMismatch);
});

test('a transaction with no internal transfers is still refused', function () {
    $payment = nativePayment();

    fakeEvmChain(['tx_to' => CONTRACT_SENDER, 'value' => '0x0']);

    settle($payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed);
});

test('a rate-limited Etherscan never fails a payment', function () {
    $payment = nativePayment();

    fakeEvmChain([
        'tx_to' => CONTRACT_SENDER,
        'value' => '0x0',
        'etherscan' => fn () => Http::response([
            'status' => '0',
            'message' => 'NOTOK',
            'result' => 'Max rate limit reached',
        ]),
    ]);

    settle($payment);

    // A quota we blew through is our problem, and is emphatically not a verdict
    // about whether somebody paid.
    expect($payment->fresh()->status)->toBe(PaymentStatus::Submitted)
        ->and($payment->fresh()->failure_reason)->toBe(PaymentFailureReason::ExplorerUnavailable);
});

test('a direct transfer never bothers asking Etherscan', function () {
    $payment = nativePayment();
    fakeEvmChain(['value' => '0x8ac7230489e80000']);

    settle($payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed);

    // A plain wallet-to-wallet send has no trace worth reading, and the quota
    // is finite.
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'etherscan'));
});

test('without a key the behaviour is exactly as before', function () {
    config()->set('billing.etherscan.enabled', false);

    $payment = nativePayment();
    fakeEvmChain(['tx_to' => CONTRACT_SENDER, 'value' => '0x0', 'logs' => [
        evmTransferLog(CONTRACT_SENDER, CONTRACT_SENDER, '0x1'),
    ]]);

    settle($payment);

    expect($payment->fresh()->failure_reason)
        ->toBe(PaymentFailureReason::NativeTransferNotVisible);
});
