<?php

namespace App\Notifications;

use App\Models\SubscriptionPayment;

/**
 * Tells the operator that a payment is waiting on a human decision.
 *
 * The last link in the chain that keeps this feature honest. Verification
 * refuses to guess whenever guessing could either pocket somebody's money
 * without giving them Pro or hand out Pro nobody paid for, and every one of
 * those refusals ends in *a person decides*. Without this, nothing says a
 * decision is owed — and the buyer sits watching a page that will not change
 * until somebody happens to open the admin console.
 *
 * Addressed to the whole queue rather than to one payment, because that is how
 * it will be dealt with: an outage strands a dozen at once, and a dozen
 * identical emails is worse than one that says how many are waiting.
 */
class PaymentNeedsReviewNotification extends SubscriptionNotification
{
    public function __construct(
        public readonly SubscriptionPayment $payment,
        public readonly int $waiting,
    ) {
        parent::__construct();
    }

    protected function typeKey(): string
    {
        return 'payment_needs_review';
    }

    protected function actionUrl(): string
    {
        return route('admin.billing');
    }

    protected function actionKey(): string
    {
        return 'notifications.review_action';
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(object $notifiable): array
    {
        return [
            'count' => (string) $this->waiting,
            'reason' => $this->payment->failure_reason?->label() ?? '',
        ];
    }
}
