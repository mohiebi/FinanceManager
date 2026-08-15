<?php

use App\Actions\Billing\GrantProAccess;
use App\Actions\Billing\RedeemFreeCoupon;
use App\Actions\Billing\ResolveCoupon;
use App\Actions\Billing\SettleCouponRedemption;
use App\Actions\Billing\StartSubscriptionPayment;
use App\Enums\BillingPlan;
use App\Enums\CouponRedemptionStatus;
use App\Enums\CouponRejection;
use App\Enums\GrantReason;
use App\Enums\PaymentNetwork;
use App\Enums\PaymentStatus;
use App\Enums\SettlementAsset;
use App\Exceptions\CouponUnavailable;
use App\Jobs\ReconcileSubscriptionsJob;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    enableBilling();
    Queue::fake();
    Notification::fake();
});

function startWithCoupon(User $user, Coupon $coupon, BillingPlan $plan = BillingPlan::Monthly): SubscriptionPayment
{
    return app(StartSubscriptionPayment::class)(
        $user, $plan, PaymentNetwork::Ethereum, SettlementAsset::Usdt, $coupon
    );
}

test('a percentage discount reaches the price the buyer actually owes', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->percent(40)->create();

    $payment = startWithCoupon($user, $coupon, BillingPlan::Yearly);

    // price_usd holds what is owed, because that is what the on-chain amount is
    // derived from; the original is kept alongside purely to show the saving.
    expect($payment->price_usd)->toBe('27.00')
        ->and($payment->list_price_usd)->toBe('45.00')
        ->and($payment->coupon_id)->toBe($coupon->id)
        ->and($payment->discountUsd())->toBe('18.00');
});

test('a fixed discount reaches the price the buyer actually owes', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->fixed('3.00')->create();

    $payment = startWithCoupon($user, $coupon);

    expect($payment->price_usd)->toBe('2.00')
        ->and($payment->list_price_usd)->toBe('5.00');
});

test('the expected on-chain amount is built from the discounted price', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->percent(50)->create();

    $payment = startWithCoupon($user, $coupon, BillingPlan::Yearly);

    // The whole reason the discount is applied before the quote: verification
    // matches this figure exactly, so it has to descend from what is owed.
    expect((float) $payment->expected_amount)->toBeGreaterThanOrEqual(22.50)
        ->and((float) $payment->expected_amount)->toBeLessThan(22.51);
});

test('opening an intent reserves a use rather than spending it', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->limited(total: 1)->create();

    $payment = startWithCoupon($user, $coupon);
    $redemption = CouponRedemption::query()->sole();

    expect($redemption->status)->toBe(CouponRedemptionStatus::Reserved)
        ->and($redemption->subscription_payment_id)->toBe($payment->id)
        ->and($redemption->discount_usd)->toBe('1.25')
        ->and($coupon->fresh()->claimedCount())->toBe(1);
});

test('a reserved use blocks somebody else while it is still open', function () {
    $coupon = Coupon::factory()->limited(total: 1)->create();

    startWithCoupon(User::factory()->create(), $coupon);

    // The first buyer has not paid yet, but the last use is spoken for — a
    // limit that only counted settled payments would let both through.
    expect(fn () => startWithCoupon(User::factory()->create(), $coupon))
        ->toThrow(CouponUnavailable::class);

    expect(SubscriptionPayment::query()->count())->toBe(1);
});

test('a lapsed intent hands its use back', function () {
    $coupon = Coupon::factory()->limited(total: 1)->create();
    $payment = startWithCoupon(User::factory()->create(), $coupon);

    // The sweep expires it the way it would any unpaid intent.
    $payment->forceFill(['expires_at' => now()->subHour()])->save();
    (new ReconcileSubscriptionsJob)->handle();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and(CouponRedemption::query()->sole()->status)->toBe(CouponRedemptionStatus::Released)
        ->and($coupon->fresh()->claimedCount())->toBe(0);

    // And the code works again for the next person.
    expect(startWithCoupon(User::factory()->create(), $coupon))
        ->toBeInstanceOf(SubscriptionPayment::class);
});

test('withdrawing an intent hands its use back', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->limited(total: 1)->create();
    $payment = startWithCoupon($user, $coupon);

    $this->actingAs($user)->delete(route('billing.payments.cancel', $payment))->assertRedirect();

    expect(CouponRedemption::query()->sole()->status)->toBe(CouponRedemptionStatus::Released)
        ->and($coupon->fresh()->claimedCount())->toBe(0);
});

test('a settled payment spends the use for good', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->limited(total: 1)->create();
    $payment = startWithCoupon($user, $coupon);

    $grant = app(GrantProAccess::class)($user, 1, GrantReason::Payment, $payment->id);
    app(SettleCouponRedemption::class)->consume($payment->fresh(), $grant);

    $redemption = CouponRedemption::query()->sole();

    expect($redemption->status)->toBe(CouponRedemptionStatus::Consumed)
        ->and($redemption->subscription_grant_id)->toBe($grant->id)
        ->and($coupon->fresh()->claimedCount())->toBe(1);

    // A consumed claim is final — a replayed release must not resurrect it.
    app(SettleCouponRedemption::class)->release($payment->fresh());
    expect(CouponRedemption::query()->sole()->status)->toBe(CouponRedemptionStatus::Consumed);
});

test('a coupon covering the whole price grants months with no payment at all', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->free()->create();

    $rejection = app(RedeemFreeCoupon::class)($user, $coupon, BillingPlan::Quarterly);

    expect($rejection)->toBeNull()
        ->and($user->fresh()->isPro())->toBeTrue()
        // No intent, no address, no amount — a chain cannot carry a zero transfer.
        ->and(SubscriptionPayment::query()->count())->toBe(0);

    $grant = $user->subscriptionGrants()->sole();

    expect($grant->reason)->toBe(GrantReason::Coupon)
        ->and($grant->months)->toBe(3);

    $redemption = CouponRedemption::query()->sole();

    expect($redemption->status)->toBe(CouponRedemptionStatus::Consumed)
        ->and($redemption->subscription_grant_id)->toBe($grant->id)
        ->and($redemption->subscription_payment_id)->toBeNull();
});

test('a coupon that leaves something to pay is never granted outright', function () {
    // Defence in depth: reaching the free path with a partial discount would
    // hand out months nobody paid for.
    expect(fn () => app(RedeemFreeCoupon::class)(
        User::factory()->create(), Coupon::factory()->percent(50)->create(), BillingPlan::Monthly
    ))->toThrow(RuntimeException::class);

    expect(User::query()->whereNotNull('pro_until')->count())->toBe(0);
});

test('every reason a code can be refused is reported distinctly', function () {
    $user = User::factory()->create();
    $resolve = app(ResolveCoupon::class);

    expect($resolve($user, 'NOPE', BillingPlan::Monthly)->rejection)
        ->toBe(CouponRejection::NotFound)
        ->and($resolve($user, Coupon::factory()->disabled()->create()->code, BillingPlan::Monthly)->rejection)
        ->toBe(CouponRejection::Disabled)
        ->and($resolve($user, Coupon::factory()->expired()->create()->code, BillingPlan::Monthly)->rejection)
        ->toBe(CouponRejection::Expired)
        ->and($resolve($user, Coupon::factory()->issuedTo(User::factory()->create())->create()->code, BillingPlan::Monthly)->rejection)
        ->toBe(CouponRejection::WrongUser);
});

test('a code issued to one account works for them and nobody else', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $coupon = Coupon::factory()->issuedTo($owner)->create();

    expect(app(ResolveCoupon::class)($owner, $coupon->code, BillingPlan::Monthly)->accepted)->toBeTrue()
        ->and(app(ResolveCoupon::class)($stranger, $coupon->code, BillingPlan::Monthly)->accepted)->toBeFalse();
});

test('a per-user limit stops the same buyer reusing a public code', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->limited(perUser: 1)->create();

    startWithCoupon($user, $coupon);

    expect(app(ResolveCoupon::class)($user, $coupon->code, BillingPlan::Monthly)->rejection)
        ->toBe(CouponRejection::UserLimitReached)
        // Somebody else is unaffected — the cap is per person, not global.
        ->and(app(ResolveCoupon::class)(User::factory()->create(), $coupon->code, BillingPlan::Monthly)->accepted)
        ->toBeTrue();
});

test('codes are matched however the buyer types them', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->create(['code' => 'LAUNCH50']);

    expect(app(ResolveCoupon::class)($user, ' launch50 ', BillingPlan::Monthly)->accepted)->toBeTrue();
});

test('applying a code does not hand back an existing full-price intent', function () {
    $user = User::factory()->create();

    $full = app(StartSubscriptionPayment::class)(
        $user, BillingPlan::Monthly, PaymentNetwork::Ethereum, SettlementAsset::Usdt
    );
    $discounted = startWithCoupon($user, Coupon::factory()->percent(50)->create());

    // The reuse check has to treat the coupon as part of the terms, or the
    // discount is silently dropped.
    expect($discounted->id)->not->toBe($full->id)
        ->and($discounted->price_usd)->toBe('2.50')
        ->and($full->fresh()->price_usd)->toBe('5.00');
});

test('a buyer redeems a discount code through the billing page', function () {
    $user = User::factory()->create();
    Coupon::factory()->percent(50)->create(['code' => 'HALF']);

    $this->actingAs($user)->post(route('billing.payments.store'), [
        'plan' => 'yearly',
        'network' => 'ethereum',
        'asset' => 'usdt',
        // Lower case, as somebody would actually type it.
        'coupon' => 'half',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(SubscriptionPayment::query()->sole()->price_usd)->toBe('22.50');
});

test('a buyer redeems a full-price code and is Pro without paying', function () {
    $user = User::factory()->create();
    Coupon::factory()->free()->create(['code' => 'ONTHEHOUSE']);

    $this->actingAs($user)->post(route('billing.payments.store'), [
        'plan' => 'monthly',
        'network' => 'ethereum',
        'asset' => 'usdt',
        'coupon' => 'ONTHEHOUSE',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($user->fresh()->isPro())->toBeTrue()
        ->and(SubscriptionPayment::query()->count())->toBe(0);
});

test('a bad code is a field error and opens nothing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('billing.payments.store'), [
        'plan' => 'monthly',
        'network' => 'ethereum',
        'asset' => 'usdt',
        'coupon' => 'NOSUCHCODE',
    ])->assertSessionHasErrors('coupon');

    expect(SubscriptionPayment::query()->count())->toBe(0);
});

test('the billing page reports the discount alongside what is owed', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->percent(50)->create(['code' => 'HALF']);
    startWithCoupon($user, $coupon, BillingPlan::Yearly);

    $this->actingAs($user)->get(route('billing.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('pending.price_usd', '22.50')
            ->where('pending.list_price_usd', '45.00')
            ->where('pending.coupon_code', 'HALF')
        );
});

test('asking twice with the same code reuses the one intent', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->percent(50)->create();

    $first = startWithCoupon($user, $coupon);
    $second = startWithCoupon($user, $coupon);

    expect($second->id)->toBe($first->id)
        // And only one use was claimed, not two.
        ->and(CouponRedemption::query()->count())->toBe(1);
});

test('checking a code prices every plan without claiming a use', function () {
    $user = User::factory()->create();
    $coupon = Coupon::factory()->percent(50)->create(['code' => 'HALF']);

    $this->actingAs($user)->postJson(route('billing.coupon.preview'), ['coupon' => 'half'])
        ->assertOk()
        ->assertJson([
            'accepted' => true,
            'code' => 'HALF',
            'plans' => [
                'monthly' => [
                    'list_price_usd' => '5.00',
                    'discount_usd' => '2.50',
                    'final_price_usd' => '2.50',
                    'covers_everything' => false,
                ],
                'yearly' => ['final_price_usd' => '22.50'],
            ],
        ]);

    // The whole point of a preview: nothing was reserved, so checking a code
    // over and over cannot burn through a limited one.
    expect(CouponRedemption::query()->count())->toBe(0)
        ->and($coupon->fresh()->claimedCount())->toBe(0);
});

test('checking a code the buyer cannot use says why', function () {
    $user = User::factory()->create();
    Coupon::factory()->percent(50)->create([
        'code' => 'GONE',
        'valid_until' => now()->subDay(),
    ]);

    $this->actingAs($user)->postJson(route('billing.coupon.preview'), ['coupon' => 'GONE'])
        ->assertOk()
        ->assertJson([
            'accepted' => false,
            'message' => CouponRejection::Expired->label(),
        ]);
});

test('checking a code issued to somebody else gives nothing away', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    Coupon::factory()->percent(50)->issuedTo($owner)->create(['code' => 'MINE']);

    // The same message an unknown code gets, so a stranger cannot tell a real
    // code from a made-up one by probing this endpoint.
    $this->actingAs($stranger)->postJson(route('billing.coupon.preview'), ['coupon' => 'MINE'])
        ->assertOk()
        ->assertJson([
            'accepted' => false,
            'message' => CouponRejection::NotFound->label(),
        ]);
});

test('checking a code that covers a plan outright says so', function () {
    $user = User::factory()->create();
    Coupon::factory()->percent(100)->create(['code' => 'FREE']);

    $this->actingAs($user)->postJson(route('billing.coupon.preview'), ['coupon' => 'FREE'])
        ->assertOk()
        ->assertJson([
            'accepted' => true,
            'plans' => [
                'monthly' => [
                    'final_price_usd' => '0.00',
                    'covers_everything' => true,
                ],
            ],
        ]);
});

test('a guest cannot probe codes', function () {
    Coupon::factory()->percent(50)->create(['code' => 'HALF']);

    $this->postJson(route('billing.coupon.preview'), ['coupon' => 'HALF'])
        ->assertUnauthorized();
});
