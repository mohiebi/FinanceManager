<?php

namespace App\Enums;

/**
 * A prepaid span of Pro access.
 *
 * Prices live in config rather than here so that repricing is an environment
 * change, and every plan is a fixed number of months rather than a recurring
 * charge: crypto cannot pull funds, so nothing renews itself.
 */
enum BillingPlan: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function label(): string
    {
        $translationKey = "billing.plans.{$this->value}.label";
        $translatedLabel = __($translationKey);

        if ($translatedLabel !== $translationKey) {
            return $translatedLabel;
        }

        return match ($this) {
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::Yearly => 'Yearly',
        };
    }

    public function description(): string
    {
        $translationKey = "billing.plans.{$this->value}.description";
        $translatedDescription = __($translationKey);

        return $translatedDescription !== $translationKey ? $translatedDescription : '';
    }

    public function months(): int
    {
        return (int) config("billing.plans.{$this->value}.months", match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::Yearly => 12,
        });
    }

    /**
     * The plan's price in USD, as a decimal string.
     *
     * A string rather than a float throughout: this is divided by an exchange
     * rate to reach an on-chain amount, and float rounding at that point is the
     * difference between a payment that verifies and one that does not.
     */
    public function priceUsd(): string
    {
        return (string) config("billing.plans.{$this->value}.price_usd", '0.00');
    }

    /** Whether the plans page should give this one visual weight. */
    public function isHighlighted(): bool
    {
        return (bool) config("billing.plans.{$this->value}.highlighted", false);
    }

    /**
     * Plans the buyer can actually choose.
     *
     * A plan priced at zero (or removed from config) stops being offered without
     * needing its case deleted, so historical payments referencing it still read.
     *
     * @return array<int, self>
     */
    public static function available(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $plan): bool => $plan->months() > 0 && (float) $plan->priceUsd() > 0,
        ));
    }
}
