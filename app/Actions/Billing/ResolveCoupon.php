<?php

namespace App\Actions\Billing;

use App\Enums\BillingPlan;
use App\Enums\CouponRejection;
use App\Models\Coupon;
use App\Models\User;
use App\Support\Billing\CouponDiscount;

/**
 * Decides whether a code may be used, and what it is worth against a plan.
 *
 * Read-only and side-effect free: it claims nothing. The claim happens later,
 * under a lock, in {@see StartSubscriptionPayment} or {@see RedeemFreeCoupon} —
 * so this can be called freely to show the buyer a price without burning a use.
 *
 * The consequence is that a resolution is advisory: between resolving and
 * claiming, somebody else may take the last use. Both claim paths therefore
 * re-run these same checks inside their transaction, which is the only place
 * the answer is authoritative.
 */
final readonly class ResolveCoupon
{
    public function __invoke(User $user, string $code, BillingPlan $plan): CouponResolution
    {
        $coupon = Coupon::query()
            ->where('code', Coupon::normalizeCode($code))
            ->first();

        if ($coupon === null) {
            return CouponResolution::rejected(CouponRejection::NotFound);
        }

        $rejection = $this->rejectionFor($user, $coupon);

        if ($rejection !== null) {
            return CouponResolution::rejected($rejection);
        }

        $listPrice = $plan->priceUsd();

        return CouponResolution::accepted(
            coupon: $coupon,
            listPriceUsd: $listPrice,
            discountUsd: CouponDiscount::amountOff($listPrice, $coupon),
            finalPriceUsd: CouponDiscount::finalPrice($listPrice, $coupon),
            coversEverything: CouponDiscount::coversEverything($listPrice, $coupon),
        );
    }

    /**
     * The first reason this coupon cannot be used, or null if it can.
     *
     * Shared with the claim paths so an advisory answer and an authoritative one
     * can never apply different rules.
     */
    public function rejectionFor(User $user, Coupon $coupon): ?CouponRejection
    {
        if ($coupon->isDisabled()) {
            return CouponRejection::Disabled;
        }

        if ($coupon->isExpired()) {
            return CouponRejection::Expired;
        }

        // A code issued to somebody else reports the same thing as one that does
        // not exist, so a stranger cannot learn which codes are real.
        if (! $coupon->isPublic() && ! $coupon->isReservedFor($user)) {
            return CouponRejection::WrongUser;
        }

        if ($coupon->max_redemptions !== null && $coupon->claimedCount() >= $coupon->max_redemptions) {
            return CouponRejection::Exhausted;
        }

        if ($coupon->max_per_user !== null && $coupon->claimedCountFor($user) >= $coupon->max_per_user) {
            return CouponRejection::UserLimitReached;
        }

        return null;
    }
}
