<?php

use App\Models\Coupon;
use App\Support\Billing\CouponDiscount;

/**
 * A Feature test rather than a Unit one despite touching no database: the
 * subject takes a Coupon model, and tests/Unit runs on plain PHPUnit with no
 * Laravel application, so Eloquent is unavailable there. `makeOne()` keeps
 * every case in memory regardless.
 */
function percentCoupon(int $percentOff): Coupon
{
    return Coupon::factory()->percent($percentOff)->makeOne();
}

function fixedCoupon(string $amountOffUsd): Coupon
{
    return Coupon::factory()->fixed($amountOffUsd)->makeOne();
}

test('a percentage comes off the price', function () {
    expect(CouponDiscount::amountOff('45.00', percentCoupon(50)))->toBe('22.50')
        ->and(CouponDiscount::finalPrice('45.00', percentCoupon(50)))->toBe('22.50')
        ->and(CouponDiscount::amountOff('5.00', percentCoupon(10)))->toBe('0.50')
        ->and(CouponDiscount::finalPrice('5.00', percentCoupon(10)))->toBe('4.50');
});

test('a fixed amount comes off the price', function () {
    expect(CouponDiscount::amountOff('45.00', fixedCoupon('10.00')))->toBe('10.00')
        ->and(CouponDiscount::finalPrice('45.00', fixedCoupon('10.00')))->toBe('35.00');
});

test('results are always two-decimal strings, never floats', function () {
    // Everything downstream compares these as strings and converts them into
    // on-chain amounts; a float here is what breaks verification.
    $discounted = CouponDiscount::finalPrice('13.00', percentCoupon(33));

    expect($discounted)->toBeString()
        ->and($discounted)->toBe('8.71')
        ->and(CouponDiscount::finalPrice('5.00', percentCoupon(1)))->toBe('4.95')
        ->and(CouponDiscount::amountOff('45.00', percentCoupon(100)))->toBe('45.00');
});

test('a fixed amount larger than the price makes it free rather than negative', function () {
    // A $10 credit against a $5 plan owes no change.
    expect(CouponDiscount::amountOff('5.00', fixedCoupon('10.00')))->toBe('5.00')
        ->and(CouponDiscount::finalPrice('5.00', fixedCoupon('10.00')))->toBe('0.00')
        ->and(CouponDiscount::coversEverything('5.00', fixedCoupon('10.00')))->toBeTrue();
});

test('a hundred percent off leaves nothing to pay', function () {
    // The fork that matters: the chain cannot carry a zero transfer, so this is
    // what sends redemption down the grant path instead of opening an intent.
    expect(CouponDiscount::finalPrice('45.00', percentCoupon(100)))->toBe('0.00')
        ->and(CouponDiscount::coversEverything('45.00', percentCoupon(100)))->toBeTrue()
        ->and(CouponDiscount::coversEverything('45.00', percentCoupon(99)))->toBeFalse();
});

test('a partial discount never reads as covering everything', function () {
    expect(CouponDiscount::coversEverything('5.00', fixedCoupon('4.99')))->toBeFalse()
        ->and(CouponDiscount::finalPrice('5.00', fixedCoupon('4.99')))->toBe('0.01');
});

test('nonsensical inputs clamp instead of going negative', function () {
    expect(CouponDiscount::finalPrice('0.00', percentCoupon(50)))->toBe('0.00')
        ->and(CouponDiscount::amountOff('0.00', fixedCoupon('10.00')))->toBe('0.00')
        ->and(CouponDiscount::amountOff('5.00', fixedCoupon('-3.00')))->toBe('0.00')
        ->and(CouponDiscount::finalPrice('5.00', fixedCoupon('-3.00')))->toBe('5.00');
});
