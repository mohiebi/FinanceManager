<?php

namespace App\Enums;

/**
 * Why a coupon code was refused.
 *
 * Kept as an enum rather than a string so the buyer can be told something
 * specific — "this code has already been used" reads very differently from
 * "no such code" — while the controller stays out of the business of deciding
 * what counts as a valid coupon.
 */
enum CouponRejection: string
{
    case NotFound = 'not_found';
    case Disabled = 'disabled';
    case Expired = 'expired';

    /** Issued to somebody else's account. */
    case WrongUser = 'wrong_user';

    /** Every use across all buyers has been claimed. */
    case Exhausted = 'exhausted';

    /** This buyer has used it as many times as they are allowed. */
    case UserLimitReached = 'user_limit_reached';

    public function label(): string
    {
        $translationKey = "billing.coupon.rejections.{$this->value}";
        $translatedLabel = __($translationKey);

        if ($translatedLabel !== $translationKey) {
            return $translatedLabel;
        }

        return match ($this) {
            // Deliberately identical to NotFound: telling somebody a code
            // exists but is not theirs invites guessing at other people's.
            self::NotFound, self::WrongUser => 'That coupon code is not valid.',
            self::Disabled => 'That coupon is no longer active.',
            self::Expired => 'That coupon has expired.',
            self::Exhausted => 'That coupon has been fully redeemed.',
            self::UserLimitReached => 'You have already used that coupon.',
        };
    }
}
