<?php

namespace App\Actions\Billing;

use App\Enums\CouponRejection;
use App\Models\Coupon;

/**
 * Whether a coupon code can be used, and what it is worth if so.
 *
 * Carries the discounted price rather than leaving the caller to recompute it,
 * so the figure shown to the buyer and the figure the payment is opened at come
 * from the same calculation.
 */
final readonly class CouponResolution
{
    private function __construct(
        public bool $accepted,
        public ?Coupon $coupon = null,
        public ?CouponRejection $rejection = null,
        public string $listPriceUsd = '0.00',
        public string $discountUsd = '0.00',
        public string $finalPriceUsd = '0.00',
        public bool $coversEverything = false,
    ) {}

    public static function accepted(
        Coupon $coupon,
        string $listPriceUsd,
        string $discountUsd,
        string $finalPriceUsd,
        bool $coversEverything,
    ): self {
        return new self(
            accepted: true,
            coupon: $coupon,
            listPriceUsd: $listPriceUsd,
            discountUsd: $discountUsd,
            finalPriceUsd: $finalPriceUsd,
            coversEverything: $coversEverything,
        );
    }

    public static function rejected(CouponRejection $rejection): self
    {
        return new self(accepted: false, rejection: $rejection);
    }
}
