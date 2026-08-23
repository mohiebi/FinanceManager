<?php

namespace App\Notifications;

use App\Models\SubscriptionPayment;

class FlaggedAddressLimitNotification extends SubscriptionNotification
{
    public function __construct(public readonly SubscriptionPayment $payment)
    {
        parent::__construct();
    }

    protected function typeKey(): string
    {
        return 'flagged_address_limit_admin';
    }

    protected function actionUrl(): string
    {
        return route('admin.billing');
    }

    protected function actionKey(): string
    {
        return 'notifications.review_action';
    }

    /** @return array<string, string> */
    protected function replacements(object $notifiable): array
    {
        return ['email' => $this->payment->user->email];
    }
}
