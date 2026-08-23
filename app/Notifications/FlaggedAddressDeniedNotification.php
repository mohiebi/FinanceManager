<?php

namespace App\Notifications;

use App\Models\SubscriptionPayment;

class FlaggedAddressDeniedNotification extends SubscriptionNotification
{
    public function __construct(public readonly SubscriptionPayment $payment, public readonly string $reason)
    {
        parent::__construct();
    }

    protected function typeKey(): string
    {
        return 'flagged_address_denied';
    }

    /** @return array<string, string> */
    protected function replacements(object $notifiable): array
    {
        return ['reason' => $this->reason];
    }
}
