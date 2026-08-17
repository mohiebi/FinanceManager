<?php

namespace App\Notifications;

class DepositPoolLowNotification extends SubscriptionNotification
{
    public function __construct(
        public readonly string $network,
        public readonly int $remaining,
    ) {
        parent::__construct();
    }

    protected function typeKey(): string
    {
        return 'deposit_pool_low';
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
            'network' => trans("billing.networks.{$this->network}.label", [], $this->localeFor($notifiable)),
            'count' => (string) $this->remaining,
        ];
    }
}
