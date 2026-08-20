<template>
    <Head :title="t('finance.dashboard.title')" />

    <div
        class="finance-dense flex min-h-[calc(100vh-92px)] shrink-0 flex-col overflow-x-hidden bg-[#101010] text-white"
    >
        <!-- ── Hero: safe to spend + decision/period ─────────────────── -->
        <div class="grid gap-[18px] px-[18px] pt-[18px] lg:grid-cols-2">
            <div
                class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-6 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <p
                    class="text-xs font-semibold tracking-[0.2em] text-[#02CD86] uppercase"
                >
                    {{ t('finance.dashboard.safe_to_spend') }}
                </p>
                <p class="mt-3 flex flex-wrap items-baseline gap-2">
                    <template v-if="safeToSpendPerDay !== null">
                        <span
                            class="text-[36px] leading-none font-bold text-white sm:text-[44px]"
                            :class="maskClass"
                            >{{ formatAmount(safeToSpendPerDay) }}</span
                        >
                        <span class="text-sm font-medium text-[#989898]">
                            {{ selectedCurrencyLabel }} ·
                            {{ t('finance.dashboard.per_day') }}
                        </span>
                    </template>
                    <span
                        v-else
                        aria-hidden="true"
                        class="inline-block h-[0.7em] w-48 animate-pulse rounded bg-white/10 align-middle"
                    />
                </p>
                <p class="mt-3 max-w-md text-sm leading-6 text-[#989898]">
                    {{
                        safeToSpendPerDay !== null ? safeToSpendExplanation : ''
                    }}
                </p>
            </div>

            <!-- Needs a decision when overspent this period; the current-period
                 progress card otherwise — never an empty slot. -->
            <div
                v-if="needsDecision"
                class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-6 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-[#E94E50]/28"
            >
                <div class="flex items-center gap-2.5">
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#2e0d0d]"
                    >
                        <AlertTriangle class="size-[18px] text-[#E94E50]" />
                    </span>
                    <p
                        class="text-xs font-semibold tracking-[0.2em] text-[#E94E50] uppercase"
                    >
                        {{ t('finance.dashboard.needs_decision') }}
                    </p>
                </div>
                <p class="mt-4 text-sm leading-6 text-[#989898]">
                    {{ needsDecisionBody }}
                </p>
                <Link
                    :href="transactionsIndex()"
                    class="mt-5 inline-flex items-center gap-2 rounded-full bg-[#E94E50] px-5 py-2.5 text-sm font-semibold text-white transition hover:brightness-110"
                >
                    {{ t('finance.dashboard.review_transactions') }}
                </Link>
            </div>
            <div
                v-else
                class="kpi-card-period overflow-hidden rounded-[22px] bg-[#1a1a1a] p-6 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <p
                    class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                >
                    {{ t('finance.calendar.current_period') }}
                </p>
                <p class="mt-2 text-[28px] leading-none font-bold text-white">
                    {{ period.month }}
                </p>
                <p class="mt-1 text-xs text-[#989898]">
                    {{ period.year }} &middot;
                    {{
                        t('finance.calendar.day_of_month', {
                            day: period.dayOfMonth,
                            days: period.daysInMonth,
                        })
                    }}
                </p>
                <div
                    class="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-white/10"
                >
                    <div
                        class="h-full rounded-full bg-[#6C4EE9] transition-all duration-700"
                        :style="{ width: period.progress + '%' }"
                    />
                </div>
                <p class="mt-1.5 text-right text-xs text-[#989898]">
                    {{
                        t('finance.calendar.elapsed', {
                            progress: period.progress,
                        })
                    }}
                </p>
            </div>
        </div>

        <!-- ── Stat row: income / cost / balance / net worth ─────────── -->
        <div
            class="grid gap-[18px] px-[18px] pt-[18px]"
            :class="
                features?.portfolio?.enabled
                    ? 'sm:grid-cols-2 xl:grid-cols-4'
                    : 'sm:grid-cols-3'
            "
        >
            <!-- Income KPI -->
            <article
                class="kpi-card-income overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div class="flex items-center gap-2.5">
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#0d2e22]"
                    >
                        <TrendingUp class="size-[18px] text-[#02CD86]" />
                    </span>
                    <p
                        class="text-xs font-medium tracking-[0.15em] text-[#989898] uppercase"
                    >
                        {{ t('finance.metrics.income') }}
                    </p>
                </div>
                <p class="mt-3 text-[20px] leading-none font-bold text-white">
                    <span v-if="summaryIncome !== null" :class="maskClass">{{
                        formatAmount(summaryIncome)
                    }}</span>
                    <span
                        v-else
                        aria-hidden="true"
                        class="inline-block h-[0.8em] w-24 animate-pulse rounded bg-white/10 align-middle"
                    />
                    <span class="text-xs font-normal text-[#989898]">{{
                        selectedCurrencyLabel
                    }}</span>
                </p>
                <p class="mt-1.5 text-xs text-[#989898]">
                    {{ props.transactions.incomes.length }}
                    {{ t('finance.reports.transactions') }}
                </p>
            </article>
            <!-- Cost KPI -->
            <article
                class="kpi-card-cost overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div class="flex items-center gap-2.5">
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#24212f]"
                    >
                        <TrendingDown class="size-[18px] text-[#6C4EE9]" />
                    </span>
                    <p
                        class="text-xs font-medium tracking-[0.15em] text-[#989898] uppercase"
                    >
                        {{ t('finance.metrics.costs') }}
                    </p>
                </div>
                <p class="mt-3 text-[20px] leading-none font-bold text-white">
                    <span v-if="summaryCost !== null" :class="maskClass">{{
                        formatAmount(summaryCost)
                    }}</span>
                    <span
                        v-else
                        aria-hidden="true"
                        class="inline-block h-[0.8em] w-24 animate-pulse rounded bg-white/10 align-middle"
                    />
                    <span class="text-xs font-normal text-[#989898]">{{
                        selectedCurrencyLabel
                    }}</span>
                </p>
                <p class="mt-1.5 text-xs text-[#989898]">
                    {{ props.transactions.costs.length }}
                    {{ t('finance.reports.transactions') }}
                </p>
            </article>
            <!-- Balance KPI -->
            <article
                class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                :class="balance >= 0 ? 'kpi-card-income' : 'kpi-card-cost'"
            >
                <div class="flex items-center gap-2.5">
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                        :class="balance >= 0 ? 'bg-[#0d2e22]' : 'bg-[#2e0d0d]'"
                    >
                        <Wallet
                            class="size-[18px]"
                            :class="
                                balance >= 0
                                    ? 'text-[#02CD86]'
                                    : 'text-[#E94E50]'
                            "
                        />
                    </span>
                    <p
                        class="text-xs font-medium tracking-[0.15em] text-[#989898] uppercase"
                    >
                        {{ t('finance.metrics.balance') }}
                    </p>
                </div>
                <p
                    class="mt-3 text-[20px] leading-none font-bold"
                    :class="balance >= 0 ? 'text-[#02CD86]' : 'text-[#E94E50]'"
                >
                    <span v-if="totalsReady" :class="maskClass">
                        {{ balance >= 0 ? '+' : '−'
                        }}{{ formatAmount(Math.abs(balance)) }}
                    </span>
                    <span
                        v-else
                        aria-hidden="true"
                        class="inline-block h-[0.8em] w-24 animate-pulse rounded bg-white/10 align-middle"
                    />
                    <span class="text-xs font-normal text-[#989898]">{{
                        selectedCurrencyLabel
                    }}</span>
                </p>
                <p class="mt-1.5 text-xs text-[#989898]">
                    {{
                        balance >= 0
                            ? t('finance.metrics.in_the_positive')
                            : t('finance.metrics.overspent')
                    }}
                </p>
            </article>
            <!-- Net worth KPI -->
            <article
                v-if="features?.portfolio?.enabled"
                class="kpi-card-income overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div class="flex items-center gap-2.5">
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#0d2e22]"
                    >
                        <ChartPie class="size-[18px] text-[#02CD86]" />
                    </span>
                    <p
                        class="text-xs font-medium tracking-[0.15em] text-[#989898] uppercase"
                    >
                        {{ t('finance.dashboard.net_worth') }}
                    </p>
                </div>
                <Deferred data="portfolio">
                    <template #fallback>
                        <p class="mt-3">
                            <span
                                aria-hidden="true"
                                class="inline-block h-[0.8em] w-24 animate-pulse rounded bg-white/10 align-middle"
                            />
                        </p>
                    </template>
                    <p
                        class="mt-3 text-[20px] leading-none font-bold text-white"
                    >
                        <template v-if="portfolioSnapshot">
                            <span :class="maskClass">{{
                                formatAmount(
                                    portfolioSnapshot.net_worth_formatted,
                                )
                            }}</span>
                            <span class="text-xs font-normal text-[#989898]">{{
                                selectedCurrencyLabel
                            }}</span>
                        </template>
                        <span
                            v-else-if="portfolioDecrypting"
                            aria-hidden="true"
                            class="inline-block h-[0.8em] w-24 animate-pulse rounded bg-white/10 align-middle"
                        />
                        <span
                            v-else
                            class="text-xs font-normal text-[#989898]"
                            >{{ t('finance.dashboard.portfolio_empty') }}</span
                        >
                    </p>
                </Deferred>
                <p class="mt-1.5 text-xs text-[#989898]">
                    <Link
                        :href="
                            portfolioRoute.url({
                                query: { currency: props.selectedCurrency },
                            })
                        "
                        class="text-[#02CD86] hover:underline"
                    >
                        {{ t('finance.portfolio.title') }}
                    </Link>
                </p>
            </article>
        </div>

        <!-- ── Needs you this week / Where it went ────────────────────── -->
        <div
            class="grid items-stretch gap-[18px] px-[18px] pt-[18px] xl:grid-cols-2"
        >
            <div
                v-if="features?.bills?.enabled"
                class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#24212f]"
                        >
                            <CalendarClock class="size-[18px] text-[#6C4EE9]" />
                        </span>
                        <p
                            class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                        >
                            {{ t('finance.dashboard.needs_you_this_week') }}
                        </p>
                    </div>
                    <Link
                        :href="
                            billsIndex.url({
                                query: { currency: props.selectedCurrency },
                            })
                        "
                        class="text-xs text-[#6C4EE9] hover:underline"
                    >
                        {{ t('finance.bills.title') }}
                    </Link>
                </div>

                <ul
                    v-if="(props.upcomingBills ?? []).length > 0"
                    class="mt-4 flex flex-col gap-1"
                >
                    <li
                        v-for="bill in props.upcomingBills ?? []"
                        :key="bill.occurrence_id"
                        class="flex items-center gap-3 rounded-xl px-2 py-2"
                    >
                        <span
                            class="size-1.5 shrink-0 rounded-full"
                            :style="{ backgroundColor: dueDotColor(bill) }"
                        />
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-semibold text-white"
                            >
                                <Ciphered :value="bill.title" table="bills" />
                            </p>
                            <p class="mt-0.5 truncate text-xs text-[#989898]">
                                {{ dueLabel(bill) }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 text-sm font-medium text-white"
                            :class="maskClass"
                        >
                            <CipheredMoney
                                :amount="bill.amount"
                                :display-amount="bill.display_amount"
                                :currency="bill.currency"
                                :display-currency="bill.display_currency"
                                :rates="props.rates"
                                table="bills"
                            />
                        </span>
                    </li>
                </ul>

                <p v-else class="mt-4 text-sm text-[#989898]">
                    {{ t('finance.bills.no_upcoming') }}
                </p>
            </div>

            <!-- Where it went -->
            <div
                class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                :class="features?.bills?.enabled ? '' : 'xl:col-span-2'"
            >
                <h2 class="text-[17px] font-normal text-white">
                    {{ t('finance.dashboard.where_it_went') }}
                </h2>
                <template v-if="categoryLegend.length > 0">
                    <div
                        class="mt-4 flex h-2 w-full gap-0.5 overflow-hidden rounded-full bg-white/10"
                    >
                        <div
                            v-for="entry in categoryLegend"
                            :key="entry.label"
                            class="h-full rounded-full transition-all duration-700"
                            :style="{
                                width: entry.pct + '%',
                                backgroundColor: entry.color,
                            }"
                        />
                    </div>
                    <ul class="mt-4 flex flex-col gap-2.5">
                        <li
                            v-for="entry in categoryLegend"
                            :key="entry.label"
                            class="flex items-center gap-2.5"
                        >
                            <span
                                class="size-2.5 shrink-0 rounded-full"
                                :style="{ backgroundColor: entry.color }"
                            />
                            <span
                                class="min-w-0 flex-1 truncate text-sm text-white"
                                >{{ entry.label }}</span
                            >
                            <span
                                class="text-sm font-semibold text-white"
                                :class="maskClass"
                                >{{ formatAmount(entry.value) }}</span
                            >
                            <span class="w-10 text-end text-xs text-[#989898]"
                                >{{ entry.pct }}%</span
                            >
                        </li>
                    </ul>
                </template>
                <div
                    v-else-if="!totalsReady"
                    aria-hidden="true"
                    class="mt-8 h-[180px] animate-pulse rounded-xl bg-white/5"
                />
                <p v-else class="mt-8 text-center text-sm text-[#989898]">
                    {{ t('finance.dashboard.no_costs') }}
                </p>
            </div>
        </div>

        <!-- ── Cash flow this month ────────────────────────────────────── -->
        <div class="px-[18px] pt-[18px] pb-[38px]">
            <div
                class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-[14.5px] font-medium text-white">
                            {{ t('finance.dashboard.cash_flow') }}
                        </h2>
                        <p class="mt-0.5 text-xs text-[#989898]">
                            {{ t('finance.dashboard.cash_flow_hint') }}
                        </p>
                    </div>
                    <div class="flex shrink-0 gap-4 text-xs text-[#989898]">
                        <span class="flex items-center gap-1.5">
                            <span class="size-2 rounded-[2px] bg-[#02CD86]" />
                            {{ t('finance.metrics.income') }}
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="size-2 rounded-[2px] bg-[#6C4EE9]" />
                            {{ t('finance.metrics.costs') }}
                        </span>
                    </div>
                </div>
                <LineChart
                    v-if="totalsReady"
                    class="mt-4"
                    :series="cashFlowSeries"
                    :categories="cashFlowByDay.categories"
                    raw-labels
                    :height="220"
                />
                <div
                    v-else
                    aria-hidden="true"
                    class="mt-8 h-[180px] animate-pulse rounded-xl bg-white/5"
                />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Deferred, Head, Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarClock,
    ChartPie,
    TrendingDown,
    TrendingUp,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import LineChart from '@/components/charts/LineChart.vue';
import Ciphered from '@/components/Ciphered.vue';
import CipheredMoney from '@/components/CipheredMoney.vue';
import { useAmountMask } from '@/composables/useAmountMask';
import { useDisplayAmounts } from '@/composables/useDisplayAmounts';
import { useVaultPortfolio } from '@/composables/useVaultPortfolio';
import type { VaultPortfolioPayload } from '@/composables/useVaultPortfolio';
import { formatAppDate } from '@/lib/date';
import type { CurrencyCode, Rates } from '@/lib/money';
import { dashboard, portfolio as portfolioRoute } from '@/routes';
import { index as billsIndex } from '@/routes/bills';
import { index as transactionsIndex } from '@/routes/transactions';
import type { Logbook, Streak } from '@/types/gamification';
import type { Encrypted } from '@/types/vault';

type TransactionType = 'cost' | 'income';
type Currency = 'toman' | 'usd' | 'eur';

type Category = {
    id: number;
    name: string;
    slug: string;
    type: TransactionType;
    is_default: boolean;
};

type Transaction = {
    id: number;
    type: TransactionType;
    amount: Encrypted<string>;
    currency: Currency;
    display_amount: string | null;
    display_currency: Currency;
    title: Encrypted<string>;
    description: Encrypted<string> | null;
    occurred_at: string;
    category: Category | null;
    category_id: number;
};

type CurrencyOption = { label: string; value: Currency };
type Period = {
    month: string;
    year: number;
    dayOfMonth: number;
    daysInMonth: number;
    progress: number;
};
type UpcomingBill = {
    occurrence_id: number;
    bill_id: number;
    title: Encrypted<string>;
    amount: Encrypted<string | number>;
    currency: Currency;
    display_amount: string | null;
    display_currency: Currency;
    category_name: string | null;
    due_date: string;
    is_overdue: boolean;
    is_due_today: boolean;
};

type PortfolioTopAsset = {
    key: string;
    label: string;
    color: string | null;
    icon: string | null;
    icon_svg: string | null;
    value_formatted: string;
    share: number;
};

type PortfolioSnapshot = {
    net_worth_formatted: string;
    pnl_percent: number | null;
    pnl_is_positive: boolean | null;
    pnl_formatted: string | null;
    has_cost_basis_data: boolean;
    asset_count: number;
    top_assets: PortfolioTopAsset[];
};

type HeadlinePrice = {
    key: string;
    label: string;
    icon: string | null;
    icon_svg: string | null;
    color: string | null;
    unit: string | null;
    price_formatted: string;
    available: boolean;
};

const props = defineProps<{
    transactions: { costs: Transaction[]; incomes: Transaction[] };
    categories: Record<TransactionType, Category[]>;
    currencies: CurrencyOption[];
    selectedCurrency: Currency;
    rates: Rates | null;
    summary: { cost: string; income: string } | null;
    period: Period;
    monthlyTrend: {
        label: string;
        from: string;
        to: string;
        /** Null under the vault — the browser buckets `trendTransactions` instead. */
        income: number | null;
        cost: number | null;
    }[];
    /** Only sent under the vault: the three months behind the trend chart. */
    trendTransactions?: {
        id: number;
        type: TransactionType;
        amount: Encrypted<string>;
        currency: Currency;
        occurred_at: string;
    }[];
    // Module props — absent entirely when the owning module is switched off.
    streak?: Streak;
    logbook?: Logbook;
    upcomingBills?: UpcomingBill[];
    pricesSyncedAt?: string | null;
    // Deferred props — undefined until the follow-up request lands, and never
    // sent at all when their module is off.
    portfolio?: PortfolioSnapshot | null;
    assetPrices?: HeadlinePrice[];
    /** Sent instead of `portfolio` when the vault is armed — see Portfolio.vue. */
    vaultPortfolio?: VaultPortfolioPayload | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const page = usePage();
const features = computed(() => page.props.features);
const { t } = useI18n();
const { masked } = useAmountMask();
const maskClass = computed(() =>
    masked.value
        ? 'blur-[6px] transition-[filter] duration-150 select-none'
        : 'transition-[filter] duration-150',
);

// Built here from decrypted holdings when the vault is armed, and handed straight
// through from the server otherwise.
const { snapshot: vaultSnapshot, decrypting: portfolioDecrypting } =
    useVaultPortfolio(
        () => props.vaultPortfolio,
        () => props.selectedCurrency as CurrencyCode,
    );

const portfolioSnapshot = computed(
    () => vaultSnapshot.value ?? props.portfolio ?? null,
);
const displayCalendar = computed(
    () => (page.props.calendar as string | undefined) ?? 'gregorian',
);
const period = computed(() => props.period);
const selectedCurrencyLabel = computed(
    () =>
        props.currencies.find(
            (currency) => currency.value === props.selectedCurrency,
        )?.label ?? t(`finance.currencies.${props.selectedCurrency}`),
);

function displayDate(value: string): string {
    return formatAppDate(value, displayCalendar.value);
}

function dueLabel(bill: UpcomingBill): string {
    if (bill.is_overdue) {
        return `${t('finance.bills.overdue')} · ${displayDate(bill.due_date)}`;
    }

    if (bill.is_due_today) {
        return t('finance.bills.due_today');
    }

    return displayDate(bill.due_date);
}

/** The mock's per-row status dot: red once overdue, amber the day it's due,
 *  a neutral tone otherwise — the due-date text itself stays plain. */
function dueDotColor(bill: UpcomingBill): string {
    if (bill.is_overdue) {
        return '#E94E50';
    }

    if (bill.is_due_today) {
        return '#F59E0B';
    }

    return '#686868';
}

function parseNum(value: string | number): number {
    return parseFloat(String(value).replace(/,/g, '')) || 0;
}

/**
 * Every amount on this page, converted to the selected currency.
 *
 * Passed through from the server when it could read them, decrypted and
 * converted here when it could not. Both the KPI cards and the cash-flow
 * chart read from this one map, so there is no arrangement where some of
 * them are right.
 */
const monthRows = computed(() => [
    ...props.transactions.costs,
    ...props.transactions.incomes,
]);

const { amounts: displayAmounts, totalOf } = useDisplayAmounts(
    () => monthRows.value,
    () => props.selectedCurrency,
    () => props.rates,
);

function amountOf(transaction: { id: number }): number {
    return displayAmounts.value?.get(transaction.id) ?? 0;
}

const incomeTotal = computed(() => totalOf(props.transactions.incomes));
const costTotal = computed(() => totalOf(props.transactions.costs));

/** Null while amounts are still sealed, so the cards show a skeleton not a zero. */
const summaryIncome = computed(() =>
    props.summary
        ? props.summary.income
        : (incomeTotal.value?.toFixed(2) ?? null),
);
const summaryCost = computed(() =>
    props.summary ? props.summary.cost : (costTotal.value?.toFixed(2) ?? null),
);

const incomeNum = computed(() => parseNum(summaryIncome.value ?? 0));
const costNum = computed(() => parseNum(summaryCost.value ?? 0));
const balance = computed(() => incomeNum.value - costNum.value);

/** True once every figure on the page has a real number behind it. */
const totalsReady = computed(
    () => summaryIncome.value !== null && summaryCost.value !== null,
);

/** Whole days left in the period, today included — never less than 1, so
 *  dividing by it on the last day of the month still means something. */
const daysRemaining = computed(() =>
    Math.max(1, props.period.daysInMonth - props.period.dayOfMonth + 1),
);

/** A positive balance spread over the days left; null while totals are still
 *  sealed, negative balances read as zero rather than a negative daily figure. */
const safeToSpendPerDay = computed(() => {
    if (!totalsReady.value) {
        return null;
    }

    return Math.max(0, balance.value) / daysRemaining.value;
});

const safeToSpendExplanation = computed(() =>
    t('finance.dashboard.safe_to_spend_explanation', {
        cost: `${formatAmount(costNum.value)} ${selectedCurrencyLabel.value}`,
        income: `${formatAmount(incomeNum.value)} ${selectedCurrencyLabel.value}`,
        month: props.period.month,
        days: daysRemaining.value,
        amount: `${formatAmount(safeToSpendPerDay.value ?? 0)} ${selectedCurrencyLabel.value}`,
    }),
);

/** The one real, data-driven trigger for the "needs a decision" card: this
 *  period's spending has already outrun its income. Deliberately not guessing
 *  at anything fancier (a pending purchase, a risky bill) that the app has no
 *  way to actually know about yet. */
const needsDecision = computed(() => totalsReady.value && balance.value < 0);

const needsDecisionBody = computed(() =>
    t('finance.dashboard.needs_decision_body', {
        amount: `${formatAmount(Math.abs(balance.value))} ${selectedCurrencyLabel.value}`,
        month: props.period.month,
    }),
);

const chartPalette = [
    '#02CD86',
    '#6C4EE9',
    '#F59E0B',
    '#3B82F6',
    '#E94E50',
    '#52525b',
];

// This month's costs grouped by category — top 5 plus an "other" bucket.
const categoryBreakdown = computed(() => {
    const totals = new Map<string, number>();

    for (const transaction of props.transactions.costs) {
        const name = categoryName(transaction);
        totals.set(name, (totals.get(name) ?? 0) + amountOf(transaction));
    }

    const sorted = [...totals.entries()].sort(
        (left, right) => right[1] - left[1],
    );
    const top = sorted.slice(0, 5);
    const restTotal = sorted
        .slice(5)
        .reduce((acc, [, value]) => acc + value, 0);

    if (restTotal > 0) {
        top.push([t('finance.categories.cost.other'), restTotal]);
    }

    return {
        labels: top.map(([label]) => label),
        series: top.map(([, value]) => Math.round(value * 100) / 100),
        colors: top.map(
            (_, index) => chartPalette[index % chartPalette.length],
        ),
        total: top.reduce((acc, [, value]) => acc + value, 0),
    };
});

/** The "where it went" legend: the same category breakdown as a share of the
 *  total, so the bar above it and every row agree on the same numbers. */
const categoryLegend = computed(() => {
    const total = categoryBreakdown.value.total;

    return categoryBreakdown.value.labels.map((label, index) => {
        const value = categoryBreakdown.value.series[index] ?? 0;

        return {
            label,
            value,
            color: categoryBreakdown.value.colors[index],
            pct: total > 0 ? Math.round((value / total) * 1000) / 10 : 0,
        };
    });
});

function dayOfMonthIndex(isoDate: string): number {
    return Number(isoDate.slice(8, 10));
}

/** Daily income and cost totals across the whole period, for the one
 *  combined "cash flow this month" chart. */
const cashFlowByDay = computed(() => {
    const income = Array.from({ length: props.period.daysInMonth }, () => 0);
    const cost = Array.from({ length: props.period.daysInMonth }, () => 0);

    for (const transaction of props.transactions.incomes) {
        const day = dayOfMonthIndex(transaction.occurred_at);

        if (day >= 1 && day <= income.length) {
            income[day - 1] += amountOf(transaction);
        }
    }

    for (const transaction of props.transactions.costs) {
        const day = dayOfMonthIndex(transaction.occurred_at);

        if (day >= 1 && day <= cost.length) {
            cost[day - 1] += amountOf(transaction);
        }
    }

    return {
        categories: cost.map((_, index) => String(index + 1)),
        income: income.map((value) => Math.round(value * 100) / 100),
        cost: cost.map((value) => Math.round(value * 100) / 100),
    };
});

const cashFlowSeries = computed(() => [
    {
        name: t('finance.metrics.income'),
        key: 'income',
        color: '#02CD86',
        data: cashFlowByDay.value.income,
    },
    {
        name: t('finance.metrics.costs'),
        key: 'cost',
        color: '#6C4EE9',
        data: cashFlowByDay.value.cost,
    },
]);

function formatAmount(amount: string | number): string {
    const numericAmount = Number(String(amount).replace(/,/g, ''));

    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 2,
        minimumFractionDigits: numericAmount % 1 === 0 ? 0 : 2,
    }).format(numericAmount);
}

function categoryName(transaction: Transaction): string {
    if (transaction.category?.name) {
        return transaction.category.name;
    }

    return (
        (props.categories[transaction.type] ?? []).find(
            (category) => category.id === transaction.category_id,
        )?.name ?? t('finance.categories.uncategorized')
    );
}
</script>
