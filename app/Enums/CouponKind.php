<?php

namespace App\Enums;

/**
 * How a coupon works out what it takes off the price.
 *
 * The percent case is the one that scales: a fixed amount that reads as
 * generous against the yearly plan is most of the monthly plan, so a single
 * launch code priced in dollars behaves quite differently depending on what the
 * buyer picks. A fixed amount is still worth having for making good on a
 * specific sum — a refund, or a partial credit.
 */
enum CouponKind: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function label(): string
    {
        $translationKey = "billing.coupon.kinds.{$this->value}";
        $translatedLabel = __($translationKey);

        if ($translatedLabel !== $translationKey) {
            return $translatedLabel;
        }

        return match ($this) {
            self::Percent => 'Percentage off',
            self::Fixed => 'Fixed amount off',
        };
    }
}
