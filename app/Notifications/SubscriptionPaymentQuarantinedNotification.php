<?php

namespace App\Notifications;

use App\Enums\PaymentFailureReason;
use App\Models\SubscriptionPayment;

class SubscriptionPaymentQuarantinedNotification extends SubscriptionNotification
{
    public function __construct(public readonly SubscriptionPayment $payment)
    {
        parent::__construct();
    }

    protected function typeKey(): string
    {
        return 'subscription_payment_quarantined';
    }

    /** @return array<string, string> */
    protected function replacements(object $notifiable): array
    {
        return [
            'plan' => $this->payment->plan->label(),
            'reason' => $this->payment->failure_reason?->label() ?? '',
            'explanation' => $this->payment->failure_reason === PaymentFailureReason::SanctionedSender
                ? trans('notifications.subscription_payment_quarantined.sanctioned_explanation', [], $this->localeFor($notifiable))
                : trans('notifications.subscription_payment_quarantined.flagged_explanation', [], $this->localeFor($notifiable)),
        ];
    }
}
