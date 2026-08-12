<?php

namespace App\Support\Billing;

use App\Enums\PaymentFailureReason;
use Carbon\CarbonImmutable;

/**
 * A verdict on one payment.
 *
 * Three shapes, and the difference between them is what protects a buyer:
 * confirmed settles, retryable waits, and only a rejection is terminal. A
 * failure that might read differently later must never be allowed to become
 * the second kind.
 */
final readonly class PaymentVerification
{
    private function __construct(
        public bool $confirmed,
        public bool $retryable,
        public ?PaymentFailureReason $reason = null,
        public ?string $receivedAmount = null,
        public ?int $confirmations = null,
        public ?int $blockNumber = null,
        public ?CarbonImmutable $blockTimestamp = null,
        public ?string $fromAddress = null,
    ) {}

    public static function confirmed(TokenTransfer $transfer): self
    {
        return new self(
            confirmed: true,
            retryable: false,
            receivedAmount: $transfer->creditedAmount,
            confirmations: $transfer->confirmations,
            blockNumber: $transfer->blockNumber,
            blockTimestamp: $transfer->blockTimestamp,
            fromAddress: $transfer->fromAddress,
        );
    }

    /**
     * On the chain and correct, but not yet buried deep enough to be safe.
     */
    public static function awaitingConfirmations(TokenTransfer $transfer): self
    {
        return new self(
            confirmed: false,
            retryable: true,
            confirmations: $transfer->confirmations,
            blockNumber: $transfer->blockNumber,
            blockTimestamp: $transfer->blockTimestamp,
            fromAddress: $transfer->fromAddress,
        );
    }

    /**
     * Could not be judged yet. Never terminal.
     */
    public static function retry(PaymentFailureReason $reason): self
    {
        return new self(confirmed: false, retryable: true, reason: $reason);
    }

    /**
     * Judged, and the answer will not change.
     */
    public static function rejected(PaymentFailureReason $reason, ?TokenTransfer $transfer = null): self
    {
        return new self(
            confirmed: false,
            retryable: false,
            reason: $reason,
            receivedAmount: $transfer?->creditedAmount,
            confirmations: $transfer?->confirmations,
            blockNumber: $transfer?->blockNumber,
            blockTimestamp: $transfer?->blockTimestamp,
            fromAddress: $transfer?->fromAddress,
        );
    }
}
