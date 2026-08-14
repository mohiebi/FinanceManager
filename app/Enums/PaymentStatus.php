<?php

namespace App\Enums;

/**
 * Where a subscription payment stands.
 *
 * There is deliberately no separate "verifying" state: a payment being checked
 * is {@see self::Submitted} with a confirmation count and an attempt count
 * beside it, which says everything a sixth transition would have, without a
 * sixth transition to get stuck in.
 */
enum PaymentStatus: string
{
    /** An intent exists. The buyer has an address and an amount, and has not paid yet. */
    case Pending = 'pending';

    /** A transaction hash has been claimed and is being checked against the chain. */
    case Submitted = 'submitted';

    /** Settled, and the months have been granted. */
    case Confirmed = 'confirmed';

    /** Refused for a reason no retry can change. */
    case Failed = 'failed';

    /** The intent's window closed before anything was paid. */
    case Expired = 'expired';

    /** Paid back by hand. Bookkeeping only — nothing here ever sends funds. */
    case Refunded = 'refunded';

    public function label(): string
    {
        $translationKey = "billing.statuses.{$this->value}";
        $translatedLabel = __($translationKey);

        if ($translatedLabel !== $translationKey) {
            return $translatedLabel;
        }

        return match ($this) {
            self::Pending => 'Awaiting payment',
            self::Submitted => 'Checking',
            self::Confirmed => 'Paid',
            self::Failed => 'Failed',
            self::Expired => 'Expired',
            self::Refunded => 'Refunded',
        };
    }

    /** Whether the payment has reached a state nothing will move it out of. */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Pending, self::Submitted => false,
            default => true,
        };
    }

    public function grantsEntitlement(): bool
    {
        return $this === self::Confirmed;
    }

    /** Whether the buyer's page should keep polling for a change. */
    public function isSettling(): bool
    {
        return $this === self::Submitted;
    }

    /** Badge tone on both the buyer's history and the admin queue. */
    public function tone(): string
    {
        return match ($this) {
            self::Confirmed => 'positive',
            self::Pending, self::Submitted => 'pending',
            self::Failed => 'negative',
            self::Expired, self::Refunded => 'neutral',
        };
    }
}
