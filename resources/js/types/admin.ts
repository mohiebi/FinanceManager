export type AdminSummary = {
    total_customers: number;
    new_customers_30d: number;
    new_customers_change: number | null;
    online_customers: number;
    active_customers_7d: number;
    active_customers_30d: number;
    telegram_customers: number;
    telegram_adoption: number;
    verified_customers: number;
    verification_rate: number;
    completed_profiles: number;
    profile_completion_rate: number;
};

export type AdminChartBreakdown = {
    labels: string[];
    values: number[];
};

export type AdminAnalytics = {
    growth: {
        labels: string[];
        new_customers: number[];
        cumulative_customers: number[];
    };
    product_adoption: AdminChartBreakdown;
    authentication_mix: AdminChartBreakdown;
    locales: AdminChartBreakdown;
};

export type AdminUser = {
    id: number;
    name: string;
    email: string;
    joined_at: string;
    last_active_at: string | null;
    is_verified: boolean;
    profile_complete: boolean;
    telegram_connected: boolean;
    auth_method: string;
    transaction_count: number;
    investment_count: number;
    bill_count: number;
};

export type AdminUserPaginator = {
    data: AdminUser[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type AdminFilters = {
    search: string;
    activity: 'all' | 'online' | '7d' | '30d' | 'inactive' | 'never';
    telegram: 'all' | 'connected' | 'disconnected';
    verification: 'all' | 'verified' | 'unverified';
    sort: 'newest' | 'oldest' | 'last_active';
};
