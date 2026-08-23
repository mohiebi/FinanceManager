<?php

namespace App\Notifications;

use App\Models\PaymentSettlement;

class SettlementNeedsAttentionNotification extends SubscriptionNotification
{
    public function __construct(public readonly PaymentSettlement $settlement)
    {
        parent::__construct();
    }

    protected function typeKey(): string
    {
        return 'settlement_needs_attention';
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
            'operation' => $this->settlement->operation_id,
            'reason' => $this->settlement->failure_code ?? 'unknown',
        ];
    }
}
