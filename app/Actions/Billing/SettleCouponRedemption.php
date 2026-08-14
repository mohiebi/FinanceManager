<?php

namespace App\Actions\Billing;

use App\Enums\CouponRedemptionStatus;
use App\Models\CouponRedemption;
use App\Models\SubscriptionGrant;
use App\Models\SubscriptionPayment;

/**
 * Moves a reserved coupon claim to its final state.
 *
 * Both transitions live here rather than being written inline wherever a
 * payment ends, so there is one place that decides what a claim becomes — and
 * one place to look when a coupon's remaining uses do not add up.
 *
 * Every method is a no-op for a payment that used no coupon, so callers need no
 * conditional of their own.
 */
final readonly class SettleCouponRedemption
{
    /**
     * The payment was paid for. The claim is spent for good.
     */
    public function consume(SubscriptionPayment $payment, ?SubscriptionGrant $grant = null): void
    {
        $redemption = $this->reservationFor($payment);

        if ($redemption === null) {
            return;
        }

        $redemption->forceFill([
            'status' => CouponRedemptionStatus::Consumed,
            'subscription_grant_id' => $grant?->getKey() ?? $redemption->subscription_grant_id,
        ])->save();
    }

    /**
     * The intent lapsed or was withdrawn unpaid, so the use goes back.
     *
     * Without this a single-use launch code would be spent by the first person
     * who opened an intent and wandered off, rather than by the first person who
     * actually paid.
     */
    public function release(SubscriptionPayment $payment): void
    {
        $this->reservationFor($payment)?->forceFill([
            'status' => CouponRedemptionStatus::Released,
        ])->save();
    }

    /**
     * Release every claim held by intents that have just lapsed.
     *
     * The set form exists because the reconciliation sweep expires intents with
     * one mass update and never loads them as models.
     *
     * @param  array<int, string>  $paymentIds
     */
    public function releaseMany(array $paymentIds): void
    {
        if ($paymentIds === []) {
            return;
        }

        CouponRedemption::query()
            ->whereIn('subscription_payment_id', $paymentIds)
            ->where('status', CouponRedemptionStatus::Reserved->value)
            ->update([
                'status' => CouponRedemptionStatus::Released->value,
                'updated_at' => now(),
            ]);
    }

    /**
     * Only a still-reserved claim can move. A consumed one is final, and a
     * released one has already gone back into the pool — re-releasing either
     * would let a replayed job change history.
     */
    private function reservationFor(SubscriptionPayment $payment): ?CouponRedemption
    {
        return CouponRedemption::query()
            ->where('subscription_payment_id', $payment->getKey())
            ->where('status', CouponRedemptionStatus::Reserved->value)
            ->first();
    }
}
