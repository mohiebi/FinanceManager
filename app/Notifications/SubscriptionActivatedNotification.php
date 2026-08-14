<?php

namespace App\Notifications;

use App\Models\SubscriptionPayment;
use Carbon\CarbonInterface;

/**
 * A payment settled and the months are on the account.
 */
class SubscriptionActivatedNotification extends SubscriptionNotification
{
    public function __construct(
        public readonly SubscriptionPayment $payment,
        public readonly CarbonInterface $proUntil,
    ) {
        parent::__construct();
    }

    protected function typeKey(): string
    {
        return 'subscription_activated';
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(object $notifiable): array
    {
        return [
            // The plan name and the date are our own invoice terms. The
            // transaction hash and the sending address deliberately are not
            // here: this ends up in an unencrypted column, and linking an
            // account to a wallet is not ours to publish.
            'plan' => $this->payment->plan->label(),
            'date' => $this->displayDate($notifiable, $this->proUntil),
        ];
    }
}
