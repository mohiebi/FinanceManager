<?php

namespace App\Notifications;

use App\Models\SubscriptionPayment;

/**
 * A claimed transaction could not be honoured.
 *
 * Only ever sent for a terminal verdict. A payment still being retried, or one
 * waiting on a human, says nothing — telling somebody their money failed while
 * we are still deciding would be worse than silence.
 */
class SubscriptionPaymentFailedNotification extends SubscriptionNotification
{
    public function __construct(public readonly SubscriptionPayment $payment)
    {
        parent::__construct();
    }

    protected function typeKey(): string
    {
        return 'subscription_payment_failed';
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(object $notifiable): array
    {
        return [
            'plan' => $this->payment->plan->label(),
            'reason' => $this->payment->failure_reason?->label() ?? '',
        ];
    }
}
