<?php

namespace App\Notifications;

/**
 * The signer came back up and nobody unlocked it.
 *
 * Its keystore is decrypted into memory and never to disk, which is the whole
 * reason a stolen server disk is not a stolen wallet — and the reason every
 * container restart leaves it unable to sign. Nothing else notices: settlements
 * simply fail retryably and quietly stop, so this alert is the only thing
 * standing between a routine deploy and a queue of stalled payments.
 */
class SignerLockedNotification extends SubscriptionNotification
{
    public function __construct(public readonly int $waitingSettlements)
    {
        parent::__construct();
    }

    protected function typeKey(): string
    {
        return 'signer_locked';
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
        return ['count' => (string) $this->waitingSettlements];
    }
}
