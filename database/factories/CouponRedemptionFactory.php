<?php

namespace Database\Factories;

use App\Enums\CouponRedemptionStatus;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CouponRedemption>
 */
class CouponRedemptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'coupon_id' => Coupon::factory(),
            'user_id' => User::factory(),
            'subscription_payment_id' => null,
            'subscription_grant_id' => null,
            'status' => CouponRedemptionStatus::Consumed,
            'discount_usd' => '1.25',
        ];
    }

    /** Claimed by an open intent, not yet paid for. */
    public function reserved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CouponRedemptionStatus::Reserved,
        ]);
    }

    /** The intent lapsed unpaid, so the claim went back into the pool. */
    public function released(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CouponRedemptionStatus::Released,
        ]);
    }
}
