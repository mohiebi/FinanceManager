<?php

namespace App\Enums;

use App\Actions\Billing\GrantProAccess;

/**
 * Why a user's Pro entitlement moved.
 *
 * Every write to `users.pro_until` records one of these, so "why is this user
 * Pro?" is answerable from the audit trail alone.
 *
 * @see GrantProAccess
 */
enum GrantReason: string
{
    /** An on-chain payment verified automatically. */
    case Payment = 'payment';

    /** An admin accepted a payment the chain checks could not settle on their own. */
    case AdminApprovePayment = 'admin_approve_payment';

    /** An admin granted months outright, with no payment behind them. */
    case AdminGrant = 'admin_grant';

    /** An admin ended an entitlement early. */
    case AdminRevoke = 'admin_revoke';

    public function label(): string
    {
        $translationKey = "billing.grant_reasons.{$this->value}";
        $translatedLabel = __($translationKey);

        if ($translatedLabel !== $translationKey) {
            return $translatedLabel;
        }

        return match ($this) {
            self::Payment => 'Payment',
            self::AdminApprovePayment => 'Payment approved by admin',
            self::AdminGrant => 'Granted by admin',
            self::AdminRevoke => 'Revoked by admin',
        };
    }

    /** Whether an administrator, rather than the chain, produced this grant. */
    public function isManual(): bool
    {
        return $this !== self::Payment;
    }
}
