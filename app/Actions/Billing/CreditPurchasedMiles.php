<?php

namespace App\Actions\Billing;

use App\Actions\Miles\AdjustMiles;
use App\Enums\MilesReason;
use App\Models\CouponRedemption;
use App\Models\MileLedgerEntry;
use App\Models\SubscriptionPayment;

final readonly class CreditPurchasedMiles
{
    public function __construct(private AdjustMiles $adjustMiles) {}

    public function __invoke(SubscriptionPayment|CouponRedemption $purchase): MileLedgerEntry
    {
        return ($this->adjustMiles)(
            user: $purchase->user,
            amount: (int) $purchase->miles,
            reason: $purchase instanceof SubscriptionPayment ? MilesReason::PackPurchase : MilesReason::GiftCode,
            idempotencyKey: 'billing:'.$purchase->getTable().':'.$purchase->getKey(),
            source: $purchase,
            metadata: ['pack' => $purchase->miles_pack->value],
        );
    }
}
