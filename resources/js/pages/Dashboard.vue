<template>
    <Head :title="t('finance.dashboard.title')" />

    <div
        class="finance-dense flex min-h-[calc(100vh-92px)] shrink-0 flex-col overflow-x-hidden bg-[#101010] text-white"
    >
        <!-- ── Hero: safe to spend + decision/period ─────────────────── -->
        <div class="grid gap-[18px] px-[18px] pt-[18px] lg:grid-cols-2">
            <div
                class="overflow-hidden rounded-[16px] bg-[#1a1a1a] p-6 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <p
                    class="text-xs font-semibold tracking-[0.2em] text-[#02CD86] uppercase"
                >
                    {{ t('finance.dashboard.safe_to_spend') }}
                </p>
                <p class="mt-3 flex flex-wrap items-baseline gap-2">
                    <template v-if="safeToSpendPerDay !== null">
                        <CompactMoney
                            class="text-[36px] leading-none font-bold text-white sm:text-[44px]"
                            :value="safeToSpendPerDay"
                            :currency="props.selectedCurrency"
                            mask-variant="placeholder"
                        />
                        <span class="text-sm font-medium text-[#989898]">
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
                class="overflow-hidden rounded-[16px] bg-[#1a1a1a] p-6 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-[#E94E50]/28"
            >
                <div class="flex items-center gap-2">
                    <span class="size-1.5 shrink-0 rounded-full bg-[#E94E50]" />
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
                class="overflow-hidden rounded-[16px] bg-[#1a1a1a] p-6 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
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
                    {{ dn(period.year) }} &middot;
                    {{
                        t('finance.calendar.day_of_month', {
                            day: dn(period.dayOfMonth),
                            days: dn(period.daysInMonth),
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
                            progress: dn(period.progress),
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
                class="overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div class="flex items-center gap-2">
                    <span class="size-1.5 shrink-0 rounded-full bg-[#02CD86]" />
                    <p class="text-xs text-[#989898]">
                        {{ t('finance.metrics.income') }}
                    </p>
                </div>
                <p class="mt-3 text-[20px] leading-none font-bold text-white">
                    <CompactMoney
                        v-if="summaryIncome !== null"
                        :value="summaryIncome"
                        :currency="props.selectedCurrency"
                        mask-variant="placeholder"
                    />
                    <span
                        v-else
                        aria-hidden="true"
                        class="inline-block h-[0.8em] w-24 animate-pulse rounded bg-white/10 align-middle"
                    />
                </p>
                <p class="mt-1.5 text-xs text-[#989898]">
                    {{ dn(props.transactions.incomes.length) }}
                    {{ t('finance.reports.transactions') }}
                </p>
            </article>
            <!-- Cost KPI -->
            <article
                class="overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div class="flex items-center gap-2">
                    <span class="size-1.5 shrink-0 rounded-full bg-[#6C4EE9]" />
                    <p class="text-xs text-[#989898]">
                        {{ t('finance.metrics.costs') }}
                    </p>
                </div>
                <p class="mt-3 text-[20px] leading-none font-bold text-white">
                    <CompactMoney
                        v-if="summaryCost !== null"
                        :value="summaryCost"
                        :currency="props.selectedCurrency"
                        mask-variant="placeholder"
                    />
                    <span
                        v-else
                        aria-hidden="true"
                        class="inline-block h-[0.8em] w-24 animate-pulse rounded bg-white/10 align-middle"
                    />
                </p>
                <p class="mt-1.5 text-xs text-[#989898]">
                    {{ dn(props.transactions.costs.length) }}
                    {{ t('finance.reports.transactions') }}
                </p>
            </article>
            <!-- Balance KPI -->
            <article
                class="overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div class="flex items-center gap-2">
                    <span
                        class="size-1.5 shrink-0 rounded-full"
                        :class="balance >= 0 ? 'bg-[#02CD86]' : 'bg-[#E94E50]'"
                    />
                    <p class="text-xs text-[#989898]">
                        {{ t('finance.metrics.balance') }}
                    </p>
                </div>
                <p
                    class="mt-3 text-[20px] leading-none font-bold"
                    :class="balance >= 0 ? 'text-[#02CD86]' : 'text-[#E94E50]'"
                >
                    <span v-if="totalsReady">
                        <span v-if="balance > 0">+</span
                        ><CompactMoney
                            :value="balance"
                            :currency="props.selectedCurrency"
                            mask-variant="placeholder"
                        />
                    </span>
                    <span
                        v-else
                        aria-hidden="true"
                        class="inline-block h-[0.8em] w-24 animate-pulse rounded bg-white/10 align-middle"
                    />
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
                class="overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div class="flex items-center gap-2">
                    <span class="size-1.5 shrink-0 rounded-full bg-[#02CD86]" />
                    <p class="text-xs text-[#989898]">
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
                            <CompactMoney
                                :value="portfolioSnapshot.net_worth_formatted"
                                :currency="props.selectedCurrency"
                                mask-variant="placeholder"
                            />
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

        <!-- ── Flight log strip ───────────────────────────────────────
             One line, not the two cards this used to be. The run and the
             month's coverage are worth a glance from here; everything behind
             them lives on the flight log's own page, which this links to. -->
        <div v-if="props.streak && props.logbook" class="px-[18px] pt-[18px]">
            <Link
                :href="flightLog()"
                class="group flex flex-wrap items-center gap-x-5 gap-y-3 rounded-[16px] border border-white/7 bg-[#1a1a1a] px-5 py-3.5 transition-colors hover:border-white/12"
            >
                <span class="flex shrink-0 items-center gap-2.5">
                    <span
                        class="grid size-7 place-items-center rounded-[9px] bg-[#02cd86]/12 text-[#02cd86]"
                    >
                        <Plane class="size-3.5" aria-hidden="true" />
                    </span>
                    <span>
                        <span
                            class="block text-[10px] font-medium tracking-[0.16em] text-[#686868] uppercase"
                            >{{ t('gamification.title') }}</span
                        >
                        <span class="block text-sm text-white">{{
                            t('gamification.page.strip_run', {
                                days: dn(props.streak.current_run),
                            })
                        }}</span>
                    </span>
                </span>

                <span class="flex min-w-[140px] flex-1 gap-1" dir="ltr">
                    <span
                        v-for="day in props.streak.days"
                        :key="day.date"
                        class="h-[22px] flex-1 rounded-[4px]"
                        :class="stripDayClass(day.state)"
                        :title="t(`gamification.states.${day.state}`)"
                    />
                </span>

                <span class="shrink-0 text-end">
                    <span class="block text-[13px] text-white">{{
                        t('gamification.page.strip_recorded', {
                            percent: dn(props.logbook.percent),
                            month: props.logbook.month,
                        })
                    }}</span>
                    <span class="block text-xs text-[#686868]">{{
                        props.logbook.uncategorised > 0
                            ? t(
                                  'gamification.page.strip_uncategorised',
                                  { count: dn(props.logbook.uncategorised) },
                                  props.logbook.uncategorised,
                              )
                            : t('gamification.page.strip_all_sorted')
                    }}</span>
                </span>

                <ArrowRight
                    class="size-4 shrink-0 text-[#686868] transition-transform group-hover:translate-x-0.5 rtl:rotate-180 rtl:group-hover:-translate-x-0.5"
                    aria-hidden="true"
                />
            </Link>
        </div>

        <!-- ── Needs you this week / Where it went ────────────────────── -->
        <div
            class="grid items-stretch gap-[18px] px-[18px] pt-[18px] xl:grid-cols-2"
        >
            <div
                v-if="features?.bills?.enabled"
                class="overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div class="flex items-center justify-between gap-3">
                    <p class="text-[14.5px] font-medium text-white">
                        {{ t('finance.dashboard.needs_you_this_week') }}
                    </p>
                    <Link
                        :href="
                            billsIndex.url({
                                query: { currency: props.selectedCurrency },
                            })
                        "
                        class="text-xs text-[#02CD86] hover:underline"
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
                                show-currency
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
                class="overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                :class="features?.bills?.enabled ? '' : 'xl:col-span-2'"
            >
                <div class="flex items-center justify-between gap-3">
                    <p class="text-[14.5px] font-medium text-white">
                        {{ t('finance.dashboard.where_it_went') }}
                    </p>
                    <Link
                        :href="
                            reportRoute.url({
                                query: { currency: props.selectedCurrency },
                            })
                        "
                        class="text-xs text-[#02CD86] hover:underline"
                    >
                        {{ t('finance.reports.title') }}
                    </Link>
                </div>
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
                                dir="ltr"
                                >{{
                                    formatCurrencyDisplay(
                                        entry.value,
                                        props.selectedCurrency,
                                        intlLocale(locale),
                                    )
                                }}</span
                            >
                            <span class="w-10 text-end text-xs text-[#989898]"
                                >{{ dn(entry.pct) }}%</span
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
                class="overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
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
import { ArrowRight, Plane } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import LineChart from '@/components/charts/LineChart.vue';
import Ciphered from '@/components/Ciphered.vue';
import CipheredMoney from '@/components/CipheredMoney.vue';
import CompactMoney from '@/components/CompactMoney.vue';
import { useAmountMask } from '@/composables/useAmountMask';
import { useDisplayAmounts } from '@/composables/useDisplayAmounts';
import { usePageSubtitle } from '@/composables/usePageSubtitle';
import { useVaultPortfolio } from '@/composables/useVaultPortfolio';
import type { VaultPortfolioPayload } from '@/composables/useVaultPortfolio';
import { dayOfMonthInCalendar, formatAppDate } from '@/lib/date';
import { intlLocale, localizeDigits } from '@/lib/locale';
import { formatCurrencyDisplay } from '@/lib/money';
import type { CurrencyCode, Rates } from '@/lib/money';
import {
    dashboard,
    flightLog,
    portfolio as portfolioRoute,
    report as reportRoute,
} from '@/routes';
import { index as billsIndex } from '@/routes/bills';
import { index as transactionsIndex } from '@/routes/transactions';
import type { Logbook, Streak, StreakDayState } from '@/types/gamification';
import type { Encrypted } from '@/types/vault';

type TransactionType = 'cost' | 'income';
type Currency = 'toman' | 'usd' | 'eur';

type Category = {
    id: number;
    name: string;
    slug: string;
    type: TransactionType;
    color: string | null;
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

/**
 * The strip is a glance, so a day is a bar rather than a numbered square — but
 * it reads with the same colours the flight log page uses, or the two would
 * describe the same run differently.
 */
function stripDayClass(state: StreakDayState): string {
    return {
        logged: 'bg-[#02cd86]',
        no_spend: 'bg-[#02cd86]/35',
        grace: 'bg-[#3a3a3a]',
        missed: 'bg-white/6',
        open: 'bg-transparent ring-1 ring-inset ring-white/20',
    }[state];
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const page = usePage();
const features = computed(() => page.props.features);
const { t, locale } = useI18n();
const { masked } = useAmountMask();

/** A plain count, rendered in the viewer's own digits — Persian for `fa`,
 *  unchanged otherwise. `t()` interpolation stringifies numbers as-is. */
function dn(value: number): string {
    return localizeDigits(value, locale.value);
}
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

// The mock's per-page header subtitle: "{month} {year} · {N} days left".
usePageSubtitle(() =>
    t('finance.dashboard.subtitle', {
        month: props.period.month,
        year: dn(props.period.year),
        days: t('finance.calendar.days_left', {
            days: dn(daysRemaining.value),
        }),
    }),
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
        cost: masked.value
            ? '••••••'
            : formatCurrencyDisplay(
                  costNum.value,
                  props.selectedCurrency,
                  intlLocale(locale.value),
              ),
        income: masked.value
            ? '••••••'
            : formatCurrencyDisplay(
                  incomeNum.value,
                  props.selectedCurrency,
                  intlLocale(locale.value),
              ),
        month: props.period.month,
        days: dn(daysRemaining.value),
        amount: masked.value
            ? '••••••'
            : formatCurrencyDisplay(
                  safeToSpendPerDay.value ?? 0,
                  props.selectedCurrency,
                  intlLocale(locale.value),
              ),
    }),
);

/** The one real, data-driven trigger for the "needs a decision" card: this
 *  period's spending has already outrun its income. Deliberately not guessing
 *  at anything fancier (a pending purchase, a risky bill) that the app has no
 *  way to actually know about yet. */
const needsDecision = computed(() => totalsReady.value && balance.value < 0);

const needsDecisionBody = computed(() =>
    t('finance.dashboard.needs_decision_body', {
        amount: formatCurrencyDisplay(
            Math.abs(balance.value),
            props.selectedCurrency,
            intlLocale(locale.value),
        ),
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
// Coloured by the category's own colour when it has one (matching the chip
// on Transactions/Report), and only falling back to the palette rotation for
// categories nobody has coloured, so the same category reads the same
// colour everywhere in the app rather than shifting with sort order.
const categoryBreakdown = computed(() => {
    const totals = new Map<string, { value: number; color: string | null }>();

    for (const transaction of props.transactions.costs) {
        const name = categoryName(transaction);
        const existing = totals.get(name);

        totals.set(name, {
            value: (existing?.value ?? 0) + amountOf(transaction),
            color: existing?.color ?? categoryColor(transaction),
        });
    }

    const sorted = [...totals.entries()].sort(
        (left, right) => right[1].value - left[1].value,
    );
    const top = sorted.slice(0, 5);
    const restTotal = sorted
        .slice(5)
        .reduce((acc, [, entry]) => acc + entry.value, 0);

    if (restTotal > 0) {
        top.push([
            t('finance.categories.cost.other'),
            { value: restTotal, color: null },
        ]);
    }

    return {
        labels: top.map(([label]) => label),
        series: top.map(([, entry]) => Math.round(entry.value * 100) / 100),
        colors: top.map(
            ([, entry], index) =>
                entry.color ?? chartPalette[index % chartPalette.length],
        ),
        total: top.reduce((acc, [, entry]) => acc + entry.value, 0),
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

/** Day-of-month in the viewer's own calendar, so the buckets line up with the
 *  Jalali month the period covers rather than the Gregorian one underneath. */
function dayOfMonthIndex(isoDate: string): number {
    return dayOfMonthInCalendar(isoDate, displayCalendar.value);
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
        categories: cost.map((_, index) => dn(index + 1)),
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

function findCategory(transaction: Transaction): Category | null {
    if (transaction.category) {
        return transaction.category;
    }

    return (
        (props.categories[transaction.type] ?? []).find(
            (category) => category.id === transaction.category_id,
        ) ?? null
    );
}

function categoryName(transaction: Transaction): string {
    return (
        findCategory(transaction)?.name ?? t('finance.categories.uncategorized')
    );
}

function categoryColor(transaction: Transaction): string | null {
    return findCategory(transaction)?.color ?? null;
}
</script>
