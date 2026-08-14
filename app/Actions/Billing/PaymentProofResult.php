<?php

namespace App\Actions\Billing;

use App\Enums\PaymentFailureReason;

/**
 * The outcome of a buyer claiming a transaction for a payment.
 *
 * Says only whether the claim was taken, not whether the money arrived — that
 * answer comes from the chain, later.
 */
final readonly class PaymentProofResult
{
    private function __construct(
        public bool $accepted,
        public ?PaymentFailureReason $reason = null,
    ) {}

    public static function accepted(): self
    {
        return new self(true);
    }

    public static function rejected(PaymentFailureReason $reason): self
    {
        return new self(false, $reason);
    }
}
