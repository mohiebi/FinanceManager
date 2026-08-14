<?php

namespace App\Exceptions;

use App\Enums\CouponRejection;
use RuntimeException;

/**
 * A coupon could not be claimed at the moment it was needed.
 *
 * Distinct from the advisory answer ResolveCoupon gives, which is only a
 * snapshot: this is thrown from inside the locked transaction that actually
 * takes a use, where somebody else may have taken the last one in between.
 * Carries the reason so the buyer can be told which of the rules they hit.
 */
class CouponUnavailable extends RuntimeException
{
    public function __construct(public readonly CouponRejection $rejection)
    {
        parent::__construct("The coupon could not be used: {$rejection->value}.");
    }
}
