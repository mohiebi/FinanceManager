<?php

namespace App\Notifications;

use Carbon\CarbonInterface;

/**
 * Pro has lapsed.
 */
class SubscriptionExpiredNotification extends SubscriptionNotification
{
    public function __construct(public readonly CarbonInterface $proUntil)
    {
        parent::__construct();
    }

    protected function typeKey(): string
    {
        return 'subscription_expired';
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(object $notifiable): array
    {
        return [
            'date' => $this->displayDate($notifiable, $this->proUntil),
        ];
    }
}
