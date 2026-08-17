<?php

namespace App\Notifications;

class PaymentScreeningDelayedNotification extends SubscriptionNotification
{
    public function __construct(public readonly int $waiting)
    {
        parent::__construct();
    }

    protected function typeKey(): string
    {
        return 'payment_screening_delayed';
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
        return [
            'count' => (string) $this->waiting,
        ];
    }
}
