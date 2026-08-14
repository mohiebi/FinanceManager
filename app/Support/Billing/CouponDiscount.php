<?php

namespace App\Support\Billing;

use App\Enums\CouponKind;
use App\Models\Coupon;
use App\Services\Billing\AssetQuoteService;
use App\Support\BudgetMath;

/**
 * What a coupon takes off a plan price.
 *
 * Works in USD, before the crypto quote, and returns two-decimal strings. That
 * placement is the whole design: everything downstream of
 * {@see AssetQuoteService::priceIn()} — the on-chain
 * amount, its per-payment nonce, the exact-match comparison at settlement — is
 * derived from the USD figure, so a discount applied any later would leave the
 * stored price and the chain disagreeing.
 *
 * The float step here follows the precedent priceIn() already sets: a
 * controlled division whose result is immediately frozen into a fixed-precision
 * string and never recomputed. Nothing downstream ever sees the float.
 *
 * Deliberately not built on {@see BudgetMath} — that is pinned to
 * its TypeScript twin by tests/fixtures/budget-vectors.json and answers a
 * different question — nor on {@see TokenAmount}, which has no subtraction and
 * works in base units this never reaches.
 */
final class CouponDiscount
{
    private const PRECISION = 2;

    /**
     * How much this coupon takes off, never more than the price itself.
     */
    public static function amountOff(string $priceUsd, Coupon $coupon): string
    {
        $price = max(0.0, (float) $priceUsd);

        $raw = match ($coupon->kind) {
            CouponKind::Percent => $price * ((float) $coupon->percent_off / 100),
            CouponKind::Fixed => (float) $coupon->amount_off_usd,
        };

        // A fixed amount larger than the plan is not an error — a $10 credit
        // against a $5 plan simply makes it free rather than owing change.
        return self::money(min($price, max(0.0, $raw)));
    }

    /**
     * What is left to pay. Never negative, and '0.00' means nothing is owed.
     */
    public static function finalPrice(string $priceUsd, Coupon $coupon): string
    {
        $price = max(0.0, (float) $priceUsd);

        return self::money(max(0.0, $price - (float) self::amountOff($priceUsd, $coupon)));
    }

    /**
     * Whether a discounted price leaves nothing to pay.
     *
     * The chain cannot carry a zero transfer, so this is the fork between
     * opening a payment intent and granting the months outright.
     */
    public static function coversEverything(string $priceUsd, Coupon $coupon): bool
    {
        return (float) self::finalPrice($priceUsd, $coupon) <= 0.0;
    }

    /**
     * Freeze a figure into the two-decimal string form every price here uses.
     *
     * Public because callers outside this class derive money from stored
     * columns too — a payment reporting what its coupon took off, for one — and
     * they must round it identically or the arithmetic stops agreeing.
     */
    public static function money(float $value): string
    {
        return number_format($value, self::PRECISION, '.', '');
    }
}
