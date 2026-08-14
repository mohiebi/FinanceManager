export type AdminRange = '12m' | '30d' | '90d';

export type AdminSummary = {
    total_customers: number;
    total_customers_change: number | null;
    new_customers: number;
    new_customers_change: number | null;
    online_customers: number;
    active_customers_7d: number;
    active_customers_30d: number;
    active_customers_30d_change: number | null;
    stickiness: number | null;
    telegram_customers: number;
    telegram_adoption: number;
    telegram_customers_change: number | null;
    pro_customers: number;
    pro_adoption: number;
    pro_customers_change: number | null;
    mcp_customers: number;
    mcp_adoption: number;
    mcp_customers_change: number | null;
    verified_customers: number;
    verification_rate: number;
    verified_customers_change: number | null;
    completed_profiles: number;
    profile_completion_rate: number;
    activated_customers: number;
    activation_rate: number | null;
    median_days_to_first_transaction: number | null;
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
    funnel: AdminChartBreakdown;
    acquisition: AdminChartBreakdown;
    product_adoption: AdminChartBreakdown;
    authentication_mix: AdminChartBreakdown;
    locales: AdminChartBreakdown;
    retention_segments: AdminChartBreakdown;
    engagement_trend: {
        labels: string[];
        active_7d: number[];
        active_30d: number[];
    };
};

export type AdminUser = {
    id: number;
    name: string;
    email: string;
    locale: string;
    signup_source: string | null;
    joined_at: string;
    last_active_at: string | null;
    is_verified: boolean;
    profile_complete: boolean;
    telegram_connected: boolean;
    pro: boolean;
    pro_until: string | null;
    mcp_connected: boolean;
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
    pro: 'all' | 'active' | 'inactive';
    mcp: 'all' | 'connected' | 'disconnected';
    verification: 'all' | 'verified' | 'unverified';
    sort:
        | 'newest'
        | 'oldest'
        | 'last_active'
        | 'transactions'
        | 'investments'
        | 'bills';
};
