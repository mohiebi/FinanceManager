<?php

namespace App\Models;

use App\Enums\CouponRedemptionStatus;
use Database\Factories\CouponRedemptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One use of a coupon.
 *
 * The row is written when a payment intent opens, not when the money arrives,
 * because by settlement the buyer has already paid and refusing them for being
 * over the coupon's limit is no longer a choice anybody can make. A claim that
 * never gets paid for is released again by the reconciliation sweep.
 *
 * `discount_usd` is a snapshot for the same reason the payment row snapshots its
 * price: editing or disabling the coupon afterwards must not rewrite history.
 */
#[Fillable([
    'coupon_id',
    'user_id',
    'subscription_payment_id',
    'subscription_grant_id',
    'status',
    'discount_usd',
])]
class CouponRedemption extends Model
{
    /** @use HasFactory<CouponRedemptionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Coupon, CouponRedemption>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return BelongsTo<User, CouponRedemption>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<SubscriptionPayment, CouponRedemption>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class, 'subscription_payment_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CouponRedemptionStatus::class,
        ];
    }
}
