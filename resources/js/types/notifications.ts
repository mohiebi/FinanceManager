/**
 * Every value App\Notifications\* writes to `data.type`.
 *
 * The subscription and review types were missing here, so the union claimed
 * four kinds existed when nine do — which any exhaustive switch on this type
 * would have believed. Kept in step by hand with the `typeKey()` of each
 * notification class.
 */
export type NotificationDataType =
    | 'bill_due_today'
    | 'bill_due_tomorrow'
    | 'streak_open'
    | 'milestone'
    | 'payment_needs_review'
    | 'subscription_activated'
    | 'subscription_expiring'
    | 'subscription_expired'
    | 'subscription_payment_failed';

export interface NotificationData {
    type: NotificationDataType;
    title: string;
    body: string;
    bill_id?: number;
    due_date?: string;
}

export interface NotificationItem {
    id: string;
    data: NotificationData;
    read_at: string | null;
    created_at: string;
}
