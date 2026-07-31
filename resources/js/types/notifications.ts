export type NotificationDataType =
    | 'bill_due_today'
    | 'bill_due_tomorrow'
    | 'streak_open'
    | 'milestone';

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
