<?php

namespace App\Enums;

/**
 * Where one use of a coupon stands.
 *
 * Reserved is the state that makes a redemption limit mean anything. A coupon
 * is claimed when the intent opens, not when the money lands, because by
 * settlement the payment has already been made and refusing it for being over
 * the limit is no longer an option. The reservation is released again if the
 * intent expires or is withdrawn unpaid.
 */
enum CouponRedemptionStatus: string
{
    /** Claimed by an open payment intent, not yet paid for. */
    case Reserved = 'reserved';

    /** The payment settled, or the coupon was redeemed outright for free. */
    case Consumed = 'consumed';

    /** The intent lapsed unpaid, so the use went back into the pool. */
    case Released = 'released';

    /** Whether this redemption still counts against the coupon's limits. */
    public function countsAgainstLimit(): bool
    {
        return $this !== self::Released;
    }
}
