<script setup lang="ts">
import { Deferred, Head, Link, router, usePoll } from '@inertiajs/vue3';
import {
    Activity,
    BadgeCheck,
    Bot,
    ChevronLeft,
    ChevronRight,
    CircleUserRound,
    Clock3,
    Download,
    Gauge,
    Gift,
    Rocket,
    Search,
    Sparkles,
    SlidersHorizontal,
    ShieldCheck,
    UserCheck,
    Users,
    WalletCards,
    X,
} from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import AdminCustomerDrawer from '@/components/admin/AdminCustomerDrawer.vue';
import AdminKpiCard from '@/components/admin/AdminKpiCard.vue';
import DonutChart from '@/components/charts/DonutChart.vue';
import FunnelChart from '@/components/charts/FunnelChart.vue';
import LineChart from '@/components/charts/LineChart.vue';
import RankedBarChart from '@/components/charts/RankedBarChart.vue';
import {
    formatAdminDate,
    formatAdminNumber,
    formatAdminPercentage,
    isRecentlyOnline,
    relativeActivity,
} from '@/lib/adminFormat';
import { dashboard as adminDashboard } from '@/routes/admin';
import { billing as adminBilling } from '@/routes/admin';
import adminCustomers from '@/routes/admin/customers';
import type {
    AdminAnalytics,
    AdminFilters,
    AdminRange,
    AdminSummary,
    AdminUser,
    AdminUserPaginator,
} from '@/types';

const props = defineProps<{
    summary: AdminSummary;
    analytics?: AdminAnalytics;
    users: AdminUserPaginator;
    filters: AdminFilters;
    range: AdminRange;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Admin', href: adminDashboard() }],
    },
});

const search = ref(props.filters.search);
const activity = ref<AdminFilters['activity']>(props.filters.activity);
const telegram = ref<AdminFilters['telegram']>(props.filters.telegram);
const pro = ref<AdminFilters['pro']>(props.filters.pro);
const mcp = ref<AdminFilters['mcp']>(props.filters.mcp);
const verification = ref<AdminFilters['verification']>(
    props.filters.verification,
);
const sort = ref<AdminFilters['sort']>(props.filters.sort);
const range = ref<AdminRange>(props.range);

type AdminTab = 'customers' | 'growth' | 'engagement' | 'miles';
const activeTab = ref<AdminTab>('customers');
const tabOptions: { value: AdminTab; label: string }[] = [
    { value: 'customers', label: 'Customers' },
    { value: 'growth', label: 'Growth' },
    { value: 'engagement', label: 'Engagement' },
    { value: 'miles', label: 'Miles' },
];

const isFiltering = ref(false);
const isChangingRange = ref(false);
const selectedUser = ref<AdminUser | null>(null);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

// Keeps "online now" and activity badges honest without a manual refresh.
usePoll(120_000, { only: ['summary'] });

const rangeOptions: { value: AdminRange; label: string }[] = [
    { value: '30d', label: '30 days' },
    { value: '90d', label: '90 days' },
    { value: '12m', label: '12 months' },
];

const rangeLabel = computed(
    () =>
        ({
            '30d': 'last 30 days',
            '90d': 'last 90 days',
            '12m': 'last 12 months',
        })[range.value],
);

const growthSeries = computed(() => [
    {
        name: 'New customers',
        key: 'new_customers',
        color: '#02CD86',
        data: props.analytics?.growth.new_customers ?? [],
        type: 'area' as const,
    },
    {
        name: 'Total customers',
        key: 'cumulative_customers',
        color: '#6C4EE9',
        data: props.analytics?.growth.cumulative_customers ?? [],
        type: 'area' as const,
    },
]);

const engagementSeries = computed(() => [
    {
        name: 'Active 7 days',
        key: 'active_7d',
        color: '#02CD86',
        data: props.analytics?.engagement_trend.active_7d ?? [],
    },
    {
        name: 'Active 30 days',
        key: 'active_30d',
        color: '#6C4EE9',
        data: props.analytics?.engagement_trend.active_30d ?? [],
    },
]);

const milesRetentionSeries = computed(() => [
    {
        name: 'Claimed Miles',
        key: 'claimed',
        color: '#02CD86',
        data: props.analytics?.miles.retention.claimed ?? [],
    },
    {
        name: 'Never claimed',
        key: 'never_claimed',
        color: '#6C4EE9',
        data: props.analytics?.miles.retention.never_claimed ?? [],
    },
]);

const milesEconomyValues = computed(() =>
    (props.analytics?.miles.economy.issued ?? []).map(
        (issued, index) =>
            issued - (props.analytics?.miles.economy.spent[index] ?? 0),
    ),
);

const formatHours = (value: number | null): string =>
    value === null ? '—' : `${formatAdminNumber(value)}h`;

const formatUsd = (value: number | null): string =>
    value === null ? '—' : `$${value.toFixed(4)}`;

const authenticationTotal = computed(() =>
    (props.analytics?.authentication_mix.values ?? []).reduce(
        (total, value) => total + value,
        0,
    ),
);

const localeTotal = computed(() =>
    (props.analytics?.locales.values ?? []).reduce(
        (total, value) => total + value,
        0,
    ),
);

const hasFilters = computed(
    () =>
        search.value !== '' ||
        activity.value !== 'all' ||
        telegram.value !== 'all' ||
        pro.value !== 'all' ||
        mcp.value !== 'all' ||
        verification.value !== 'all' ||
        sort.value !== 'newest',
);

const exportUrl = computed(() => {
    const query: Record<string, string> = {};

    if (search.value !== '') {
        query.search = search.value;
    }

    if (activity.value !== 'all') {
        query.activity = activity.value;
    }

    if (telegram.value !== 'all') {
        query.telegram = telegram.value;
    }

    if (pro.value !== 'all') {
        query.pro = pro.value;
    }

    if (mcp.value !== 'all') {
        query.mcp = mcp.value;
    }

    if (verification.value !== 'all') {
        query.verification = verification.value;
    }

    if (sort.value !== 'newest') {
        query.sort = sort.value;
    }

    return adminCustomers.export.url({ query });
});

watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search;
        activity.value = filters.activity;
        telegram.value = filters.telegram;
        pro.value = filters.pro;
        mcp.value = filters.mcp;
        verification.value = filters.verification;
        sort.value = filters.sort;
    },
    { deep: true },
);

watch(
    () => props.range,
    (value) => {
        range.value = value;
    },
);

watch(search, (value, previousValue) => {
    if (value === props.filters.search || value === previousValue) {
        return;
    }

    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => applyFilters(), 350);
});

onUnmounted(() => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }
});

function queryPayload(page = 1): Record<string, string | number | null> {
    return {
        search: search.value || null,
        activity: activity.value === 'all' ? null : activity.value,
        telegram: telegram.value === 'all' ? null : telegram.value,
        pro: pro.value === 'all' ? null : pro.value,
        mcp: mcp.value === 'all' ? null : mcp.value,
        verification: verification.value === 'all' ? null : verification.value,
        sort: sort.value === 'newest' ? null : sort.value,
        range: range.value === '12m' ? null : range.value,
        page: page > 1 ? page : null,
    };
}

function applyFilters(page = 1): void {
    router.get(adminDashboard.url(), queryPayload(page), {
        only: ['users', 'filters'],
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onStart: () => {
            isFiltering.value = true;
        },
        onFinish: () => {
            isFiltering.value = false;
        },
    });
}

function changeRange(value: AdminRange): void {
    if (value === range.value) {
        return;
    }

    range.value = value;

    router.get(adminDashboard.url(), queryPayload(props.users.current_page), {
        only: ['summary', 'analytics', 'range'],
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onStart: () => {
            isChangingRange.value = true;
        },
        onFinish: () => {
            isChangingRange.value = false;
        },
    });
}

function toggleActivityFilter(value: AdminFilters['activity']): void {
    activity.value = activity.value === value ? 'all' : value;
    applyFilters();
}

function toggleTelegramFilter(): void {
    telegram.value = telegram.value === 'connected' ? 'all' : 'connected';
    applyFilters();
}

function toggleProFilter(): void {
    pro.value = pro.value === 'active' ? 'all' : 'active';
    applyFilters();
}

function toggleMcpFilter(): void {
    mcp.value = mcp.value === 'connected' ? 'all' : 'connected';
    applyFilters();
}

function toggleVerificationFilter(): void {
    verification.value = verification.value === 'verified' ? 'all' : 'verified';
    applyFilters();
}

function sortBy(
    value: AdminFilters['sort'],
    fallback: AdminFilters['sort'] = 'newest',
): void {
    sort.value = sort.value === value ? fallback : value;
    applyFilters();
}

function clearFilters(): void {
    search.value = '';
    activity.value = 'all';
    telegram.value = 'all';
    verification.value = 'all';
    sort.value = 'newest';
    applyFilters();
}

function activityTone(user: AdminUser): string {
    if (!user.last_active_at) {
        return 'bg-white/5 text-[#686868]';
    }

    return isRecentlyOnline(user.last_active_at)
        ? 'bg-[#0d2e22] text-[#7ee8c4]'
        : 'bg-white/5 text-[#b3b3b3]';
}
</script>

<template>
    <div class="contents">
        <Head title="Admin analytics" />
        <main
            class="min-h-[calc(100vh-92px)] bg-[#111111] px-4 py-6 text-white sm:px-6 lg:px-8 lg:py-8"
        >
            <div class="mx-auto flex w-full max-w-[1600px] flex-col gap-6">
                <header
                    class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between"
                >
                    <div>
                        <p class="text-sm font-medium text-[#02CD86]">
                            Administration
                        </p>
                        <h1
                            class="mt-1 text-3xl font-semibold tracking-tight text-white"
                        >
                            Customer analytics
                        </h1>
                        <p
                            class="mt-2 max-w-2xl text-sm leading-6 text-[#989898]"
                        >
                            A privacy-conscious view of customer growth,
                            engagement, and product adoption.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <Link
                            :href="adminBilling().url"
                            class="rounded-lg px-3 py-1.5 text-xs text-[#989898] ring-1 ring-white/10 transition hover:text-white"
                        >
                            Subscriptions
                        </Link>
                        <div
                            class="flex items-center gap-2 text-xs text-[#686868]"
                        >
                            <Clock3 class="size-4 text-[#02CD86]" />
                            Updates every five minutes
                        </div>
                        <div
                            class="flex rounded-xl border border-white/10 bg-[#1a1a1a] p-1"
                            role="group"
                            aria-label="Analysis period"
                        >
                            <button
                                v-for="option in rangeOptions"
                                :key="option.value"
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                                :class="
                                    range === option.value
                                        ? 'bg-[#02cd86] text-[#101010]'
                                        : 'text-[#989898] hover:text-white'
                                "
                                :aria-pressed="range === option.value"
                                @click="changeRange(option.value)"
                            >
                                {{ option.label }}
                            </button>
                        </div>
                    </div>
                </header>

                <section
                    aria-label="Customer summary"
                    class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6"
                    :class="isChangingRange ? 'opacity-60' : ''"
                >
                    <AdminKpiCard
                        label="Total customers"
                        :value="formatAdminNumber(summary.total_customers)"
                        caption="All time"
                        :delta="summary.total_customers_change"
                        delta-label="vs 30 days ago"
                    >
                        <template #icon>
                            <span
                                class="flex size-9 items-center justify-center rounded-xl bg-[#0d2e22]"
                            >
                                <Users class="size-[18px] text-[#02CD86]" />
                            </span>
                        </template>
                        <template #footnote>
                            {{ formatAdminNumber(summary.new_customers) }}
                            joined in the {{ rangeLabel }}
                            <span
                                v-if="summary.new_customers_change !== null"
                                :class="
                                    summary.new_customers_change >= 0
                                        ? 'text-[#7ee8c4]'
                                        : 'text-[#ff9f9f]'
                                "
                            >
                                ({{
                                    summary.new_customers_change >= 0
                                        ? '+'
                                        : ''
                                }}{{ summary.new_customers_change.toFixed(1) }}%
                                vs previous)
                            </span>
                        </template>
                    </AdminKpiCard>

                    <AdminKpiCard
                        label="Active customers"
                        :value="formatAdminNumber(summary.active_customers_30d)"
                        caption="30 days"
                        :delta="summary.active_customers_30d_change"
                        delta-label="vs 30 days ago"
                        clickable
                        :active="filters.activity === '30d'"
                        @select="toggleActivityFilter('30d')"
                    >
                        <template #icon>
                            <span
                                class="flex size-9 items-center justify-center rounded-xl bg-[#0d2e22]"
                            >
                                <Activity class="size-[18px] text-[#02CD86]" />
                            </span>
                        </template>
                        <template #footnote>
                            {{ formatAdminNumber(summary.active_customers_7d) }}
                            weekly
                            <span v-if="summary.stickiness !== null">
                                ·
                                {{ formatAdminPercentage(summary.stickiness) }}
                                stickiness
                            </span>
                        </template>
                    </AdminKpiCard>

                    <AdminKpiCard
                        label="Online now"
                        :value="formatAdminNumber(summary.online_customers)"
                        caption="15 minutes"
                        footnote="Recently active authenticated customers"
                        clickable
                        :active="filters.activity === 'online'"
                        @select="toggleActivityFilter('online')"
                    >
                        <template #icon>
                            <span
                                class="flex size-9 items-center justify-center rounded-xl bg-[#0d2e22]"
                            >
                                <CircleUserRound
                                    class="size-[18px] text-[#02CD86]"
                                />
                            </span>
                        </template>
                    </AdminKpiCard>

                    <AdminKpiCard
                        label="Activation"
                        :value="
                            summary.activation_rate !== null
                                ? formatAdminPercentage(summary.activation_rate)
                                : '—'
                        "
                        :caption="rangeLabel"
                        :footnote="
                            summary.median_days_to_first_transaction !== null
                                ? `Median ${summary.median_days_to_first_transaction} days to first transaction`
                                : 'First transaction within 7 days of joining'
                        "
                    >
                        <template #icon>
                            <span
                                class="flex size-9 items-center justify-center rounded-xl bg-[#0d2e22]"
                            >
                                <Rocket class="size-[18px] text-[#02CD86]" />
                            </span>
                        </template>
                    </AdminKpiCard>

                    <AdminKpiCard
                        label="Telegram connected"
                        :value="formatAdminNumber(summary.telegram_customers)"
                        :caption="
                            formatAdminPercentage(summary.telegram_adoption)
                        "
                        caption-tone="purple"
                        :delta="summary.telegram_customers_change"
                        delta-label="vs 30 days ago"
                        footnote="Customers linked to the Telegram bot"
                        clickable
                        :active="filters.telegram === 'connected'"
                        @select="toggleTelegramFilter"
                    >
                        <template #icon>
                            <span
                                class="flex size-9 items-center justify-center rounded-xl bg-[#201d31]"
                            >
                                <Bot class="size-[18px] text-[#947bff]" />
                            </span>
                        </template>
                    </AdminKpiCard>

                    <AdminKpiCard
                        label="Pro accounts"
                        :value="formatAdminNumber(summary.pro_customers)"
                        :caption="formatAdminPercentage(summary.pro_adoption)"
                        caption-tone="green"
                        :delta="summary.pro_customers_change"
                        delta-label="vs 30 days ago"
                        footnote="Customers with paid access right now"
                        clickable
                        :active="filters.pro === 'active'"
                        @select="toggleProFilter"
                    >
                        <template #icon>
                            <span
                                class="flex size-9 items-center justify-center rounded-xl bg-[#12251c]"
                            >
                                <BadgeCheck
                                    class="size-[18px] text-[#02CD86]"
                                />
                            </span>
                        </template>
                    </AdminKpiCard>

                    <AdminKpiCard
                        label="AI assistant"
                        :value="formatAdminNumber(summary.mcp_customers)"
                        :caption="formatAdminPercentage(summary.mcp_adoption)"
                        caption-tone="purple"
                        :delta="summary.mcp_customers_change"
                        delta-label="vs 30 days ago"
                        footnote="Customers with a live MCP connection"
                        clickable
                        :active="filters.mcp === 'connected'"
                        @select="toggleMcpFilter"
                    >
                        <template #icon>
                            <span
                                class="flex size-9 items-center justify-center rounded-xl bg-[#201d31]"
                            >
                                <Sparkles class="size-[18px] text-[#947bff]" />
                            </span>
                        </template>
                    </AdminKpiCard>

                    <AdminKpiCard
                        label="Verified email"
                        :value="formatAdminNumber(summary.verified_customers)"
                        :caption="
                            formatAdminPercentage(summary.verification_rate)
                        "
                        caption-tone="green"
                        :delta="summary.verified_customers_change"
                        delta-label="vs 30 days ago"
                        footnote="Customers with a verified email address"
                        clickable
                        :active="filters.verification === 'verified'"
                        @select="toggleVerificationFilter"
                    >
                        <template #icon>
                            <span
                                class="flex size-9 items-center justify-center rounded-xl bg-[#0d2e22]"
                            >
                                <UserCheck class="size-[18px] text-[#02CD86]" />
                            </span>
                        </template>
                    </AdminKpiCard>
                </section>

                <div
                    class="flex w-fit items-center gap-0.5 rounded-xl border border-white/10 bg-[#1a1a1a] p-1"
                    role="tablist"
                    aria-label="Admin sections"
                >
                    <button
                        v-for="option in tabOptions"
                        :key="option.value"
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === option.value"
                        class="rounded-lg px-4 py-1.5 text-sm font-medium transition"
                        :class="
                            activeTab === option.value
                                ? 'bg-[#02cd86] text-[#101010]'
                                : 'text-[#989898] hover:text-white'
                        "
                        @click="activeTab = option.value"
                    >
                        {{ option.label }}
                    </button>
                </div>

                <Deferred data="analytics">
                    <template #fallback>
                        <section
                            aria-label="Loading analytics"
                            class="grid gap-4 xl:grid-cols-2"
                        >
                            <div
                                v-for="index in 8"
                                :key="index"
                                class="h-[340px] animate-pulse rounded-[18px] bg-[#1a1a1a] ring-1 ring-white/10"
                            >
                                <div class="m-5 h-5 w-40 rounded bg-white/10" />
                                <div
                                    class="mx-5 mt-8 h-56 rounded-xl bg-white/[0.04]"
                                />
                            </div>
                        </section>
                    </template>

                    <section
                        v-if="analytics"
                        aria-label="Analytics charts"
                        :class="isChangingRange ? 'opacity-60' : ''"
                    >
                        <div
                            v-show="activeTab === 'growth'"
                            aria-label="Growth"
                            class="grid gap-4 xl:grid-cols-2"
                        >
                            <article
                                class="overflow-hidden rounded-[18px] bg-[#1a1a1a] p-5 ring-1 ring-white/10 xl:col-span-2"
                            >
                                <div
                                    class="flex items-start justify-between gap-4"
                                >
                                    <div>
                                        <h2 class="text-lg font-medium">
                                            Customer growth
                                        </h2>
                                        <p class="mt-1 text-sm text-[#686868]">
                                            New and cumulative customers over
                                            the
                                            {{ rangeLabel }}
                                        </p>
                                    </div>
                                    <Users class="size-5 text-[#02CD86]" />
                                </div>
                                <LineChart
                                    class="mt-4"
                                    :series="growthSeries"
                                    :categories="analytics.growth.labels"
                                    raw-labels
                                    value-suffix=""
                                    no-data-text="No customer growth data yet"
                                    :height="280"
                                />
                            </article>

                            <article
                                class="overflow-hidden rounded-[18px] bg-[#1a1a1a] p-5 ring-1 ring-white/10"
                            >
                                <h2 class="text-lg font-medium">
                                    Signup-to-value funnel
                                </h2>
                                <p class="mt-1 text-sm text-[#686868]">
                                    Customers who joined in the {{ rangeLabel }}
                                </p>
                                <FunnelChart
                                    class="mt-4"
                                    :labels="analytics.funnel.labels"
                                    :values="analytics.funnel.values"
                                />
                            </article>

                            <article
                                class="overflow-hidden rounded-[18px] bg-[#1a1a1a] p-5 ring-1 ring-white/10"
                            >
                                <h2 class="text-lg font-medium">
                                    Acquisition channels
                                </h2>
                                <p class="mt-1 text-sm text-[#686868]">
                                    Where customers who joined in the
                                    {{ rangeLabel }} came from
                                </p>
                                <RankedBarChart
                                    v-if="
                                        analytics.acquisition.values.length > 0
                                    "
                                    class="mt-3"
                                    :labels="analytics.acquisition.labels"
                                    :values="analytics.acquisition.values"
                                    :colors="[
                                        '#02CD86',
                                        '#4cb6a2',
                                        '#6C4EE9',
                                        '#947bff',
                                        '#b8aaff',
                                        '#686868',
                                    ]"
                                    series-name="Signups"
                                    :percentage-labels="false"
                                    :height="280"
                                />
                                <p
                                    v-else
                                    class="flex h-[280px] items-center justify-center text-sm text-[#686868]"
                                >
                                    No signups in this period yet
                                </p>
                            </article>
                        </div>

                        <div
                            v-show="activeTab === 'engagement'"
                            aria-label="Engagement"
                            class="grid gap-4 xl:grid-cols-2"
                        >
                            <article
                                class="overflow-hidden rounded-[18px] bg-[#1a1a1a] p-5 ring-1 ring-white/10 xl:col-span-2"
                            >
                                <h2 class="text-lg font-medium">
                                    Engagement trend
                                </h2>
                                <p class="mt-1 text-sm text-[#686868]">
                                    Weekly and monthly active customers from
                                    daily snapshots
                                </p>
                                <LineChart
                                    v-if="
                                        analytics.engagement_trend.labels
                                            .length > 0
                                    "
                                    class="mt-4"
                                    :series="engagementSeries"
                                    :categories="
                                        analytics.engagement_trend.labels
                                    "
                                    raw-labels
                                    value-suffix=""
                                    :height="260"
                                />
                                <p
                                    v-else
                                    class="flex h-[260px] items-center justify-center px-6 text-center text-sm text-[#686868]"
                                >
                                    Trends appear once the daily snapshot job
                                    has captured its first few days of data.
                                </p>
                            </article>

                            <article
                                class="overflow-hidden rounded-[18px] bg-[#1a1a1a] p-5 ring-1 ring-white/10"
                            >
                                <h2 class="text-lg font-medium">
                                    Product adoption
                                </h2>
                                <p class="mt-1 text-sm text-[#686868]">
                                    Customers with at least one record in each
                                    product area
                                </p>
                                <RankedBarChart
                                    class="mt-3"
                                    :labels="analytics.product_adoption.labels"
                                    :values="analytics.product_adoption.values"
                                    :colors="[
                                        '#02CD86',
                                        '#4cb6a2',
                                        '#6C4EE9',
                                        '#947bff',
                                    ]"
                                    series-name="Customers"
                                    :percentage-labels="false"
                                    :height="280"
                                />
                            </article>

                            <article
                                class="overflow-hidden rounded-[18px] bg-[#1a1a1a] p-5 ring-1 ring-white/10"
                            >
                                <h2 class="text-lg font-medium">
                                    Authentication mix
                                </h2>
                                <p class="mt-1 text-sm text-[#686868]">
                                    How customers access their accounts
                                </p>
                                <DonutChart
                                    v-if="authenticationTotal > 0"
                                    class="mt-2"
                                    :series="
                                        analytics.authentication_mix.values
                                    "
                                    :labels="
                                        analytics.authentication_mix.labels
                                    "
                                    :colors="['#02CD86', '#6C4EE9', '#947bff']"
                                    center-label="Customers"
                                    :center-value="
                                        formatAdminNumber(authenticationTotal)
                                    "
                                    :tooltip-formatter="
                                        (value: number) =>
                                            formatAdminNumber(value) +
                                            ' customers'
                                    "
                                />
                                <p
                                    v-else
                                    class="flex h-[280px] items-center justify-center text-sm text-[#686868]"
                                >
                                    No authentication data yet
                                </p>
                            </article>

                            <article
                                class="overflow-hidden rounded-[18px] bg-[#1a1a1a] p-5 ring-1 ring-white/10"
                            >
                                <h2 class="text-lg font-medium">
                                    Retention by segment
                                </h2>
                                <p class="mt-1 text-sm text-[#686868]">
                                    Share of customers older than 30 days who
                                    were active in the last 30 days
                                </p>
                                <RankedBarChart
                                    v-if="
                                        analytics.retention_segments.values
                                            .length > 0
                                    "
                                    class="mt-3"
                                    :labels="
                                        analytics.retention_segments.labels
                                    "
                                    :values="
                                        analytics.retention_segments.values
                                    "
                                    :colors="[
                                        '#02CD86',
                                        '#4cb6a2',
                                        '#6C4EE9',
                                        '#947bff',
                                        '#b8aaff',
                                    ]"
                                    series-name="Retention"
                                    :percentage-labels="false"
                                    percent-values
                                    :height="280"
                                />
                                <p
                                    v-else
                                    class="flex h-[280px] items-center justify-center px-6 text-center text-sm text-[#686868]"
                                >
                                    Retention appears once customers are older
                                    than 30 days.
                                </p>
                            </article>

                            <article
                                class="overflow-hidden rounded-[18px] bg-[#1a1a1a] p-5 ring-1 ring-white/10"
                            >
                                <h2 class="text-lg font-medium">
                                    Customer locales
                                </h2>
                                <p class="mt-1 text-sm text-[#686868]">
                                    Language preferences across registered
                                    customers
                                </p>
                                <DonutChart
                                    v-if="localeTotal > 0"
                                    class="mt-2"
                                    :series="analytics.locales.values"
                                    :labels="analytics.locales.labels"
                                    :colors="['#02CD86', '#6C4EE9', '#947bff']"
                                    center-label="Customers"
                                    :center-value="
                                        formatAdminNumber(localeTotal)
                                    "
                                    :tooltip-formatter="
                                        (value: number) =>
                                            formatAdminNumber(value) +
                                            ' customers'
                                    "
                                />
                                <p
                                    v-else
                                    class="flex h-[280px] items-center justify-center text-sm text-[#686868]"
                                >
                                    No locale data yet
                                </p>
                            </article>
                        </div>

                        <div
                            v-show="activeTab === 'miles'"
                            aria-label="Miles economy"
                            class="grid gap-4 xl:grid-cols-2"
                        >
                            <section
                                class="grid gap-3 sm:grid-cols-2 xl:col-span-2 xl:grid-cols-5"
                                aria-label="Miles totals"
                            >
                                <article
                                    v-for="metric in [
                                        {
                                            label: 'Outstanding',
                                            value: analytics.miles.overview
                                                .outstanding,
                                            icon: WalletCards,
                                        },
                                        {
                                            label: 'Issued',
                                            value: analytics.miles.overview
                                                .issued,
                                            icon: Sparkles,
                                        },
                                        {
                                            label: 'Spent',
                                            value: analytics.miles.overview
                                                .spent,
                                            icon: Gauge,
                                        },
                                        {
                                            label: 'Transferred',
                                            value: analytics.miles.overview
                                                .transferred,
                                            icon: Gift,
                                        },
                                        {
                                            label: 'Wallets',
                                            value: analytics.miles.overview
                                                .wallets,
                                            icon: Users,
                                        },
                                    ]"
                                    :key="metric.label"
                                    class="rounded-[18px] bg-[#1a1a1a] p-4 ring-1 ring-white/10"
                                >
                                    <component
                                        :is="metric.icon"
                                        class="size-5 text-[#02CD86]"
                                    />
                                    <p class="mt-5 text-2xl font-semibold">
                                        {{ formatAdminNumber(metric.value) }}
                                    </p>
                                    <p class="mt-1 text-xs text-[#989898]">
                                        {{ metric.label }}
                                    </p>
                                </article>
                            </section>

                            <article
                                class="overflow-hidden rounded-[18px] bg-[#1a1a1a] p-5 ring-1 ring-white/10 xl:col-span-2"
                            >
                                <h2 class="text-lg font-medium">
                                    Retention by Miles behavior
                                </h2>
                                <p class="mt-1 text-sm text-[#686868]">
                                    Covered-date retention for signup cohorts,
                                    split by customers who claimed and those who
                                    never claimed
                                </p>
                                <LineChart
                                    class="mt-4"
                                    :series="milesRetentionSeries"
                                    :categories="
                                        analytics.miles.retention.labels
                                    "
                                    raw-labels
                                    value-suffix="%"
                                    :height="260"
                                />
                            </article>

                            <article
                                class="overflow-hidden rounded-[18px] bg-[#1a1a1a] p-5 ring-1 ring-white/10"
                            >
                                <h2 class="text-lg font-medium">
                                    Net Miles by reason
                                </h2>
                                <p class="mt-1 text-sm text-[#686868]">
                                    Issued less spent in the {{ rangeLabel }}
                                </p>
                                <RankedBarChart
                                    v-if="
                                        analytics.miles.economy.labels.length >
                                        0
                                    "
                                    class="mt-3"
                                    :labels="analytics.miles.economy.labels"
                                    :values="milesEconomyValues"
                                    :colors="['#02CD86', '#6C4EE9']"
                                    series-name="Net Miles"
                                    :percentage-labels="false"
                                    :height="300"
                                />
                                <p
                                    v-else
                                    class="flex h-[300px] items-center justify-center text-sm text-[#686868]"
                                >
                                    No Miles ledger activity in this period
                                </p>
                            </article>

                            <article
                                class="rounded-[18px] bg-[#1a1a1a] p-5 ring-1 ring-white/10"
                            >
                                <h2 class="text-lg font-medium">
                                    Earning and unlock cadence
                                </h2>
                                <dl class="mt-5 grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <dt class="text-[#686868]">Claims</dt>
                                        <dd class="mt-1 text-xl font-medium">
                                            {{
                                                formatAdminNumber(
                                                    analytics.miles.engagement
                                                        .claims,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-[#686868]">
                                            Completed cycles
                                        </dt>
                                        <dd class="mt-1 text-xl font-medium">
                                            {{
                                                formatAdminNumber(
                                                    analytics.miles.engagement
                                                        .completed_cycles,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-[#686868]">
                                            Claims per claimer
                                        </dt>
                                        <dd class="mt-1 text-xl font-medium">
                                            {{
                                                analytics.miles.engagement
                                                    .average_claims_per_claimer
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-[#686868]">
                                            Activity days per logger
                                        </dt>
                                        <dd class="mt-1 text-xl font-medium">
                                            {{
                                                analytics.miles.engagement
                                                    .average_activity_days_per_active_user
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-[#686868]">
                                            Median to first unlock
                                        </dt>
                                        <dd class="mt-1 text-xl font-medium">
                                            {{
                                                formatHours(
                                                    analytics.miles.unlocks
                                                        .median_hours_to_first,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-[#686868]">
                                            Median to seventh unlock
                                        </dt>
                                        <dd class="mt-1 text-xl font-medium">
                                            {{
                                                formatHours(
                                                    analytics.miles.unlocks
                                                        .median_hours_to_seventh,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                            </article>

                            <article
                                class="rounded-[18px] bg-[#1a1a1a] p-5 ring-1 ring-white/10"
                            >
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h2 class="text-lg font-medium">
                                            Protection and sinks
                                        </h2>
                                        <p class="mt-1 text-sm text-[#686868]">
                                            Weekly-scale Miles uses
                                        </p>
                                    </div>
                                    <ShieldCheck
                                        class="size-5 text-[#02CD86]"
                                    />
                                </div>
                                <dl class="mt-5 grid grid-cols-2 gap-4 text-sm">
                                    <div
                                        v-for="metric in [
                                            [
                                                'Freeze purchases',
                                                analytics.miles.protections
                                                    .freeze_purchases,
                                            ],
                                            [
                                                'Freezes consumed',
                                                analytics.miles.protections
                                                    .freezes_consumed,
                                            ],
                                            [
                                                'Repair purchases',
                                                analytics.miles.protections
                                                    .repair_purchases,
                                            ],
                                            [
                                                'Repairs applied',
                                                analytics.miles.protections
                                                    .repairs_applied,
                                            ],
                                            [
                                                'Weekly graces',
                                                analytics.miles.protections
                                                    .weekly_graces,
                                            ],
                                            [
                                                'Cosmetics',
                                                analytics.miles.protections
                                                    .cosmetics_purchased,
                                            ],
                                        ]"
                                        :key="String(metric[0])"
                                    >
                                        <dt class="text-[#686868]">
                                            {{ metric[0] }}
                                        </dt>
                                        <dd class="mt-1 text-xl font-medium">
                                            {{
                                                formatAdminNumber(
                                                    Number(metric[1]),
                                                )
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                            </article>

                            <article
                                class="rounded-[18px] bg-[#1a1a1a] p-5 ring-1 ring-white/10"
                            >
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h2 class="text-lg font-medium">
                                            Referral funnel
                                        </h2>
                                        <p class="mt-1 text-sm text-[#686868]">
                                            Attributed referrals and earned
                                            stages
                                        </p>
                                    </div>
                                    <Gift class="size-5 text-[#02CD86]" />
                                </div>
                                <ol class="mt-5 space-y-3">
                                    <li
                                        v-for="stage in [
                                            [
                                                'Signups',
                                                analytics.miles.referrals
                                                    .signups,
                                            ],
                                            [
                                                'Activated',
                                                analytics.miles.referrals
                                                    .activated,
                                            ],
                                            [
                                                'Retained',
                                                analytics.miles.referrals
                                                    .retained,
                                            ],
                                            [
                                                'Habit formed',
                                                analytics.miles.referrals.habit,
                                            ],
                                        ]"
                                        :key="String(stage[0])"
                                        class="flex items-center justify-between border-b border-white/[0.06] pb-3 text-sm last:border-0"
                                    >
                                        <span class="text-[#989898]">{{
                                            stage[0]
                                        }}</span>
                                        <span class="font-medium">{{
                                            formatAdminNumber(Number(stage[1]))
                                        }}</span>
                                    </li>
                                </ol>
                                <p class="mt-3 text-xs text-[#686868]">
                                    {{ analytics.miles.referrals.review_holds }}
                                    review holds ·
                                    {{ analytics.miles.referrals.rejections }}
                                    rejected ·
                                    {{ analytics.miles.referrals.gifts }} gifts
                                </p>
                            </article>

                            <article
                                class="rounded-[18px] bg-[#1a1a1a] p-5 ring-1 ring-white/10 xl:col-span-2"
                            >
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h2 class="text-lg font-medium">
                                            Advisor economics
                                        </h2>
                                        <p class="mt-1 text-sm text-[#686868]">
                                            Provider performance and Miles
                                            reconciliation
                                        </p>
                                    </div>
                                    <Bot class="size-5 text-[#02CD86]" />
                                </div>
                                <dl
                                    class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6"
                                >
                                    <div
                                        v-for="metric in [
                                            [
                                                'Operations',
                                                analytics.miles.advisor
                                                    .operations,
                                            ],
                                            [
                                                'Successful runs',
                                                analytics.miles.advisor
                                                    .successful_recommendations,
                                            ],
                                            [
                                                'Terminal failures',
                                                analytics.miles.advisor
                                                    .terminal_failures,
                                            ],
                                            [
                                                'Failure rate',
                                                formatAdminPercentage(
                                                    analytics.miles.advisor
                                                        .terminal_failure_rate,
                                                ),
                                            ],
                                            [
                                                'Shadow Miles',
                                                analytics.miles.advisor
                                                    .shadow_miles,
                                            ],
                                            [
                                                'Charged Miles',
                                                analytics.miles.advisor
                                                    .charged_miles,
                                            ],
                                            [
                                                'Refunded Miles',
                                                analytics.miles.advisor
                                                    .refunded_miles,
                                            ],
                                            [
                                                'Cost p50',
                                                formatUsd(
                                                    analytics.miles.advisor
                                                        .provider_cost_p50_usd,
                                                ),
                                            ],
                                            [
                                                'Cost p95',
                                                formatUsd(
                                                    analytics.miles.advisor
                                                        .provider_cost_p95_usd,
                                                ),
                                            ],
                                            [
                                                'Latency p50',
                                                analytics.miles.advisor
                                                    .latency_p50_ms === null
                                                    ? '—'
                                                    : `${analytics.miles.advisor.latency_p50_ms}ms`,
                                            ],
                                            [
                                                'Latency p95',
                                                analytics.miles.advisor
                                                    .latency_p95_ms === null
                                                    ? '—'
                                                    : `${analytics.miles.advisor.latency_p95_ms}ms`,
                                            ],
                                            [
                                                'Mismatches',
                                                analytics.miles.advisor
                                                    .reconciliation_mismatches,
                                            ],
                                        ]"
                                        :key="String(metric[0])"
                                    >
                                        <dt class="text-xs text-[#686868]">
                                            {{ metric[0] }}
                                        </dt>
                                        <dd class="mt-1 text-lg font-medium">
                                            {{ metric[1] }}
                                        </dd>
                                    </div>
                                </dl>
                            </article>
                        </div>
                    </section>
                </Deferred>

                <section
                    v-show="activeTab === 'customers'"
                    class="mb-4 overflow-hidden rounded-[18px] bg-[#1a1a1a] ring-1 ring-white/10"
                >
                    <div class="border-b border-white/10 p-5">
                        <div
                            class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between"
                        >
                            <div>
                                <h2 class="text-lg font-medium">
                                    Customer directory
                                </h2>
                                <p class="mt-1 text-sm text-[#686868]">
                                    {{ formatAdminNumber(users.total) }}
                                    matching customers. Read-only account and
                                    adoption data.
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <div
                                    class="relative min-w-[220px] flex-1 sm:flex-none"
                                >
                                    <Search
                                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-[#686868]"
                                    />
                                    <input
                                        v-model="search"
                                        type="search"
                                        placeholder="Search name or email"
                                        class="h-10 w-full rounded-xl border border-white/10 bg-[#252525] pr-3 pl-10 text-sm text-white outline-none placeholder:text-[#686868] focus:border-[#02CD86]/70 focus:ring-2 focus:ring-[#02CD86]/15 sm:w-[250px]"
                                    />
                                </div>

                                <select
                                    v-model="activity"
                                    aria-label="Activity filter"
                                    class="h-10 rounded-xl border border-white/10 bg-[#252525] px-3 text-sm text-white outline-none focus:border-[#02CD86]/70"
                                    @change="applyFilters()"
                                >
                                    <option value="all">All activity</option>
                                    <option value="online">Online now</option>
                                    <option value="7d">Active 7 days</option>
                                    <option value="30d">Active 30 days</option>
                                    <option value="inactive">
                                        Inactive 30+ days
                                    </option>
                                    <option value="never">Never seen</option>
                                </select>

                                <select
                                    v-model="telegram"
                                    aria-label="Telegram filter"
                                    class="h-10 rounded-xl border border-white/10 bg-[#252525] px-3 text-sm text-white outline-none focus:border-[#02CD86]/70"
                                    @change="applyFilters()"
                                >
                                    <option value="all">All Telegram</option>
                                    <option value="connected">
                                        Telegram connected
                                    </option>
                                    <option value="disconnected">
                                        Telegram disconnected
                                    </option>
                                </select>

                                <select
                                    v-model="verification"
                                    aria-label="Verification filter"
                                    class="h-10 rounded-xl border border-white/10 bg-[#252525] px-3 text-sm text-white outline-none focus:border-[#02CD86]/70"
                                    @change="applyFilters()"
                                >
                                    <option value="all">
                                        All verification
                                    </option>
                                    <option value="verified">Verified</option>
                                    <option value="unverified">
                                        Unverified
                                    </option>
                                </select>

                                <select
                                    v-model="sort"
                                    aria-label="Sort customers"
                                    class="h-10 rounded-xl border border-white/10 bg-[#252525] px-3 text-sm text-white outline-none focus:border-[#02CD86]/70"
                                    @change="applyFilters()"
                                >
                                    <option value="newest">Newest first</option>
                                    <option value="oldest">Oldest first</option>
                                    <option value="last_active">
                                        Recently active
                                    </option>
                                    <option value="transactions">
                                        Most transactions
                                    </option>
                                    <option value="investments">
                                        Most investments
                                    </option>
                                    <option value="bills">Most bills</option>
                                </select>

                                <button
                                    v-if="hasFilters"
                                    type="button"
                                    class="flex h-10 items-center gap-2 rounded-xl border border-white/10 px-3 text-sm text-[#989898] transition hover:border-white/20 hover:text-white active:translate-y-px"
                                    @click="clearFilters"
                                >
                                    <X class="size-4" /> Clear
                                </button>

                                <a
                                    :href="exportUrl"
                                    class="flex h-10 items-center gap-2 rounded-xl border border-white/10 px-3 text-sm text-[#989898] transition hover:border-white/20 hover:text-white active:translate-y-px"
                                    download
                                >
                                    <Download class="size-4" /> Export CSV
                                </a>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="users.data.length > 0"
                        class="relative overflow-x-auto"
                        :class="isFiltering ? 'opacity-60' : ''"
                    >
                        <table class="w-full min-w-[1160px] text-left text-sm">
                            <thead
                                class="bg-[#151515] text-[11px] font-medium tracking-[0.12em] text-[#686868] uppercase"
                            >
                                <tr>
                                    <th class="px-5 py-3">Customer</th>
                                    <th class="px-4 py-3">
                                        <button
                                            type="button"
                                            class="tracking-[0.12em] uppercase transition hover:text-white"
                                            :class="
                                                sort === 'last_active'
                                                    ? 'text-[#7ee8c4]'
                                                    : ''
                                            "
                                            @click="sortBy('last_active')"
                                        >
                                            Activity
                                        </button>
                                    </th>
                                    <th class="px-4 py-3">Account</th>
                                    <th class="px-4 py-3">Telegram</th>
                                    <th class="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            class="tracking-[0.12em] uppercase transition hover:text-white"
                                            :class="
                                                sort === 'transactions'
                                                    ? 'text-[#7ee8c4]'
                                                    : ''
                                            "
                                            @click="sortBy('transactions')"
                                        >
                                            Transactions
                                        </button>
                                    </th>
                                    <th class="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            class="tracking-[0.12em] uppercase transition hover:text-white"
                                            :class="
                                                sort === 'investments'
                                                    ? 'text-[#7ee8c4]'
                                                    : ''
                                            "
                                            @click="sortBy('investments')"
                                        >
                                            Investments
                                        </button>
                                    </th>
                                    <th class="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            class="tracking-[0.12em] uppercase transition hover:text-white"
                                            :class="
                                                sort === 'bills'
                                                    ? 'text-[#7ee8c4]'
                                                    : ''
                                            "
                                            @click="sortBy('bills')"
                                        >
                                            Bills
                                        </button>
                                    </th>
                                    <th class="px-5 py-3 text-right">
                                        <button
                                            type="button"
                                            class="tracking-[0.12em] uppercase transition hover:text-white"
                                            :class="
                                                sort === 'oldest'
                                                    ? 'text-[#7ee8c4]'
                                                    : ''
                                            "
                                            @click="sortBy('oldest')"
                                        >
                                            Joined
                                        </button>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/[0.07]">
                                <tr
                                    v-for="user in users.data"
                                    :key="user.id"
                                    tabindex="0"
                                    class="cursor-pointer transition-colors hover:bg-white/[0.025] focus:bg-white/[0.04] focus:outline-none"
                                    @click="selectedUser = user"
                                    @keydown.enter="selectedUser = user"
                                >
                                    <td class="px-5 py-4">
                                        <div class="font-medium text-white">
                                            {{ user.name }}
                                        </div>
                                        <div
                                            class="mt-1 text-xs text-[#686868]"
                                        >
                                            {{ user.email }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span
                                            class="inline-flex rounded-lg px-2 py-1 text-xs"
                                            :class="activityTone(user)"
                                            >{{
                                                relativeActivity(
                                                    user.last_active_at,
                                                )
                                            }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap gap-1.5">
                                            <span
                                                class="rounded-lg px-2 py-1 text-xs"
                                                :class="
                                                    user.is_verified
                                                        ? 'bg-[#0d2e22] text-[#7ee8c4]'
                                                        : 'bg-[#2f1717] text-[#ffb4b4]'
                                                "
                                                >{{
                                                    user.is_verified
                                                        ? 'Verified'
                                                        : 'Unverified'
                                                }}</span
                                            >
                                            <span
                                                class="rounded-lg bg-white/5 px-2 py-1 text-xs text-[#989898]"
                                                >{{
                                                    user.profile_complete
                                                        ? 'Complete'
                                                        : 'Incomplete'
                                                }}</span
                                            >
                                            <span
                                                class="rounded-lg bg-white/5 px-2 py-1 text-xs text-[#989898]"
                                                >{{ user.auth_method }}</span
                                            >
                                            <!-- Grouped with the other status
                                                 chips rather than given columns
                                                 of their own: the table is
                                                 already wide, and three yes/no
                                                 columns scan worse than this. -->
                                            <span
                                                v-if="user.pro"
                                                class="rounded-lg bg-[#12251c] px-2 py-1 text-xs text-[#02CD86]"
                                                >Pro</span
                                            >
                                            <span
                                                v-if="user.mcp_connected"
                                                class="rounded-lg bg-[#201d31] px-2 py-1 text-xs text-[#b8aaff]"
                                                >AI</span
                                            >
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span
                                            class="rounded-lg px-2 py-1 text-xs"
                                            :class="
                                                user.telegram_connected
                                                    ? 'bg-[#201d31] text-[#b8aaff]'
                                                    : 'bg-white/5 text-[#686868]'
                                            "
                                            >{{
                                                user.telegram_connected
                                                    ? 'Connected'
                                                    : 'Not connected'
                                            }}</span
                                        >
                                    </td>
                                    <td
                                        class="px-4 py-4 text-right font-medium tabular-nums"
                                    >
                                        {{
                                            formatAdminNumber(
                                                user.transaction_count,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-4 text-right font-medium tabular-nums"
                                    >
                                        {{
                                            formatAdminNumber(
                                                user.investment_count,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-4 text-right font-medium tabular-nums"
                                    >
                                        {{ formatAdminNumber(user.bill_count) }}
                                    </td>
                                    <td
                                        class="px-5 py-4 text-right text-xs text-[#989898]"
                                    >
                                        {{ formatAdminDate(user.joined_at) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-else
                        class="flex min-h-72 flex-col items-center justify-center px-6 text-center"
                    >
                        <span
                            class="flex size-12 items-center justify-center rounded-2xl bg-white/5"
                        >
                            <SlidersHorizontal class="size-5 text-[#686868]" />
                        </span>
                        <h3 class="mt-4 font-medium">No customers found</h3>
                        <p class="mt-1 max-w-sm text-sm text-[#686868]">
                            Adjust the directory filters or search term to see
                            more customers.
                        </p>
                        <button
                            v-if="hasFilters"
                            type="button"
                            class="mt-4 rounded-xl bg-[#02CD86] px-4 py-2 text-sm font-medium text-[#101010] transition hover:bg-[#19dda0] active:translate-y-px"
                            @click="clearFilters"
                        >
                            Clear filters
                        </button>
                    </div>

                    <footer
                        v-if="users.total > 0"
                        class="flex flex-col gap-3 border-t border-white/10 px-5 py-4 text-sm text-[#686868] sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p>
                            Showing {{ users.from }}-{{ users.to }} of
                            {{ formatAdminNumber(users.total) }}
                        </p>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="flex size-9 items-center justify-center rounded-xl border border-white/10 text-[#989898] transition hover:border-white/20 hover:text-white disabled:cursor-not-allowed disabled:opacity-30"
                                :disabled="
                                    users.current_page <= 1 || isFiltering
                                "
                                aria-label="Previous page"
                                @click="applyFilters(users.current_page - 1)"
                            >
                                <ChevronLeft class="size-4" />
                            </button>
                            <span class="min-w-20 text-center text-xs"
                                >Page {{ users.current_page }} of
                                {{ users.last_page }}</span
                            >
                            <button
                                type="button"
                                class="flex size-9 items-center justify-center rounded-xl border border-white/10 text-[#989898] transition hover:border-white/20 hover:text-white disabled:cursor-not-allowed disabled:opacity-30"
                                :disabled="
                                    users.current_page >= users.last_page ||
                                    isFiltering
                                "
                                aria-label="Next page"
                                @click="applyFilters(users.current_page + 1)"
                            >
                                <ChevronRight class="size-4" />
                            </button>
                        </div>
                    </footer>
                </section>
            </div>
        </main>

        <AdminCustomerDrawer
            :user="selectedUser"
            @close="selectedUser = null"
        />
    </div>
</template>
