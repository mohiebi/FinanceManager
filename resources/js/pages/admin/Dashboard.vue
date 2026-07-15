<script setup lang="ts">
import { Deferred, Head, router } from '@inertiajs/vue3';
import {
    Activity,
    Bot,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    CircleUserRound,
    Clock3,
    Search,
    SlidersHorizontal,
    UserCheck,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import DonutChart from '@/components/charts/DonutChart.vue';
import LineChart from '@/components/charts/LineChart.vue';
import RankedBarChart from '@/components/charts/RankedBarChart.vue';
import { dashboard as adminDashboard } from '@/routes/admin';
import type {
    AdminAnalytics,
    AdminFilters,
    AdminSummary,
    AdminUser,
    AdminUserPaginator,
} from '@/types';

const props = defineProps<{
    summary: AdminSummary;
    analytics?: AdminAnalytics;
    users: AdminUserPaginator;
    filters: AdminFilters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Admin', href: adminDashboard() }],
    },
});

const search = ref(props.filters.search);
const activity = ref<AdminFilters['activity']>(props.filters.activity);
const telegram = ref<AdminFilters['telegram']>(props.filters.telegram);
const verification = ref<AdminFilters['verification']>(
    props.filters.verification,
);
const sort = ref<AdminFilters['sort']>(props.filters.sort);
const isFiltering = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const numberFormatter = new Intl.NumberFormat('en-US');
const dateFormatter = new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
});

const growthSeries = computed(() => [
    {
        name: 'New customers',
        key: 'new_customers',
        color: '#02CD86',
        data: props.analytics?.growth.new_customers ?? [],
    },
    {
        name: 'Total customers',
        key: 'cumulative_customers',
        color: '#6C4EE9',
        data: props.analytics?.growth.cumulative_customers ?? [],
    },
]);

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
        verification.value !== 'all' ||
        sort.value !== 'newest',
);

watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search;
        activity.value = filters.activity;
        telegram.value = filters.telegram;
        verification.value = filters.verification;
        sort.value = filters.sort;
    },
    { deep: true },
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

function applyFilters(page = 1): void {
    router.get(
        adminDashboard.url(),
        {
            search: search.value || null,
            activity: activity.value === 'all' ? null : activity.value,
            telegram: telegram.value === 'all' ? null : telegram.value,
            verification:
                verification.value === 'all' ? null : verification.value,
            sort: sort.value === 'newest' ? null : sort.value,
            page: page > 1 ? page : null,
        },
        {
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
        },
    );
}

function clearFilters(): void {
    search.value = '';
    activity.value = 'all';
    telegram.value = 'all';
    verification.value = 'all';
    sort.value = 'newest';
    applyFilters();
}

function formatNumber(value: number): string {
    return numberFormatter.format(value);
}

function formatPercentage(value: number): string {
    return `${value.toFixed(1)}%`;
}

function formatDate(value: string): string {
    return dateFormatter.format(new Date(value));
}

function relativeActivity(value: string | null): string {
    if (!value) {
        return 'Never seen';
    }

    const elapsedMinutes = Math.max(
        0,
        Math.floor((Date.now() - new Date(value).getTime()) / 60_000),
    );

    if (elapsedMinutes < 1) {
        return 'Just now';
    }

    if (elapsedMinutes < 60) {
        return `${elapsedMinutes}m ago`;
    }

    const elapsedHours = Math.floor(elapsedMinutes / 60);

    if (elapsedHours < 24) {
        return `${elapsedHours}h ago`;
    }

    const elapsedDays = Math.floor(elapsedHours / 24);

    return elapsedDays < 30 ? `${elapsedDays}d ago` : formatDate(value);
}

function activityTone(user: AdminUser): string {
    if (!user.last_active_at) {
        return 'bg-white/5 text-[#686868]';
    }

    const elapsed = Date.now() - new Date(user.last_active_at).getTime();

    return elapsed <= 15 * 60_000
        ? 'bg-[#0d2e22] text-[#7ee8c4]'
        : 'bg-white/5 text-[#b3b3b3]';
}
</script>

<template>
    <div class="contents">
        <Head title="Admin analytics" />
        <div
            class="flex h-full min-h-[calc(100vh-92px)] flex-1 flex-col overflow-x-hidden bg-[#111111] px-4 py-6 text-white sm:px-6 lg:px-8 lg:py-8"
        >
            <div class="mx-auto flex w-full max-w-[1600px] flex-col gap-6">
                <header
                    class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
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
                    <div class="flex items-center gap-2 text-xs text-[#686868]">
                        <Clock3 class="size-4 text-[#02CD86]" />
                        Activity updates every five minutes
                    </div>
                </header>

                <section
                    aria-label="Customer summary"
                    class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6"
                >
                    <article
                        class="rounded-[18px] bg-[#1a1a1a] p-5 ring-1 ring-white/10"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="flex size-9 items-center justify-center rounded-xl bg-[#0d2e22]"
                            >
                                <Users class="size-[18px] text-[#02CD86]" />
                            </span>
                            <span class="text-xs text-[#686868]">All time</span>
                        </div>
                        <p class="mt-5 text-3xl font-semibold tabular-nums">
                            {{ formatNumber(summary.total_customers) }}
                        </p>
                        <p class="mt-1 text-sm text-[#989898]">
                            Total customers
                        </p>
                        <p class="mt-3 text-xs text-[#686868]">
                            {{ formatNumber(summary.new_customers_30d) }} joined
                            in 30 days
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
                                }}{{
                                    summary.new_customers_change.toFixed(1)
                                }}%)
                            </span>
                        </p>
                    </article>

                    <article
                        class="rounded-[18px] bg-[#1a1a1a] p-5 ring-1 ring-white/10"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="flex size-9 items-center justify-center rounded-xl bg-[#0d2e22]"
                            >
                                <Activity class="size-[18px] text-[#02CD86]" />
                            </span>
                            <span class="text-xs text-[#686868]">30 days</span>
                        </div>
                        <p class="mt-5 text-3xl font-semibold tabular-nums">
                            {{ formatNumber(summary.active_customers_30d) }}
                        </p>
                        <p class="mt-1 text-sm text-[#989898]">
                            Active customers
                        </p>
                        <p class="mt-3 text-xs text-[#686868]">
                            {{ formatNumber(summary.active_customers_7d) }}
                            active in the last 7 days
                        </p>
                    </article>

                    <article
                        class="rounded-[18px] bg-[#1a1a1a] p-5 ring-1 ring-white/10"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="flex size-9 items-center justify-center rounded-xl bg-[#0d2e22]"
                            >
                                <CircleUserRound
                                    class="size-[18px] text-[#02CD86]"
                                />
                            </span>
                            <span class="text-xs text-[#686868]"
                                >15 minutes</span
                            >
                        </div>
                        <p class="mt-5 text-3xl font-semibold tabular-nums">
                            {{ formatNumber(summary.online_customers) }}
                        </p>
                        <p class="mt-1 text-sm text-[#989898]">Online now</p>
                        <p class="mt-3 text-xs text-[#686868]">
                            Recently active authenticated customers
                        </p>
                    </article>

                    <article
                        class="rounded-[18px] bg-[#1a1a1a] p-5 ring-1 ring-white/10"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="flex size-9 items-center justify-center rounded-xl bg-[#201d31]"
                            >
                                <Bot class="size-[18px] text-[#947bff]" />
                            </span>
                            <span class="text-xs text-[#947bff]">{{
                                formatPercentage(summary.telegram_adoption)
                            }}</span>
                        </div>
                        <p class="mt-5 text-3xl font-semibold tabular-nums">
                            {{ formatNumber(summary.telegram_customers) }}
                        </p>
                        <p class="mt-1 text-sm text-[#989898]">
                            Telegram connected
                        </p>
                        <p class="mt-3 text-xs text-[#686868]">
                            Customers linked to the Telegram bot
                        </p>
                    </article>

                    <article
                        class="rounded-[18px] bg-[#1a1a1a] p-5 ring-1 ring-white/10"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="flex size-9 items-center justify-center rounded-xl bg-[#0d2e22]"
                            >
                                <UserCheck class="size-[18px] text-[#02CD86]" />
                            </span>
                            <span class="text-xs text-[#7ee8c4]">{{
                                formatPercentage(summary.verification_rate)
                            }}</span>
                        </div>
                        <p class="mt-5 text-3xl font-semibold tabular-nums">
                            {{ formatNumber(summary.verified_customers) }}
                        </p>
                        <p class="mt-1 text-sm text-[#989898]">
                            Verified email
                        </p>
                        <p class="mt-3 text-xs text-[#686868]">
                            Customers with a verified email address
                        </p>
                    </article>

                    <article
                        class="rounded-[18px] bg-[#1a1a1a] p-5 ring-1 ring-white/10"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="flex size-9 items-center justify-center rounded-xl bg-[#0d2e22]"
                            >
                                <CheckCircle2
                                    class="size-[18px] text-[#02CD86]"
                                />
                            </span>
                            <span class="text-xs text-[#7ee8c4]">{{
                                formatPercentage(
                                    summary.profile_completion_rate,
                                )
                            }}</span>
                        </div>
                        <p class="mt-5 text-3xl font-semibold tabular-nums">
                            {{ formatNumber(summary.completed_profiles) }}
                        </p>
                        <p class="mt-1 text-sm text-[#989898]">
                            Profiles complete
                        </p>
                        <p class="mt-3 text-xs text-[#686868]">
                            Customers who completed onboarding
                        </p>
                    </article>
                </section>

                <Deferred data="analytics">
                    <template #fallback>
                        <section
                            aria-label="Loading analytics"
                            class="grid gap-4 xl:grid-cols-2"
                        >
                            <div
                                v-for="index in 4"
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
                        class="grid gap-4 xl:grid-cols-2"
                    >
                        <article
                            class="overflow-hidden rounded-[18px] bg-[#1a1a1a] p-5 ring-1 ring-white/10 xl:col-span-2"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h2 class="text-lg font-medium">
                                        Customer growth
                                    </h2>
                                    <p class="mt-1 text-sm text-[#686868]">
                                        New and cumulative customers across 12
                                        months
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
                                :series="analytics.authentication_mix.values"
                                :labels="analytics.authentication_mix.labels"
                                :colors="['#02CD86', '#6C4EE9', '#947bff']"
                                center-label="Customers"
                                :center-value="
                                    formatNumber(authenticationTotal)
                                "
                                :tooltip-formatter="
                                    (value: number) =>
                                        formatNumber(value) + ' customers'
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
                            class="overflow-hidden rounded-[18px] bg-[#1a1a1a] p-5 ring-1 ring-white/10 xl:col-span-2"
                        >
                            <h2 class="text-lg font-medium">
                                Customer locales
                            </h2>
                            <p class="mt-1 text-sm text-[#686868]">
                                Language preferences across registered customers
                            </p>
                            <div class="mx-auto max-w-xl">
                                <DonutChart
                                    v-if="localeTotal > 0"
                                    class="mt-2"
                                    :series="analytics.locales.values"
                                    :labels="analytics.locales.labels"
                                    :colors="['#02CD86', '#6C4EE9', '#947bff']"
                                    center-label="Customers"
                                    :center-value="formatNumber(localeTotal)"
                                    :tooltip-formatter="
                                        (value: number) =>
                                            formatNumber(value) + ' customers'
                                    "
                                />
                                <p
                                    v-else
                                    class="flex h-[280px] items-center justify-center text-sm text-[#686868]"
                                >
                                    No locale data yet
                                </p>
                            </div>
                        </article>
                    </section>
                </Deferred>

                <section
                    class="overflow-hidden rounded-[18px] bg-[#1a1a1a] ring-1 ring-white/10"
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
                                    {{ formatNumber(users.total) }} matching
                                    customers. Read-only account and adoption
                                    data.
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
                                </select>

                                <button
                                    v-if="hasFilters"
                                    type="button"
                                    class="flex h-10 items-center gap-2 rounded-xl border border-white/10 px-3 text-sm text-[#989898] transition hover:border-white/20 hover:text-white active:translate-y-px"
                                    @click="clearFilters"
                                >
                                    <X class="size-4" /> Clear
                                </button>
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
                                    <th class="px-4 py-3">Activity</th>
                                    <th class="px-4 py-3">Account</th>
                                    <th class="px-4 py-3">Telegram</th>
                                    <th class="px-4 py-3 text-right">
                                        Transactions
                                    </th>
                                    <th class="px-4 py-3 text-right">
                                        Investments
                                    </th>
                                    <th class="px-4 py-3 text-right">Bills</th>
                                    <th class="px-5 py-3 text-right">Joined</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/[0.07]">
                                <tr
                                    v-for="user in users.data"
                                    :key="user.id"
                                    class="transition-colors hover:bg-white/[0.025]"
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
                                            formatNumber(user.transaction_count)
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-4 text-right font-medium tabular-nums"
                                    >
                                        {{
                                            formatNumber(user.investment_count)
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-4 text-right font-medium tabular-nums"
                                    >
                                        {{ formatNumber(user.bill_count) }}
                                    </td>
                                    <td
                                        class="px-5 py-4 text-right text-xs text-[#989898]"
                                    >
                                        {{ formatDate(user.joined_at) }}
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
                            {{ formatNumber(users.total) }}
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
        </div>
    </div>
</template>
