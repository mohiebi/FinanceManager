<?php

use App\Actions\Billing\GrantProAccess;
use App\Actions\Billing\StartSubscriptionPayment;
use App\Actions\Billing\VerifyPaymentOnChain;
use App\Enums\CouponRedemptionStatus;
use App\Enums\MilesPack;
use App\Enums\MilesReason;
use App\Enums\PaymentNetwork;
use App\Enums\PaymentStatus;
use App\Enums\SettlementAsset;
use App\Jobs\VerifySubscriptionPaymentJob;
use App\Models\Coupon;
use App\Models\MileLedgerEntry;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    enableBilling();
    Queue::fake();
    Notification::fake();
    Http::preventStrayRequests();
});

it('offers four Miles packs with honest usage and savings', function () {
    $this->actingAs(User::factory()->create())->get(route('billing.edit'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('plans', 4)
        ->where('plans.0.key', 'starter')
        ->where('plans.0.miles', 500)
        ->where('plans.0.advisor_plans', 2)
        ->where('plans.1.miles', 1200)
        ->where('plans.3.price_usd', '50.00')
        ->where('plans.3.best_value', true)
        ->where('pending', null));
});

it('opens pack payments on each supported network without granting before settlement', function (string $network) {
    enableBilling([
        'billing.networks.arbitrum.enabled' => true,
        'billing.networks.arbitrum.address' => TEST_RECEIVING_ADDRESS,
        'billing.networks.arbitrum.rpc_url' => 'https://arbitrum.test/rpc',
    ]);
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('billing.payments.store'), [
        'plan' => 'everyday', 'network' => $network, 'asset' => 'usdc',
    ])->assertSessionHasNoErrors()->assertRedirect();
    $payment = $user->subscriptionPayments()->sole();
    expect($payment->plan)->toBeNull()
        ->and($payment->miles_pack)->toBe(MilesPack::Everyday)
        ->and($payment->miles)->toBe(1200)
        ->and($payment->months)->toBe(0)
        ->and($payment->price_usd)->toBe('10.00')
        ->and($user->fresh()->pro_until)->toBeNull();
    expect(MileLedgerEntry::where('reason', MilesReason::PackPurchase)->count())->toBe(0);
    $this->get(route('billing.edit'))->assertInertia(fn (Assert $page) => $page
        ->where('pending.miles', 1200)
        ->where('pending.network', $network)
        ->where('history.0.tone', 'pending')
        ->where('history.0.miles', 1200));
})->with(['ethereum', 'arbitrum']);

it('rejects legacy subscription purchases and unavailable packs', function () {
    $this->actingAs(User::factory()->create());
    foreach (['monthly', 'unknown'] as $plan) {
        $this->post(route('billing.payments.store'), ['plan' => $plan, 'network' => 'ethereum', 'asset' => 'usdc'])
            ->assertSessionHasErrors('plan');
    }
    config()->set('billing.miles_packs.starter.price_usd', '0.00');
    $this->post(route('billing.payments.store'), ['plan' => 'starter', 'network' => 'ethereum', 'asset' => 'usdc'])
        ->assertSessionHasErrors('plan');
    expect(SubscriptionPayment::count())->toBe(0);
});

it('settles a snapshotted Miles amount exactly once without extending Pro', function () {
    $user = User::factory()->pro()->create();
    $expiry = $user->pro_until->toIso8601String();
    $payment = app(StartSubscriptionPayment::class)($user, MilesPack::Everyday, PaymentNetwork::Ethereum, SettlementAsset::Usdc);
    $payment->forceFill(['status' => PaymentStatus::Submitted, 'tx_hash' => '0x'.str_repeat('a', 64)])->save();
    config()->set('billing.miles_packs.everyday.miles', 9999);
    fakeEvmChain([
        'tx_to' => $payment->token_contract,
        'logs' => [evmTransferLog($payment->token_contract, TEST_RECEIVING_ADDRESS, '0x'.dechex((int) $payment->expectedBaseUnits()))],
    ]);
    $verify = app(VerifyPaymentOnChain::class);
    $job = new VerifySubscriptionPaymentJob($payment->id);
    $job->handle($verify, app(GrantProAccess::class));
    $job->handle($verify, app(GrantProAccess::class));
    expect($user->mileWallet()->sole()->balance)->toBe(1200)
        ->and($user->mileLedgerEntries()->where('reason', MilesReason::PackPurchase)->count())->toBe(1)
        ->and($user->fresh()->pro_until->toIso8601String())->toBe($expiry)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Confirmed);
});

it('grants fully covered coupon packs once and includes them in history', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->percent(100)->create(['code' => 'MILES-FREE', 'max_per_user' => 1]);
    $payload = ['plan' => 'starter', 'network' => 'ethereum', 'asset' => 'usdc', 'coupon' => $coupon->code];
    $this->actingAs($user)->post(route('billing.payments.store'), $payload)
        ->assertSessionHasNoErrors()->assertSessionHas('activated.miles', 500);
    $balance = $user->mileWallet()->sole()->balance;
    $this->get(route('billing.edit'))->assertInertia(fn (Assert $page) => $page
        ->where('history.0.kind', 'coupon')->where('history.0.miles', 500)
        ->where('history.0.price_usd', '0.00')->where('history.0.tone', 'positive'));
    $this->post(route('billing.payments.store'), $payload)->assertSessionHasErrors('coupon');
    expect($user->mileWallet()->sole()->balance)->toBe($balance)
        ->and($user->subscriptionPayments()->count())->toBe(0)
        ->and($user->subscriptionGrants()->count())->toBe(0)
        ->and($user->fresh()->pro_until)->toBeNull();
});

it('previews and reserves a discounted pack and releases its coupon on cancellation', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->percent(50)->create(['code' => 'HALF-MILES']);
    $this->actingAs($user)->postJson(route('billing.coupon.preview'), ['coupon' => $coupon->code])
        ->assertOk()->assertJsonPath('plans.everyday.final_price_usd', '5.00');
    $this->post(route('billing.payments.store'), [
        'plan' => 'everyday', 'network' => 'ethereum', 'asset' => 'usdc', 'coupon' => $coupon->code,
    ])->assertSessionHasNoErrors();
    $payment = $user->subscriptionPayments()->sole();
    expect($payment->price_usd)->toBe('5.00')->and($payment->miles)->toBe(1200);
    $this->delete(route('billing.payments.cancel', $payment))->assertRedirect();
    expect($payment->couponRedemption()->sole()->status)->toBe(CouponRedemptionStatus::Released)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Cancelled);
});

it('credits Miles through manual payment approval without creating a subscription grant', function () {
    $admin = User::factory()->create(['email' => 'billing-admin@example.com']);
    config()->set('app.admin_email', $admin->email);
    $user = User::factory()->create();
    $payment = app(StartSubscriptionPayment::class)($user, MilesPack::Starter, PaymentNetwork::Ethereum, SettlementAsset::Usdc);
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.billing.approve', $payment), ['note' => 'Verified payment manually.'])
        ->assertSessionHasNoErrors()->assertRedirect();
    $this->post(route('admin.billing.approve', $payment), ['note' => 'Duplicate approval.'])->assertStatus(409);
    expect($user->mileWallet()->sole()->balance)->toBe(500)
        ->and($user->subscriptionGrants()->count())->toBe(0);
});

it('combines Activity with Miles and redirects the old Activity link', function () {
    $user = User::factory()->withModules()->create();
    $this->actingAs($user)->get(route('miles.index'))->assertInertia(fn (Assert $page) => $page
        ->component('Miles/Index')->has('activity.streak')->has('activity.logbook')
        ->has('activity.ranks', 3)->has('activity.moments')->has('overview.claimCycle', 7)->has('history.data'));
    $this->get(route('flight-log'))->assertRedirect(route('miles.index'));
});
