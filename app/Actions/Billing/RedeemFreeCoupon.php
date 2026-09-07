<?php

namespace App\Actions\Billing;

use App\Enums\BillingPlan;
use App\Enums\CouponRedemptionStatus;
use App\Enums\CouponRejection;
use App\Enums\GrantReason;
use App\Enums\MilesPack;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\User;
use App\Support\Billing\CouponDiscount;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Redeems a coupon that covers the whole price.
 *
 * There is no payment to make, so there is no chain involved at all: no
 * address, no amount, no transaction to wait for. A zero-value transfer is not
 * something a chain can carry, so the alternative would be opening an intent
 * nobody could ever settle.
 *
 * Goes straight to {@see GrantProAccess}, the single writer of `pro_until`, and
 * is recorded with {@see GrantReason::Coupon} so the audit trail distinguishes
 * a buyer redeeming a code from an administrator granting months by hand.
 */
final readonly class RedeemFreeCoupon
{
    public function __construct(
        private ResolveCoupon $resolveCoupon,
        private GrantProAccess $grantProAccess,
    ) {}

    /**
     * @return CouponRejection|null null when the months were granted
     */
    public function __invoke(User $user, Coupon $coupon, BillingPlan|MilesPack $plan): ?CouponRejection
    {
        return DB::transaction(function () use ($user, $coupon, $plan): ?CouponRejection {
            // Re-read under a lock. ResolveCoupon's answer was advisory — between
            // it and here, somebody else may have taken the last use.
            $locked = Coupon::query()->whereKey($coupon->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                return CouponRejection::NotFound;
            }

            $rejection = ($this->resolveCoupon)->rejectionFor($user, $locked);

            if ($rejection !== null) {
                return $rejection;
            }

            $listPrice = $plan->priceUsd();

            // Defence in depth: reaching here with a coupon that leaves something
            // to pay would grant months nobody paid for.
            if (! CouponDiscount::coversEverything($listPrice, $locked)) {
                throw new RuntimeException("Coupon {$locked->code} does not cover the {$plan->value} plan in full.");
            }

            if ($plan instanceof MilesPack) {
                $redemption = CouponRedemption::create([
                    'coupon_id' => $locked->getKey(),
                    'user_id' => $user->getKey(),
                    'miles_pack' => $plan,
                    'miles' => $plan->miles(),
                    'status' => CouponRedemptionStatus::Consumed,
                    'discount_usd' => CouponDiscount::amountOff($listPrice, $locked),
                ]);
                app(CreditPurchasedMiles::class)($redemption);

                return null;
            }
            $grant = ($this->grantProAccess)(
                user: $user,
                months: $plan->months(),
                reason: GrantReason::Coupon,
                note: "Coupon {$locked->code}",
            );

            CouponRedemption::create([
                'coupon_id' => $locked->getKey(),
                'user_id' => $user->getKey(),
                'subscription_grant_id' => $grant->getKey(),
                // Consumed outright rather than reserved: nothing is pending,
                // the months are already on the account.
                'status' => CouponRedemptionStatus::Consumed,
                'discount_usd' => CouponDiscount::amountOff($listPrice, $locked),
            ]);

            return null;
        });
    }
}
