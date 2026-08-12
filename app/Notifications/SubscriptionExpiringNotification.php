<?php

namespace App\Notifications;

use Carbon\CarbonInterface;

/**
 * Pro is about to lapse.
 *
 * Load-bearing rather than courteous: a crypto payment cannot be taken again,
 * so nothing renews itself and this is the only thing standing between a paying
 * customer and silently losing access.
 */
class SubscriptionExpiringNotification extends SubscriptionNotification
{
    public function __construct(public readonly CarbonInterface $proUntil)
    {
        parent::__construct();
    }

    protected function typeKey(): string
    {
        return 'subscription_expiring';
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(object $notifiable): array
    {
        return [
            'date' => $this->displayDate($notifiable, $this->proUntil),
            'days' => (string) max(0, (int) ceil(now()->diffInDays($this->proUntil, false))),
        ];
    }
}
