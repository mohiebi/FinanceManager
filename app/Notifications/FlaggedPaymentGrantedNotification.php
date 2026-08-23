<?php

namespace App\Notifications;

use App\Models\PaymentRiskCase;

class FlaggedPaymentGrantedNotification extends SubscriptionNotification
{
    public function __construct(public readonly PaymentRiskCase $case)
    {
        parent::__construct();
    }

    protected function typeKey(): string
    {
        return 'flagged_payment_granted';
    }

    /** @return array<string, string> */
    protected function replacements(object $notifiable): array
    {
        return ['address' => $this->case->source_address];
    }
}
