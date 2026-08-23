<?php

namespace App\Notifications;

use App\Models\PaymentRiskCase;

class FlaggedPaymentReviewNotification extends SubscriptionNotification
{
    public function __construct(public readonly PaymentRiskCase $case)
    {
        parent::__construct();
    }

    protected function typeKey(): string
    {
        return 'flagged_payment_review';
    }

    /** @return array<string, string> */
    protected function replacements(object $notifiable): array
    {
        return [
            'deadline' => $this->case->review_expires_at->toDayDateTimeString(),
            'address' => $this->case->source_address,
        ];
    }
}
