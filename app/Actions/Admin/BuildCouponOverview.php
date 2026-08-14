<?php

namespace App\Actions\Admin;

use App\Enums\CouponRedemptionStatus;
use App\Models\Coupon;

/**
 * The coupon list for the billing console.
 *
 * Sibling to {@see BuildBillingOverview}, and deliberately separate: coupons
 * and payments are read on the same page but answer different questions, and
 * one action returning both would be reached for whenever either was needed.
 */
final readonly class BuildCouponOverview
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(): array
    {
        return Coupon::query()
            ->with('user:id,email')
            // Counted in SQL rather than per row: a console listing every code
            // would otherwise issue a query each to work out its remaining uses.
            ->withCount([
                'redemptions as claimed_count' => fn ($query) => $query
                    ->whereNot('status', CouponRedemptionStatus::Released->value),
                'redemptions as consumed_count' => fn ($query) => $query
                    ->where('status', CouponRedemptionStatus::Consumed->value),
                'redemptions as total_count',
            ])
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->map(fn (Coupon $coupon): array => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'kind' => $coupon->kind->value,
                'kind_label' => $coupon->kind->label(),
                'percent_off' => $coupon->percent_off,
                'amount_off_usd' => $coupon->amount_off_usd,
                // Null reads as "anyone" in the UI.
                'user_email' => $coupon->user?->email,
                'max_redemptions' => $coupon->max_redemptions,
                'max_per_user' => $coupon->max_per_user,
                'claimed_count' => (int) $coupon->claimed_count,
                'consumed_count' => (int) $coupon->consumed_count,
                // Deletion is only offered while a code has never been touched.
                'deletable' => (int) $coupon->total_count === 0,
                'valid_until' => $coupon->valid_until?->toIso8601String(),
                'disabled' => $coupon->isDisabled(),
                'expired' => $coupon->isExpired(),
                'note' => $coupon->note,
                'created_at' => $coupon->created_at->toIso8601String(),
            ])
            ->all();
    }
}
