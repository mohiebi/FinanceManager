<?php

use App\Actions\Billing\GrantProAccess;
use App\Actions\Billing\VerifyPaymentOnChain;
use App\Enums\GrantReason;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentNetwork;
use App\Enums\PaymentStatus;
use App\Exceptions\ExplorerUnavailable;
use App\Jobs\VerifySubscriptionPaymentJob;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\PaymentNeedsReviewNotification;
use App\Notifications\SubscriptionActivatedNotification;
use App\Notifications\SubscriptionPaymentFailedNotification;
use App\Services\Billing\ChainExplorerFactory;
use App\Support\Billing\TokenAmount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/** Six USDT, expressed the way a chain reports it. */
const USDT_AMOUNT_HEX = '0x5b8d80';

/** Ten ether in wei — past PHP_INT_MAX, which is exactly the point. */
const TEN_ETHER_HEX = '0x8ac7230489e80000';

const USDT_CONTRACT = '0xdac17f958d2ee523a2206206994597c13d831ec7';

beforeEach(function () {
    enableBilling();

    // Nothing in this file renders a page, so anything reaching the network here
    // would be a genuine call to a node — which no test may ever make.
    Http::preventStrayRequests();

    // Load-bearing, not tidiness. ResendChannel talks to the Resend SDK directly
    // rather than through Laravel's mailer, so neither Mail::fake() nor
    // Http::fake() intercepts it — without this, settling a payment in a test
    // sends a real email through the live API.
    Notification::fake();
});

/**
 * A payment expecting precisely what the given hex describes.
 *
 * Each one gets its own transaction hash: the unique index means two fixtures
 * sharing the factory default would collide, which is the index doing its job
 * rather than something to work around.
 */
function paymentExpecting(string $amountHex, int $decimals = 6, array $attributes = []): SubscriptionPayment
{
    static $sequence = 0;

    $factory = SubscriptionPayment::factory();

    if ($decimals === 18) {
        $factory = $factory->native();
    }

    return $factory->submitted()->create([
        'tx_hash' => '0x'.str_pad((string) ++$sequence, 64, 'a', STR_PAD_LEFT),
        'expected_amount' => TokenAmount::toDecimal(TokenAmount::fromHex($amountHex), $decimals),
        'pay_to_address' => mb_strtolower(TEST_RECEIVING_ADDRESS),
        ...$attributes,
    ]);
}

function verify(SubscriptionPayment $payment): void
{
    (new VerifySubscriptionPaymentJob($payment->id))->handle(
        app(VerifyPaymentOnChain::class),
        app(GrantProAccess::class),
    );

    passPaymentScreening($payment);
}

test('a matching token transfer settles the payment and grants the months', function () {
    $payment = paymentExpecting(USDT_AMOUNT_HEX, 6, ['months' => 3]);
    fakeEvmChain([
        'tx_to' => USDT_CONTRACT,
        'logs' => [evmTransferLog(USDT_CONTRACT, TEST_RECEIVING_ADDRESS, USDT_AMOUNT_HEX)],
    ]);

    verify($payment);

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->received_amount)->toBe('6.000000')
        ->and($payment->confirmations)->toBe(12)
        ->and($payment->block_number)->toBe(21_000_000)
        ->and($payment->from_address)->toBe('0x2222222222222222222222222222222222222222')
        ->and($payment->verified_at)->not->toBeNull()
        ->and($payment->user->fresh()->isPro())->toBeTrue();

    $grant = $payment->user->subscriptionGrants()->sole();

    expect($grant->months)->toBe(3)
        ->and($grant->reason)->toBe(GrantReason::Payment)
        ->and($grant->subscription_payment_id)->toBe($payment->id);

    Notification::assertSentTo($payment->user, SubscriptionActivatedNotification::class);
});

test('the buyer is told when a payment is refused for good', function () {
    $payment = paymentExpecting(USDT_AMOUNT_HEX);
    fakeEvmChain([
        'succeeded' => false,
        'tx_to' => USDT_CONTRACT,
        'logs' => [evmTransferLog(USDT_CONTRACT, TEST_RECEIVING_ADDRESS, USDT_AMOUNT_HEX)],
    ]);

    verify($payment);

    Notification::assertSentTo($payment->user, SubscriptionPaymentFailedNotification::class);
});

test('an outcome a human might reverse alerts the operator, not the buyer', function () {
    $admin = User::factory()->create(['email' => 'boss@example.com']);
    config()->set('app.admin_email', 'boss@example.com');

    // An exchange that deducted its fee from the amount sent. Real money, just
    // not the figure the intent expects — and quite possibly something the
    // operator will approve within the hour.
    $payment = paymentExpecting(USDT_AMOUNT_HEX);
    fakeEvmChain([
        'tx_to' => USDT_CONTRACT,
        'logs' => [evmTransferLog(USDT_CONTRACT, TEST_RECEIVING_ADDRESS, '0x5b8d7f')],
    ]);

    verify($payment);

    expect($payment->fresh()->failure_reason)->toBe(PaymentFailureReason::AmountMismatch);

    Notification::assertSentTo($admin, PaymentNeedsReviewNotification::class);

    // Telling the buyer their payment failed, only to reverse it, is worse than
    // telling them nothing while it is decided.
    Notification::assertNotSentTo($payment->user, SubscriptionPaymentFailedNotification::class);
});

test('one alert covers a whole burst of stranded payments', function () {
    $admin = User::factory()->create(['email' => 'boss@example.com']);
    config()->set('app.admin_email', 'boss@example.com');

    // A chain going down parks everything in flight at the same moment. Six
    // identical emails would be less useful than one saying six are waiting.
    // failed() makes no request of its own — it is the hook the queue calls once
    // the retries are spent — so no chain fake is needed here.
    foreach (range(1, 6) as $ignored) {
        $payment = paymentExpecting(USDT_AMOUNT_HEX);

        (new VerifySubscriptionPaymentJob($payment->id))->failed(new RuntimeException('gave up'));
    }

    Notification::assertSentToTimes($admin, PaymentNeedsReviewNotification::class, 1);
});

test('a different kind of problem is never buried by an ongoing outage', function () {
    $admin = User::factory()->create(['email' => 'boss@example.com']);
    config()->set('app.admin_email', 'boss@example.com');

    $stranded = paymentExpecting(USDT_AMOUNT_HEX);
    (new VerifySubscriptionPaymentJob($stranded->id))->failed(new RuntimeException('gave up'));

    $mismatched = paymentExpecting(USDT_AMOUNT_HEX);
    fakeEvmChain([
        'tx_to' => USDT_CONTRACT,
        'logs' => [evmTransferLog(USDT_CONTRACT, TEST_RECEIVING_ADDRESS, '0x5b8d7f')],
    ]);
    verify($mismatched);

    expect($stranded->fresh()->failure_reason)->toBe(PaymentFailureReason::ExplorerUnavailable)
        ->and($mismatched->fresh()->failure_reason)->toBe(PaymentFailureReason::AmountMismatch);

    // Debounced per reason, so an unreachable node cannot silence a genuine
    // amount problem sitting behind it.
    Notification::assertSentToTimes($admin, PaymentNeedsReviewNotification::class, 2);
});

test('a definitive refusal still goes to the buyer and not the operator', function () {
    $admin = User::factory()->create(['email' => 'boss@example.com']);
    config()->set('app.admin_email', 'boss@example.com');

    // A reverted transaction is not a judgement call — nothing an operator
    // could decide would make it a payment.
    $payment = paymentExpecting(USDT_AMOUNT_HEX);
    fakeEvmChain([
        'succeeded' => false,
        'tx_to' => USDT_CONTRACT,
        'logs' => [evmTransferLog(USDT_CONTRACT, TEST_RECEIVING_ADDRESS, USDT_AMOUNT_HEX)],
    ]);

    verify($payment);

    Notification::assertSentTo($payment->user, SubscriptionPaymentFailedNotification::class);
    Notification::assertNotSentTo($admin, PaymentNeedsReviewNotification::class);
});

test('no configured admin means no alert and no crash', function () {
    config()->set('app.admin_email', '');

    $payment = paymentExpecting(USDT_AMOUNT_HEX);

    (new VerifySubscriptionPaymentJob($payment->id))->failed(new RuntimeException('gave up'));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Submitted);
    Notification::assertNothingSent();
});

test('nothing is said while a payment might still settle', function () {
    // Telling somebody their money failed while we are still deciding would be
    // worse than saying nothing — and an outage is not a verdict.
    $payment = paymentExpecting(USDT_AMOUNT_HEX);
    Http::fake(['*' => Http::response('nope', 500)]);

    verify($payment);

    Notification::assertNothingSent();
});

test('a matching native transfer settles too', function () {
    $payment = paymentExpecting(TEN_ETHER_HEX, 18);
    fakeEvmChain(['value' => TEN_ETHER_HEX]);

    verify($payment);

    // Ten ether in wei exceeds PHP_INT_MAX. A cast anywhere in this path would
    // turn the amount into a float and the comparison into a coin toss.
    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->fresh()->received_amount)->toBe('10.000000000000000000');
});

test('a token nobody has to pay for cannot buy a subscription', function () {
    $payment = paymentExpecting(USDT_AMOUNT_HEX);

    // A perfectly-shaped Transfer event, to the right address, for the right
    // amount — emitted by a contract anyone can deploy for nothing. The only
    // thing separating it from real money is which address logged it.
    fakeEvmChain([
        'tx_to' => '0x9999999999999999999999999999999999999999',
        'logs' => [evmTransferLog(
            '0x9999999999999999999999999999999999999999',
            TEST_RECEIVING_ADDRESS,
            USDT_AMOUNT_HEX,
        )],
    ]);

    verify($payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->fresh()->failure_reason)->toBe(PaymentFailureReason::WrongToken)
        ->and($payment->fresh()->user->fresh()->isPro())->toBeFalse();
});

test('several transfers in one transaction are added together', function () {
    // 0x2dc6c0 + 0x2dc6c0 = 0x5b8d80, the expected six USDT.
    $payment = paymentExpecting(USDT_AMOUNT_HEX);
    fakeEvmChain([
        'tx_to' => USDT_CONTRACT,
        'logs' => [
            evmTransferLog(USDT_CONTRACT, TEST_RECEIVING_ADDRESS, '0x2dc6c0'),
            evmTransferLog(USDT_CONTRACT, TEST_RECEIVING_ADDRESS, '0x2dc6c0'),
        ],
    ]);

    verify($payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed);
});

test('checksum-cased addresses from the chain still match', function () {
    $payment = paymentExpecting(USDT_AMOUNT_HEX);

    // EIP-55 casing is a display convention. Comparing case-sensitively would
    // reject a perfectly good payment.
    fakeEvmChain([
        'tx_to' => mb_strtoupper('0xDAC17F958D2EE523A2206206994597C13D831EC7'),
        'logs' => [evmTransferLog(
            '0xDAC17F958D2EE523A2206206994597C13D831EC7',
            '0x1111111111111111111111111111111111111111',
            USDT_AMOUNT_HEX,
        )],
    ]);

    verify($payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed);
});

test('a transfer to somebody else is refused', function () {
    $payment = paymentExpecting(USDT_AMOUNT_HEX);
    fakeEvmChain([
        'tx_to' => USDT_CONTRACT,
        'logs' => [evmTransferLog(
            USDT_CONTRACT,
            '0x8888888888888888888888888888888888888888',
            USDT_AMOUNT_HEX,
        )],
    ]);

    verify($payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->fresh()->failure_reason)->toBe(PaymentFailureReason::WrongRecipient);
});

test('a reverted transaction is refused', function () {
    $payment = paymentExpecting(USDT_AMOUNT_HEX);
    fakeEvmChain([
        'succeeded' => false,
        'tx_to' => USDT_CONTRACT,
        'logs' => [evmTransferLog(USDT_CONTRACT, TEST_RECEIVING_ADDRESS, USDT_AMOUNT_HEX)],
    ]);

    verify($payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->fresh()->failure_reason)->toBe(PaymentFailureReason::Reverted);
});

test('ether moved inside a contract goes to a human, not to a refusal', function () {
    $payment = paymentExpecting(TEN_ETHER_HEX, 18);

    // A smart-contract wallet or an exchange withdrawal: `to` is the contract,
    // the ether reached us internally, and a receipt cannot show that. Real
    // money, genuinely paid, which we simply cannot read.
    fakeEvmChain([
        'tx_to' => '0x7777777777777777777777777777777777777777',
        'value' => '0x0',
        'logs' => [evmTransferLog(
            '0x7777777777777777777777777777777777777777',
            '0x7777777777777777777777777777777777777777',
            '0x1',
        )],
    ]);

    verify($payment);

    expect($payment->fresh()->failure_reason)
        ->toBe(PaymentFailureReason::NativeTransferNotVisible)
        ->and($payment->fresh()->failure_reason->needsReview())->toBeTrue();
});

test('a short payment needs review', function () {
    $payment = paymentExpecting(USDT_AMOUNT_HEX);
    fakeEvmChain([
        'tx_to' => USDT_CONTRACT,
        'logs' => [evmTransferLog(USDT_CONTRACT, TEST_RECEIVING_ADDRESS, '0x5b8d7f')],
    ]);

    verify($payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->fresh()->failure_reason)->toBe(PaymentFailureReason::AmountMismatch)
        ->and($payment->fresh()->failure_reason->needsReview())->toBeTrue();
});

test('an overpayment is accepted and records the complete credited amount', function () {
    $payment = paymentExpecting(USDT_AMOUNT_HEX);
    fakeEvmChain([
        'tx_to' => USDT_CONTRACT,
        'logs' => [evmTransferLog(USDT_CONTRACT, TEST_RECEIVING_ADDRESS, '0x5b8d81')],
    ]);

    verify($payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->fresh()->received_amount)->toBe('6.000001')
        ->and($payment->user->fresh()->isPro())->toBeTrue();
});

test('a transaction mined before the intent existed cannot be claimed', function () {
    $payment = paymentExpecting(USDT_AMOUNT_HEX);
    fakeEvmChain([
        'tx_to' => USDT_CONTRACT,
        'timestamp' => now()->subHour(),
        'logs' => [evmTransferLog(USDT_CONTRACT, TEST_RECEIVING_ADDRESS, USDT_AMOUNT_HEX)],
    ]);

    verify($payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->fresh()->failure_reason)->toBe(PaymentFailureReason::TxTooOld);
});

test('a payment made after its quote lapsed goes to review', function () {
    $payment = paymentExpecting(USDT_AMOUNT_HEX, 6, [
        'quote_expires_at' => now()->subMinutes(10),
    ]);
    fakeEvmChain([
        'tx_to' => USDT_CONTRACT,
        'logs' => [evmTransferLog(USDT_CONTRACT, TEST_RECEIVING_ADDRESS, USDT_AMOUNT_HEX)],
    ]);

    verify($payment);

    expect($payment->fresh()->failure_reason)->toBe(PaymentFailureReason::QuoteExpired)
        ->and($payment->fresh()->failure_reason->needsReview())->toBeTrue();
});

test('a transfer waits until it is buried deep enough', function () {
    $payment = paymentExpecting(USDT_AMOUNT_HEX);
    fakeEvmChain([
        'tx_to' => USDT_CONTRACT,
        'confirmations' => 3,
        'logs' => [evmTransferLog(USDT_CONTRACT, TEST_RECEIVING_ADDRESS, USDT_AMOUNT_HEX)],
    ]);

    verify($payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Submitted)
        ->and($payment->fresh()->confirmations)->toBe(3)
        ->and($payment->fresh()->attempts)->toBe(1)
        ->and($payment->fresh()->user->fresh()->isPro())->toBeFalse();
});

test('a transaction not yet mined is waited for, not refused', function () {
    $payment = paymentExpecting(USDT_AMOUNT_HEX);
    fakeEvmChain(['found' => false]);

    verify($payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Submitted)
        ->and($payment->fresh()->failure_reason)->toBe(PaymentFailureReason::TxNotFound);
});

test('an unreachable chain never fails a payment', function () {
    // The single most important rule here: our node being down must not spend
    // somebody else's money.
    $cases = [
        'server error' => fn () => Http::fake(['*' => Http::response('nope', 500)]),
        'rate limited' => fn () => Http::fake(['*' => Http::response('slow down', 429)]),
        'connection refused' => fn () => Http::fake(fn () => throw new ConnectionException('refused')),
        'json-rpc error' => fn () => Http::fake(['*' => Http::response([
            ['jsonrpc' => '2.0', 'id' => 0, 'error' => ['code' => -32000, 'message' => 'busy']],
        ])]),
        'unreadable body' => fn () => Http::fake(['*' => Http::response('<html>maintenance</html>')]),
    ];

    foreach ($cases as $label => $arrange) {
        $payment = paymentExpecting(USDT_AMOUNT_HEX);
        $arrange();

        verify($payment);

        expect($payment->fresh()->status)->toBe(PaymentStatus::Submitted, $label)
            ->and($payment->fresh()->failure_reason)
            ->toBe(PaymentFailureReason::ExplorerUnavailable, $label)
            ->and($payment->fresh()->user->fresh()->isPro())->toBeFalse();
    }
});

test('an endpoint pointed at the wrong chain is our problem, not the buyer\'s', function () {
    $payment = paymentExpecting(USDT_AMOUNT_HEX);

    // A misconfigured RPC URL reporting some other network. Treated as
    // unavailability rather than a rejected payment: the buyer did nothing wrong,
    // and a config mistake must not cost them.
    fakeEvmChain([
        'chain_id' => 137,
        'tx_to' => USDT_CONTRACT,
        'logs' => [evmTransferLog(USDT_CONTRACT, TEST_RECEIVING_ADDRESS, USDT_AMOUNT_HEX)],
    ]);

    verify($payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Submitted)
        ->and($payment->fresh()->failure_reason)->toBe(PaymentFailureReason::ExplorerUnavailable);
});

test('running out of attempts leaves the payment open for a human', function () {
    $payment = paymentExpecting(USDT_AMOUNT_HEX);

    (new VerifySubscriptionPaymentJob($payment->id))->failed(new RuntimeException('gave up'));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Submitted)
        ->and($payment->fresh()->failure_reason)->toBe(PaymentFailureReason::ExplorerUnavailable);
});

test('settling twice grants once', function () {
    $payment = paymentExpecting(USDT_AMOUNT_HEX, 6, ['months' => 1]);
    fakeEvmChain([
        'tx_to' => USDT_CONTRACT,
        'logs' => [evmTransferLog(USDT_CONTRACT, TEST_RECEIVING_ADDRESS, USDT_AMOUNT_HEX)],
    ]);

    verify($payment);
    $afterFirst = $payment->user->fresh()->pro_until;

    verify($payment->fresh());

    expect($payment->user->fresh()->pro_until->toDateTimeString())
        ->toBe($afterFirst->toDateTimeString())
        ->and($payment->user->subscriptionGrants()->count())->toBe(1);
});

test('two payments by one buyer stack their months', function () {
    $user = User::factory()->create();

    foreach (['0x5b8d80', '0x5b8d80'] as $index => $hex) {
        $payment = paymentExpecting($hex, 6, [
            'user_id' => $user->id,
            'months' => 1,
        ]);

        fakeEvmChain([
            'tx_to' => USDT_CONTRACT,
            'block' => 21_000_000 + $index,
            'logs' => [evmTransferLog(USDT_CONTRACT, TEST_RECEIVING_ADDRESS, $hex)],
        ]);

        verify($payment);
    }

    expect($user->fresh()->pro_until->toDateString())
        ->toBe(now()->addMonthsNoOverflow(2)->toDateString())
        ->and($user->subscriptionGrants()->count())->toBe(2);
});

test('a payment that is no longer awaiting the chain is left alone', function () {
    foreach ([PaymentStatus::Confirmed, PaymentStatus::Expired, PaymentStatus::Failed] as $status) {
        $payment = SubscriptionPayment::factory()->create(['status' => $status]);
        Http::fake();

        verify($payment);

        expect($payment->fresh()->status)->toBe($status);
        Http::assertNothingSent();
    }
});

test('an rpc failover that exhausts every endpoint names what each one answered', function () {
    // Failing over used to keep only thrown exceptions, so a run where every
    // endpoint rate-limited raised an error naming no status and carrying no
    // previous — leaving 429s and a genuine outage indistinguishable.
    enableBilling([
        'billing.networks.ethereum.rpc_urls' => [
            'https://rpc-one.test/v2/super-secret-provider-key',
            'https://rpc-two.test/rpc',
        ],
    ]);

    Http::fake([
        'rpc-one.test/*' => Http::response(['error' => 'rate limited'], 429),
        'rpc-two.test/*' => Http::response('gateway down', 502),
    ]);

    $explorer = app(ChainExplorerFactory::class)->for(PaymentNetwork::Ethereum);

    try {
        $explorer->transferFor(paymentExpecting(USDT_AMOUNT_HEX));
        $this->fail('The explorer should have refused after exhausting every endpoint.');
    } catch (ExplorerUnavailable $exception) {
        expect($exception->getMessage())
            ->toContain('rpc-one.test answered 429')
            ->toContain('rpc-two.test answered 502')
            // Hosts only: a keyed provider carries its credential in the path,
            // and this message reaches the log.
            ->not->toContain('super-secret-provider-key');
    }
});
