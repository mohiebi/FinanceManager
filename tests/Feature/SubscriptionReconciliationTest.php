<?php

use App\Enums\PaymentFailureReason;
use App\Enums\PaymentStatus;
use App\Jobs\ReconcileSubscriptionsJob;
use App\Jobs\VerifySubscriptionPaymentJob;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\SubscriptionExpiredNotification;
use App\Notifications\SubscriptionExpiringNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    enableBilling();
    Notification::fake();
    Queue::fake();
});

function reconcile(): void
{
    (new ReconcileSubscriptionsJob)->handle();
}

test('an intent nobody paid is closed once its window shuts', function () {
    $stale = SubscriptionPayment::factory()->stale()->create();
    $live = SubscriptionPayment::factory()->create();

    reconcile();

    expect($stale->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($stale->fresh()->failure_reason)->toBe(PaymentFailureReason::Expired)
        ->and($live->fresh()->status)->toBe(PaymentStatus::Pending);
});

test('a verification a restarted worker dropped is picked back up', function () {
    // Without this, a buyer whose money is already spent would sit in "checking"
    // forever because a queue:restart happened to land mid-flight.
    $stalled = SubscriptionPayment::factory()->submitted()->create();
    $stalled->forceFill(['updated_at' => now()->subHour()])->saveQuietly();

    $fresh = SubscriptionPayment::factory()->submitted('0x'.str_repeat('9', 64))->create();

    reconcile();

    Queue::assertPushed(
        VerifySubscriptionPaymentJob::class,
        fn (VerifySubscriptionPaymentJob $job): bool => $job->paymentId === $stalled->id,
    );

    Queue::assertNotPushed(
        VerifySubscriptionPaymentJob::class,
        fn (VerifySubscriptionPaymentJob $job): bool => $job->paymentId === $fresh->id,
    );
});

test('a payment too old to still be settling is left for a human', function () {
    $ancient = SubscriptionPayment::factory()->submitted()->create();
    $ancient->forceFill([
        'created_at' => now()->subDays(30),
        'updated_at' => now()->subDays(30),
    ])->saveQuietly();

    reconcile();

    Queue::assertNothingPushed();
});

test('an expiry warning goes out once, and re-arms itself on renewal', function () {
    $user = User::factory()->pro(now()->addDays(3))->create();

    reconcile();
    reconcile();

    Notification::assertSentToTimes($user, SubscriptionExpiringNotification::class, 1);
    expect($user->fresh()->pro_expiry_warned_for->toDateTimeString())
        ->toBe($user->fresh()->pro_until->toDateTimeString());

    // Renewing moves pro_until, so the marker no longer matches it and the next
    // expiry earns its own warning — with no reset step anywhere.
    $user->forceFill(['pro_until' => now()->addDays(2)->addMonths(1)])->save();
    reconcile();
    Notification::assertSentToTimes($user, SubscriptionExpiringNotification::class, 1);

    $user->forceFill(['pro_until' => now()->addDays(4)])->save();
    reconcile();
    Notification::assertSentToTimes($user, SubscriptionExpiringNotification::class, 2);
});

test('nobody is warned too early, and nobody free is warned at all', function () {
    $farOff = User::factory()->pro(now()->addMonths(6))->create();
    $free = User::factory()->create();

    reconcile();

    Notification::assertNothingSentTo($farOff);
    Notification::assertNothingSentTo($free);
});

test('a lapsed user is told once', function () {
    $user = User::factory()->proExpired()->create();

    reconcile();
    reconcile();

    Notification::assertSentToTimes($user, SubscriptionExpiredNotification::class, 1);
    expect($user->fresh()->isPro())->toBeFalse();
});

test('somebody who lapsed long ago is left in peace', function () {
    $user = User::factory()->pro(now()->subMonths(4))->create();

    reconcile();

    Notification::assertNothingSentTo($user);
});

test('the sweep never has to take an entitlement away', function () {
    // pro_until is compared against the clock on every read, so expiry needs no
    // job to enforce it. This asserts the column is genuinely left alone — if a
    // downgrade pass ever appears, it will be because somebody added one.
    $user = User::factory()->pro(now()->addDay())->create();
    $before = $user->pro_until;

    reconcile();

    expect($user->fresh()->pro_until->toDateTimeString())->toBe($before->toDateTimeString());
});
