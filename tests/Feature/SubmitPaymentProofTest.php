<?php

use App\Actions\Billing\SubmitPaymentProof;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentStatus;
use App\Jobs\VerifySubscriptionPaymentJob;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    enableBilling();

    // The queue is synchronous under test, so without this every accepted claim
    // would run a real verification inline — which is a different subject, tested
    // in VerifySubscriptionPaymentJobTest.
    Queue::fake();
});

test('claiming a transaction moves the payment on to be checked', function () {
    $payment = SubscriptionPayment::factory()->create();
    $hash = '0x'.str_repeat('a', 64);

    $result = app(SubmitPaymentProof::class)($payment, $hash);

    expect($result->accepted)->toBeTrue()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Submitted)
        ->and($payment->fresh()->tx_hash)->toBe($hash)
        ->and($payment->fresh()->submitted_at)->not->toBeNull();

    Queue::assertPushed(
        VerifySubscriptionPaymentJob::class,
        fn (VerifySubscriptionPaymentJob $job): bool => $job->paymentId === $payment->id
            && $job->queue === 'billing',
    );
});

test('a refused claim queues nothing', function () {
    $payment = SubscriptionPayment::factory()->stale()->create();

    app(SubmitPaymentProof::class)($payment, '0x'.str_repeat('a', 64));

    Queue::assertNothingPushed();
});

test('a hash is normalized before it is stored', function () {
    $payment = SubscriptionPayment::factory()->create();

    // Mixed case, and without the prefix — both things a buyer pasting from a
    // block explorer will actually do.
    app(SubmitPaymentProof::class)($payment, '  '.str_repeat('A', 64).'  ');

    expect($payment->fresh()->tx_hash)->toBe('0x'.str_repeat('a', 64));
});

test('two payments cannot claim one transaction', function () {
    $hash = '0x'.str_repeat('d', 64);
    $first = SubscriptionPayment::factory()->create();
    $second = SubscriptionPayment::factory()->create();

    expect(app(SubmitPaymentProof::class)($first, $hash)->accepted)->toBeTrue();

    $result = app(SubmitPaymentProof::class)($second, $hash);

    // Refused by the unique index, not by a lookup — a check followed by a write
    // leaves a window for both to pass, and this is the one primitive stopping
    // somebody claiming a stranger's transaction.
    expect($result->accepted)->toBeFalse()
        ->and($result->reason)->toBe(PaymentFailureReason::AlreadyClaimed)
        ->and($second->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($second->fresh()->tx_hash)->toBeNull();
});

test('the same hash may be reused on a different chain', function () {
    // A guard against the unique index being narrowed to the hash alone: chains
    // have independent hash spaces.
    $hash = '0x'.str_repeat('e', 64);

    SubscriptionPayment::factory()->create(['tx_hash' => $hash]);
    $other = SubscriptionPayment::factory()->create(['network' => null, 'chain_id' => null]);

    expect(fn () => $other->forceFill(['tx_hash' => $hash])->save())->not->toThrow(Exception::class);
});

test('many open intents can coexist without a hash', function () {
    SubscriptionPayment::factory()->count(5)->create();

    expect(SubscriptionPayment::query()->whereNull('tx_hash')->count())->toBe(5);
});

test('a malformed hash never reaches the database', function () {
    $payment = SubscriptionPayment::factory()->create();

    foreach ([
        '0x'.str_repeat('a', 63),
        '0x'.str_repeat('a', 65),
        '0x'.str_repeat('z', 64),
        'not-a-hash',
        '',
    ] as $malformed) {
        $result = app(SubmitPaymentProof::class)($payment, $malformed);

        expect($result->accepted)->toBeFalse()
            ->and($result->reason)->toBe(PaymentFailureReason::TxNotFound);
    }

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->tx_hash)->toBeNull();
});

test('an intent past its window can no longer be paid against', function () {
    $payment = SubscriptionPayment::factory()->stale()->create();

    $result = app(SubmitPaymentProof::class)($payment, '0x'.str_repeat('a', 64));

    expect($result->accepted)->toBeFalse()
        ->and($result->reason)->toBe(PaymentFailureReason::Expired);
});

test('a payment whose quote has lapsed is refused before it is claimed', function () {
    $payment = SubscriptionPayment::factory()->create([
        'quote_expires_at' => now()->subMinute(),
    ]);

    $result = app(SubmitPaymentProof::class)($payment, '0x'.str_repeat('a', 64));

    expect($result->accepted)->toBeFalse()
        ->and($result->reason)->toBe(PaymentFailureReason::QuoteExpired);
});

test('claiming again after a rejected attempt still works', function () {
    $payment = SubscriptionPayment::factory()->create();

    app(SubmitPaymentProof::class)($payment, 'nonsense');
    $result = app(SubmitPaymentProof::class)($payment, '0x'.str_repeat('f', 64));

    expect($result->accepted)->toBeTrue()
        ->and($payment->fresh()->attempts)->toBe(0)
        ->and($payment->fresh()->failure_reason)->toBeNull();
});

test('an admin-created payment has no chain to claim against', function () {
    $payment = SubscriptionPayment::factory()->create([
        'network' => null,
        'chain_id' => null,
    ]);

    $result = app(SubmitPaymentProof::class)($payment, '0x'.str_repeat('a', 64));

    expect($result->accepted)->toBeFalse()
        ->and($result->reason)->toBe(PaymentFailureReason::WrongChain);
});

test('a user with an armed vault can still pay', function () {
    // Billing rows are plaintext by design, so nothing here needs a data key.
    // A queue worker settling a payment has no browser to ask for one.
    $user = User::factory()->create();
    $user->ensureEncryptionKey();
    $user->encryptionKey()->update(['wrapped_dek_server' => null]);

    $payment = SubscriptionPayment::factory()->create(['user_id' => $user->id]);

    expect(app(SubmitPaymentProof::class)($payment, '0x'.str_repeat('a', 64))->accepted)->toBeTrue()
        ->and($user->fresh()->vaultIsArmed())->toBeTrue();
});
