<?php

use App\Actions\Billing\GrantProAccess;
use App\Enums\GrantReason;
use App\Enums\PaymentStatus;
use App\Models\DepositAddress;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    enableBilling();

    // Settling a claim is a separate subject; here we only care that the claim
    // was taken. Without this the synchronous test queue would verify inline.
    Queue::fake();
});

test('the billing page offers every configured plan and rail', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('billing.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Billing')
            ->has('plans', 3)
            ->where('plans.0.key', 'monthly')
            ->where('plans.0.price_usd', '5.00')
            // The yearly plan is cheaper per month, so it advertises how much.
            ->where('plans.2.key', 'yearly')
            ->where('plans.2.savings_percent', 25)
            ->has('networks', 1)
            ->where('networks.0.chain_id', 1)
            ->has('networks.0.assets', 3)
            ->where('pending', null)
            ->has('history', 0)
        );
});

test('the page 404s while billing is switched off', function () {
    config()->set('billing.enabled', false);

    $this->actingAs(User::factory()->create())
        ->get(route('billing.edit'))
        ->assertNotFound();
});

test('the page remains available but marks checkout unavailable when a pool is empty', function () {
    enableBilling();
    DepositAddress::query()->delete();

    $this->actingAs(User::factory()->create())
        ->get(route('billing.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('networks.0.available', false)
            ->where('networks.0.available_addresses', 0));
});

test('billing is behind auth and a complete profile', function () {
    $this->get(route('billing.edit'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create(['birthdate' => null]))
        ->get(route('billing.edit'))
        ->assertRedirect(route('profile.edit'));

    // The `verified` middleware is on the group too, but it is not asserted here:
    // this app rolls its own passwordless flow instead of Fortify's, so there is
    // no verification.notice route for the middleware to redirect to, and every
    // route in the group behaves the same way. Not a billing concern.
});

test('choosing a plan opens an intent and shows what to send', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('billing.payments.store'), [
        'plan' => 'yearly',
        'network' => 'ethereum',
        'asset' => 'usdc',
    ])->assertRedirect();

    $this->actingAs($user)->get(route('billing.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pending.plan', 'yearly')
            ->where('pending.months', 12)
            ->where('pending.asset_symbol', 'USDC')
            ->where('pending.status', 'pending')
            ->where('pending.pay_to_address', mb_strtolower(TEST_RECEIVING_ADDRESS))
            // An EIP-681 request, so the amount does not have to be retyped —
            // which is how eighteen-decimal figures end up mismatched.
            ->where('pending.payment_uri', fn (string $uri): bool => str_starts_with($uri, 'ethereum:0xa0b86991')
                && str_contains($uri, '@1/transfer')
                && str_contains($uri, 'uint256='))
            ->has('history', 1)
        );
});

test('an asset the chosen chain does not offer is refused', function () {
    enableBilling(['billing.networks.ethereum.assets.usdc.contract' => null]);

    $this->actingAs(User::factory()->create())
        ->post(route('billing.payments.store'), [
            'plan' => 'monthly',
            'network' => 'ethereum',
            'asset' => 'usdc',
        ])
        ->assertSessionHasErrors('asset');

    expect(SubscriptionPayment::query()->count())->toBe(0);
});

test('an unpriced plan is refused', function () {
    enableBilling(['billing.plans.quarterly.price_usd' => '0.00']);

    $this->actingAs(User::factory()->create())
        ->post(route('billing.payments.store'), [
            'plan' => 'quarterly',
            'network' => 'ethereum',
            'asset' => 'usdt',
        ])
        ->assertSessionHasErrors('plan');
});

test('submitting a hash moves the payment on to be checked', function () {
    $user = User::factory()->create();
    $payment = SubscriptionPayment::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('billing.payments.proof', $payment), ['tx_hash' => '0x'.str_repeat('a', 64)])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Submitted);
});

test('a hash is normalized before validation, so explorer formatting is accepted', function () {
    $user = User::factory()->create();
    $payment = SubscriptionPayment::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('billing.payments.proof', $payment), ['tx_hash' => str_repeat('A', 64)])
        ->assertSessionHasNoErrors();

    expect($payment->fresh()->tx_hash)->toBe('0x'.str_repeat('a', 64));
});

test('a malformed hash is rejected with a readable message', function () {
    $user = User::factory()->create();
    $payment = SubscriptionPayment::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('billing.payments.proof', $payment), ['tx_hash' => '0x1234'])
        ->assertSessionHasErrors('tx_hash');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

test('nobody can claim a transaction against somebody else\'s payment', function () {
    $payment = SubscriptionPayment::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->post(route('billing.payments.proof', $payment), ['tx_hash' => '0x'.str_repeat('a', 64)])
        ->assertForbidden();

    $this->actingAs($stranger)
        ->delete(route('billing.payments.cancel', $payment))
        ->assertNotFound();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

test('a payment being checked stays on the page so it can be watched', function () {
    $user = User::factory()->create();
    $payment = SubscriptionPayment::factory()->submitted()->create(['user_id' => $user->id]);

    // The narrower "still unpaid" scope would drop it here, and the page would
    // stop polling the moment there was something to poll for.
    $this->actingAs($user)->get(route('billing.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pending.id', $payment->id)
            ->where('pending.status', 'submitted')
        );
});

test('a settled payment leaves the panel and the badge turns Pro together', function () {
    $user = User::factory()->create();
    $payment = SubscriptionPayment::factory()->submitted()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('billing.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pending.status', 'submitted')
            ->where('subscription.is_pro', false)
        );

    // Settle it exactly as the verification job does.
    $payment->forceFill(['status' => PaymentStatus::Confirmed, 'verified_at' => now()])->save();
    app(GrantProAccess::class)($user, 1, GrantReason::Payment, $payment->id);

    // Both flip in the same response, which is what the page's poll picks up:
    // the panel disappears (also ending the polling) and the badge turns Pro.
    $this->actingAs($user)->get(route('billing.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pending', null)
            ->where('subscription.is_pro', true)
            ->where('history.0.status_label', PaymentStatus::Confirmed->label())
        );
});

test('an unpaid intent can be withdrawn, a claimed one cannot', function () {
    $user = User::factory()->create();
    $open = SubscriptionPayment::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->delete(route('billing.payments.cancel', $open))->assertRedirect();
    // Withdrawn, not expired: the buyer cancelled it themselves, and the history
    // is the only place the distinction is ever read.
    expect($open->fresh()->status)->toBe(PaymentStatus::Cancelled);

    // A payment that has claimed a transaction is evidence now, and stays.
    $claimed = SubscriptionPayment::factory()->submitted()->create(['user_id' => $user->id]);

    $this->actingAs($user)->delete(route('billing.payments.cancel', $claimed))->assertNotFound();
    expect($claimed->fresh()->status)->toBe(PaymentStatus::Submitted);
});

test('a user with an armed vault can still buy and pay', function () {
    // Billing rows are plaintext by design — RejectWhenVaultArmed is deliberately
    // absent from these routes, because the worker that settles a payment has no
    // browser to ask for a data key.
    $user = User::factory()->create();
    $user->ensureEncryptionKey();
    $user->encryptionKey()->update(['wrapped_dek_server' => null]);

    $this->actingAs($user)->get(route('billing.edit'))->assertOk();

    $this->actingAs($user)->post(route('billing.payments.store'), [
        'plan' => 'monthly',
        'network' => 'ethereum',
        'asset' => 'usdt',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->vaultIsArmed())->toBeTrue()
        ->and($user->subscriptionPayments()->count())->toBe(1);
});

test('the settings nav offers billing only while it is switched on', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('subscription.billing_enabled', true));

    config()->set('billing.enabled', false);

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('subscription.billing_enabled', false));
});
