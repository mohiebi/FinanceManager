<?php

use App\Enums\CouponKind;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    enableBilling();
    Queue::fake();
    config()->set('app.admin_email', 'boss@example.com');
});

/**
 * Password confirmation guards every mutating admin route, so acting as the
 * admin means seeding the session key the middleware reads.
 */
function couponAdmin(): User
{
    $admin = User::factory()->create(['email' => 'boss@example.com']);

    test()->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

    return $admin;
}

/** @return array<string, mixed> */
function couponPayload(array $overrides = []): array
{
    return ['code' => 'LAUNCH50', 'kind' => 'percent', 'percent_off' => 50, ...$overrides];
}

test('an admin can create a percentage coupon', function () {
    $admin = couponAdmin();

    $this->post(route('admin.coupons.store'), couponPayload())->assertRedirect();

    $coupon = Coupon::query()->sole();

    expect($coupon->code)->toBe('LAUNCH50')
        ->and($coupon->kind)->toBe(CouponKind::Percent)
        ->and($coupon->percent_off)->toBe(50)
        ->and($coupon->amount_off_usd)->toBeNull()
        ->and($coupon->user_id)->toBeNull()
        ->and($coupon->created_by_admin_id)->toBe($admin->id);
});

test('an admin can create a fixed-amount coupon', function () {
    couponAdmin();

    $this->post(route('admin.coupons.store'), couponPayload([
        'code' => 'refund-5', 'kind' => 'fixed', 'percent_off' => null, 'amount_off_usd' => '5',
    ]))->assertRedirect();

    $coupon = Coupon::query()->sole();

    // Lower case in, canonical out, and the amount frozen to two decimals.
    expect($coupon->code)->toBe('REFUND-5')
        ->and($coupon->kind)->toBe(CouponKind::Fixed)
        ->and($coupon->amount_off_usd)->toBe('5.00')
        ->and($coupon->percent_off)->toBeNull();
});

test('a coupon can be locked to one account by email', function () {
    couponAdmin();
    $target = User::factory()->create(['email' => 'Buyer@Example.com']);

    $this->post(route('admin.coupons.store'), couponPayload([
        'user_email' => 'buyer@example.com',
    ]))->assertRedirect();

    expect(Coupon::query()->sole()->user_id)->toBe($target->id);
});

test('an unknown target email is refused', function () {
    couponAdmin();

    $this->post(route('admin.coupons.store'), couponPayload(['user_email' => 'nobody@example.com']))
        ->assertSessionHasErrors('user_email');

    expect(Coupon::query()->count())->toBe(0);
});

test('the amount matching the chosen kind is required', function () {
    couponAdmin();

    $this->post(route('admin.coupons.store'), ['code' => 'A1', 'kind' => 'percent'])
        ->assertSessionHasErrors('percent_off');

    $this->post(route('admin.coupons.store'), ['code' => 'A2', 'kind' => 'fixed'])
        ->assertSessionHasErrors('amount_off_usd');

    expect(Coupon::query()->count())->toBe(0);
});

test('nonsensical discounts are refused', function () {
    couponAdmin();

    foreach ([0, -5, 101] as $percent) {
        $this->post(route('admin.coupons.store'), couponPayload(['percent_off' => $percent]))
            ->assertSessionHasErrors('percent_off');
    }

    foreach (['0', '-3'] as $amount) {
        $this->post(route('admin.coupons.store'), couponPayload([
            'kind' => 'fixed', 'percent_off' => null, 'amount_off_usd' => $amount,
        ]))->assertSessionHasErrors('amount_off_usd');
    }

    expect(Coupon::query()->count())->toBe(0);
});

test('a duplicate code is refused, whatever the casing', function () {
    couponAdmin();
    Coupon::factory()->create(['code' => 'LAUNCH50']);

    $this->post(route('admin.coupons.store'), couponPayload(['code' => 'launch50']))
        ->assertSessionHasErrors('code');

    expect(Coupon::query()->count())->toBe(1);
});

test('a code with spaces or punctuation is refused', function () {
    couponAdmin();

    foreach (['HALF PRICE', 'FIFTY%', 'a b'] as $code) {
        $this->post(route('admin.coupons.store'), couponPayload(['code' => $code]))
            ->assertSessionHasErrors('code');
    }
});

test('an expiry in the past is refused', function () {
    couponAdmin();

    $this->post(route('admin.coupons.store'), couponPayload(['valid_until' => now()->subDay()->toDateTimeString()]))
        ->assertSessionHasErrors('valid_until');
});

test('a coupon can be disabled and enabled again', function () {
    couponAdmin();
    $coupon = Coupon::factory()->create();

    $this->post(route('admin.coupons.disable', $coupon))->assertRedirect();
    expect($coupon->fresh()->isDisabled())->toBeTrue();

    $this->post(route('admin.coupons.enable', $coupon))->assertRedirect();
    expect($coupon->fresh()->isDisabled())->toBeFalse();
});

test('a redeemed coupon cannot be deleted, only disabled', function () {
    couponAdmin();
    $coupon = Coupon::factory()->create();
    CouponRedemption::factory()->create(['coupon_id' => $coupon->id]);

    // Deleting it would orphan the ledger, and with it the reason somebody has Pro.
    $this->delete(route('admin.coupons.destroy', $coupon))->assertStatus(409);

    expect(Coupon::query()->whereKey($coupon->id)->exists())->toBeTrue();
});

test('an unused coupon can be deleted outright', function () {
    couponAdmin();
    $coupon = Coupon::factory()->create();

    $this->delete(route('admin.coupons.destroy', $coupon))->assertRedirect();

    expect(Coupon::query()->count())->toBe(0);
});

test('the console lists coupons with their remaining uses', function () {
    couponAdmin();
    $coupon = Coupon::factory()->limited(total: 10)->create(['code' => 'LAUNCH50']);
    CouponRedemption::factory()->count(3)->create(['coupon_id' => $coupon->id]);
    CouponRedemption::factory()->released()->create(['coupon_id' => $coupon->id]);

    $this->get(route('admin.billing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Billing')
            ->has('coupons', 1)
            ->where('coupons.0.code', 'LAUNCH50')
            // The released one went back into the pool and is not counted.
            ->where('coupons.0.claimed_count', 3)
            ->where('coupons.0.max_redemptions', 10)
            ->where('coupons.0.deletable', false)
            ->where('coupons.0.user_email', null)
        );
});

test('coupon routes are admin-only', function () {
    $coupon = Coupon::factory()->create();
    $stranger = User::factory()->create();

    $routes = [
        ['post', route('admin.coupons.store'), couponPayload()],
        ['post', route('admin.coupons.disable', $coupon), []],
        ['post', route('admin.coupons.enable', $coupon), []],
        ['delete', route('admin.coupons.destroy', $coupon), []],
    ];

    foreach ($routes as [$method, $url, $data]) {
        $this->actingAs($stranger)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->{$method}($url, $data)
            ->assertForbidden();
    }

    expect(Coupon::query()->count())->toBe(1);
});

test('creating a coupon demands a fresh password confirmation', function () {
    $boss = User::factory()->create(['email' => 'boss@example.com']);

    $this->actingAs($boss)
        ->post(route('admin.coupons.store'), couponPayload())
        ->assertRedirect(route('password.confirm'));

    expect(Coupon::query()->count())->toBe(0);
});
