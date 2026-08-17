<?php

use App\Enums\GrantReason;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentStatus;
use App\Jobs\VerifySubscriptionPaymentJob;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    enableBilling();
    Queue::fake();
    config()->set('app.admin_email', 'boss@example.com');
});

function admin(): User
{
    // Password confirmation guards every mutating route, so a test acting as the
    // admin has to have confirmed recently — the same posture as vault enrolment.
    $admin = User::factory()->create(['email' => 'boss@example.com']);

    test()->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

    return $admin;
}

test('the billing console is admin-only, on every route', function () {
    $payment = SubscriptionPayment::factory()->submitted()->create();
    $stranger = User::factory()->create();

    $routes = [
        ['get', route('admin.billing'), []],
        ['post', route('admin.billing.approve', $payment), ['note' => 'nope']],
        ['post', route('admin.billing.reject', $payment), ['note' => 'nope']],
        ['post', route('admin.billing.recheck', $payment), []],
        ['post', route('admin.billing.grant', $stranger), ['months' => 1, 'note' => 'nope']],
        ['post', route('admin.billing.revoke', $stranger), ['note' => 'nope']],
    ];

    foreach ($routes as [$method, $url, $data]) {
        $this->actingAs($stranger)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->{$method}($url, $data)
            ->assertForbidden();
    }

    expect($stranger->fresh()->isPro())->toBeFalse()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Submitted);
});

test('the console lists what needs a decision', function () {
    admin();

    $stranded = SubscriptionPayment::factory()->submitted()->create([
        'failure_reason' => PaymentFailureReason::ExplorerUnavailable,
    ]);
    SubscriptionPayment::factory()->confirmed()->create();
    User::factory()->pro()->create();

    $this->get(route('admin.billing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Billing')
            ->where('counts.pro_users', 1)
            ->has('needsAttention', 1)
            ->where('needsAttention.0.id', $stranded->id)
            ->has('proUsers', 1)
        );
});

test('approving a chain anomaly sends it to screening and only no match grants', function () {
    $boss = admin();
    $payment = SubscriptionPayment::factory()->submitted()->create([
        'months' => 3,
        'failure_reason' => PaymentFailureReason::AmountMismatch,
        'from_address' => '0x2222222222222222222222222222222222222222',
        'received_amount' => '4.90',
    ]);

    $this->post(route('admin.billing.approve', $payment), [
        'note' => 'Exchange took its fee out of the amount.',
    ])->assertRedirect();

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Submitted)
        ->and($payment->approved_by_admin_id)->toBe($boss->id)
        ->and($payment->failure_reason)->toBeNull()
        ->and($payment->chain_verified_at)->not->toBeNull()
        ->and($payment->user->fresh()->isPro())->toBeFalse();

    passPaymentScreening($payment);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->user->fresh()->isPro())->toBeTrue();

    $grant = $payment->user->subscriptionGrants()->sole();

    expect($grant->reason)->toBe(GrantReason::Payment)
        ->and($grant->months)->toBe(3)
        ->and($grant->granted_by_admin_id)->toBeNull();
});

test('every decision has to be explained', function () {
    admin();
    $payment = SubscriptionPayment::factory()->submitted()->create();
    $user = User::factory()->create();

    $this->post(route('admin.billing.approve', $payment), ['note' => ''])
        ->assertSessionHasErrors('note');

    $this->post(route('admin.billing.reject', $payment), [])
        ->assertSessionHasErrors('note');

    $this->post(route('admin.billing.grant', $user), ['months' => 1])
        ->assertSessionHasErrors('note');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Submitted)
        ->and($user->fresh()->isPro())->toBeFalse();
});

test('an already settled payment cannot be approved twice', function () {
    admin();
    $payment = SubscriptionPayment::factory()->confirmed()->create();

    $this->post(route('admin.billing.approve', $payment), ['note' => 'again'])
        ->assertStatus(409);

    expect($payment->user->subscriptionGrants()->count())->toBe(0);
});

test('rejecting closes the payment without touching entitlement', function () {
    admin();
    $payment = SubscriptionPayment::factory()->submitted()->create();

    $this->post(route('admin.billing.reject', $payment), ['note' => 'Paid on the wrong chain.'])
        ->assertRedirect();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->fresh()->failure_reason)->toBe(PaymentFailureReason::AdminRejected)
        ->and($payment->user->fresh()->isPro())->toBeFalse();
});

test('a recheck re-queues the chain lookup', function () {
    admin();
    $payment = SubscriptionPayment::factory()->submitted()->create();

    $this->post(route('admin.billing.recheck', $payment))->assertRedirect();

    Queue::assertPushed(
        VerifySubscriptionPaymentJob::class,
        fn (VerifySubscriptionPaymentJob $job): bool => $job->paymentId === $payment->id,
    );
});

test('granting and revoking by hand both leave a trail', function () {
    $boss = admin();
    $user = User::factory()->create();

    $this->post(route('admin.billing.grant', $user), ['months' => 6, 'note' => 'Comped for support.'])
        ->assertRedirect();

    expect($user->fresh()->isPro())->toBeTrue();

    $this->post(route('admin.billing.revoke', $user), ['note' => 'Refunded.'])
        ->assertRedirect();

    expect($user->fresh()->isPro())->toBeFalse()
        // Not nulled: the row still records that they were Pro, and until when.
        ->and($user->fresh()->pro_until)->not->toBeNull();

    $reasons = $user->subscriptionGrants()->orderBy('created_at')->pluck('reason');

    expect($reasons->all())->toBe([GrantReason::AdminGrant, GrantReason::AdminRevoke])
        ->and($user->subscriptionGrants()->where('granted_by_admin_id', $boss->id)->count())->toBe(2);
});

test('a nonsensical grant length is refused', function () {
    admin();
    $user = User::factory()->create();

    foreach ([0, -3, 999] as $months) {
        $this->post(route('admin.billing.grant', $user), ['months' => $months, 'note' => 'testing'])
            ->assertSessionHasErrors('months');
    }

    expect($user->fresh()->isPro())->toBeFalse();
});

test('mutating routes demand a fresh password confirmation', function () {
    $boss = User::factory()->create(['email' => 'boss@example.com']);
    $payment = SubscriptionPayment::factory()->submitted()->create();

    // Admin, but without a recent confirmation: the admin check is one string
    // compare, which is thin protection for handing out paid entitlements.
    $this->actingAs($boss)
        ->post(route('admin.billing.approve', $payment), ['note' => 'no confirmation'])
        ->assertRedirect(route('password.confirm'));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Submitted);
});
