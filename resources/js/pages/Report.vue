<template>
    <Head title="Reports" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6">
        <section
            class="overflow-hidden rounded-3xl border border-neutral-200/80 bg-[radial-gradient(circle_at_top_right,_#ffd8b4,_transparent_30%),radial-gradient(circle_at_bottom_left,_#d4efe2,_transparent_34%),linear-gradient(135deg,_#fff8f1,_#f4fbf7)] p-6 shadow-sm dark:border-neutral-800 dark:bg-[radial-gradient(circle_at_top_right,_#4a3022,_transparent_28%),radial-gradient(circle_at_bottom_left,_#173226,_transparent_34%),linear-gradient(135deg,_#121110,_#131a17)]"
        >
            <div
                class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between"
            >
                <div class="max-w-2xl space-y-3">
                    <p
                        class="text-xs font-semibold tracking-[0.35em] text-amber-700 uppercase dark:text-amber-300"
                    >
                        Time-based reports
                    </p>
                    <div class="space-y-2">
                        <h1
                            class="text-3xl font-semibold tracking-tight text-neutral-950 sm:text-4xl dark:text-neutral-50"
                        >
                            Slice your money story by month, season, year, or
                            any custom range.
                        </h1>
                        <p
                            class="text-sm text-neutral-600 dark:text-neutral-300"
                        >
                            Each filter refreshes the transaction list and
                            totals so you can compare what came in and what
                            went out over the period you care about.
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:min-w-xl">
                    <div
                        class="rounded-2xl border border-white/70 bg-white/75 p-4 shadow-xs backdrop-blur dark:border-white/10 dark:bg-white/5"
                    >
                        <p
                            class="text-xs font-medium tracking-[0.2em] text-neutral-500 uppercase dark:text-neutral-400"
                        >
                            Range
                        </p>
                        <p class="mt-2 text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                            {{ props.period.label }}
                        </p>
                    </div>

                    <div
                        class="rounded-2xl border border-white/70 bg-white/75 p-4 shadow-xs backdrop-blur dark:border-white/10 dark:bg-white/5"
                    >
                        <p
                            class="text-xs font-medium tracking-[0.2em] text-neutral-500 uppercase dark:text-neutral-400"
                        >
                            Transactions
                        </p>
                        <p class="mt-2 text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                            {{ props.summary.count }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section
            class="rounded-3xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
        >
            <div class="flex flex-col gap-5">
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="range in ranges"
                            :key="range.value"
                            :variant="
                                selectedRange === range.value
                                    ? 'default'
                                    : 'outline'
                            "
                            class="rounded-full"
                            @click="selectRange(range.value)"
                        >
                            {{ range.label }}
                        </Button>
                    </div>

                    <div class="grid gap-2 lg:min-w-56">
                        <Label for="report_currency">Display currency</Label>
                        <Select v-model="selectedCurrency">
                            <SelectTrigger id="report_currency" class="w-full">
                                <SelectValue placeholder="Select currency" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="currency in props.currencies"
                                    :key="currency.value"
                                    :value="currency.value"
                                >
                                    {{ currency.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div
                    :class="
                        selectedRange === 'custom'
                            ? 'grid gap-4 lg:grid-cols-[1fr_1fr_1fr_auto]'
                            : 'grid gap-4 lg:grid-cols-1'
                    "
                >
                    <template v-if="selectedRange === 'custom'">
                        <div class="grid gap-2">
                            <Label for="from_date">From</Label>
                            <Input
                                id="from_date"
                                v-model="fromDate"
                                type="date"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="to_date">To</Label>
                            <Input
                                id="to_date"
                                v-model="toDate"
                                type="date"
                            />
                        </div>
                    </template>

                    <div v-if="selectedRange === 'custom'" class="flex items-end">
                        <Button
                            class="w-full rounded-full lg:w-auto"
                            @click="applyFilters()"
                        >
                            Apply custom range
                        </Button>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
                class="rounded-3xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
            >
                <p
                    class="text-xs font-medium tracking-[0.2em] text-emerald-600 uppercase dark:text-emerald-300"
                >
                    Income
                </p>
                <p class="mt-3 text-2xl font-semibold text-neutral-950 dark:text-neutral-50">
                    {{
                        formatMoney(
                            props.summary.income,
                            props.selectedCurrency,
                        )
                    }}
                </p>
            </article>

            <article
                class="rounded-3xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
            >
                <p
                    class="text-xs font-medium tracking-[0.2em] text-rose-600 uppercase dark:text-rose-300"
                >
                    Costs
                </p>
                <p class="mt-3 text-2xl font-semibold text-neutral-950 dark:text-neutral-50">
                    {{ formatMoney(props.summary.cost, props.selectedCurrency) }}
                </p>
            </article>

            <article
                class="rounded-3xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950 md:col-span-2 xl:col-span-1"
            >
                <p
                    class="text-xs font-medium tracking-[0.2em] text-neutral-500 uppercase dark:text-neutral-400"
                >
                    Balance
                </p>
                <p class="mt-3 text-2xl font-semibold text-neutral-950 dark:text-neutral-50">
                    {{ balanceLabel }}
                </p>
            </article>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section
                class="rounded-3xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
            >
                <div
                    class="flex items-center justify-between gap-4 border-b p-5 dark:border-neutral-800"
                >
                    <div>
                        <p
                            class="text-xs font-medium text-rose-600 uppercase dark:text-rose-300"
                        >
                            Costs
                        </p>
                        <h2 class="text-lg font-semibold">Money going out</h2>
                    </div>
                    <span class="text-xs text-neutral-500 dark:text-neutral-400">
                        {{ props.transactions.costs.length }} entries
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b text-left text-xs tracking-wide text-neutral-500 uppercase dark:border-neutral-800"
                            >
                                <th class="px-3 py-3 font-medium sm:px-5">
                                    Title
                                </th>
                                <th class="px-3 py-3 font-medium sm:px-5">
                                    Category
                                </th>
                                <th
                                    class="hidden px-3 py-3 font-medium sm:table-cell sm:px-5"
                                >
                                    Date
                                </th>
                                <th
                                    class="px-3 py-3 text-right font-medium sm:px-5"
                                >
                                    Amount
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="transaction in props.transactions.costs"
                                :key="transaction.id"
                                class="border-b last:border-0 hover:bg-rose-50/50 dark:border-neutral-800 dark:hover:bg-rose-950/10"
                            >
                                <td class="px-3 py-4 sm:px-5">
                                    <div class="text-xs font-medium sm:text-sm">
                                        {{ transaction.title }}
                                    </div>
                                    <div
                                        v-if="transaction.description"
                                        class="mt-1 line-clamp-1 text-xs text-neutral-500"
                                    >
                                        {{ transaction.description }}
                                    </div>
                                </td>
                                <td class="px-3 py-4 sm:px-5">
                                    <span
                                        class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs dark:bg-neutral-900"
                                    >
                                        {{
                                            transaction.category?.name ??
                                            'Uncategorized'
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="hidden px-3 py-4 text-xs text-neutral-600 sm:table-cell sm:px-5 sm:text-sm dark:text-neutral-300"
                                >
                                    {{ transaction.occurred_at }}
                                </td>
                                <td
                                    class="px-3 py-4 text-right text-xs font-semibold text-rose-700 sm:px-5 sm:text-sm dark:text-rose-300"
                                >
                                    {{
                                        formatMoney(
                                            transaction.display_amount,
                                            transaction.display_currency,
                                        )
                                    }}
                                </td>
                            </tr>
                            <tr v-if="props.transactions.costs.length === 0">
                                <td
                                    colspan="4"
                                    class="px-5 py-12 text-center text-neutral-500"
                                >
                                    No costs matched this range.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                class="rounded-3xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
            >
                <div
                    class="flex items-center justify-between gap-4 border-b p-5 dark:border-neutral-800"
                >
                    <div>
                        <p
                            class="text-xs font-medium text-emerald-600 uppercase dark:text-emerald-300"
                        >
                            Incomes
                        </p>
                        <h2 class="text-lg font-semibold">Money coming in</h2>
                    </div>
                    <span class="text-xs text-neutral-500 dark:text-neutral-400">
                        {{ props.transactions.incomes.length }} entries
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b text-left text-xs tracking-wide text-neutral-500 uppercase dark:border-neutral-800"
                            >
                                <th class="px-3 py-3 font-medium sm:px-5">
                                    Title
                                </th>
                                <th class="px-3 py-3 font-medium sm:px-5">
                                    Category
                                </th>
                                <th
                                    class="hidden px-3 py-3 font-medium sm:table-cell sm:px-5"
                                >
                                    Date
                                </th>
                                <th
                                    class="px-3 py-3 text-right font-medium sm:px-5"
                                >
                                    Amount
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="transaction in props.transactions.incomes"
                                :key="transaction.id"
                                class="border-b last:border-0 hover:bg-emerald-50/50 dark:border-neutral-800 dark:hover:bg-emerald-950/10"
                            >
                                <td class="px-3 py-4 sm:px-5">
                                    <div class="text-xs font-medium sm:text-sm">
                                        {{ transaction.title }}
                                    </div>
                                    <div
                                        v-if="transaction.description"
                                        class="mt-1 line-clamp-1 text-xs text-neutral-500"
                                    >
                                        {{ transaction.description }}
                                    </div>
                                </td>
                                <td class="px-3 py-4 sm:px-5">
                                    <span
                                        class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs dark:bg-neutral-900"
                                    >
                                        {{
                                            transaction.category?.name ??
                                            'Uncategorized'
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="hidden px-3 py-4 text-xs text-neutral-600 sm:table-cell sm:px-5 sm:text-sm dark:text-neutral-300"
                                >
                                    {{ transaction.occurred_at }}
                                </td>
                                <td
                                    class="px-3 py-4 text-right text-xs font-semibold text-emerald-700 sm:px-5 sm:text-sm dark:text-emerald-300"
                                >
                                    {{
                                        formatMoney(
                                            transaction.display_amount,
                                            transaction.display_currency,
                                        )
                                    }}
                                </td>
                            </tr>
                            <tr v-if="props.transactions.incomes.length === 0">
                                <td
                                    colspan="4"
                                    class="px-5 py-12 text-center text-neutral-500"
                                >
                                    No incomes matched this range.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard, report } from '@/routes';

type ReportRange = 'this_month' | 'this_season' | 'yearly' | 'custom';
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
    amount: string;
    currency: Currency;
    display_amount: string;
    display_currency: Currency;
    title: string;
    description: string | null;
    occurred_at: string;
    category: Category | null;
    category_id: number;
};

type CurrencyOption = {
    label: string;
    value: Currency;
};

const props = defineProps<{
    filters: {
        range: ReportRange;
        from: string;
        to: string;
    };
    period: {
        label: string;
    };
    transactions: {
        costs: Transaction[];
        incomes: Transaction[];
    };
    currencies: CurrencyOption[];
    selectedCurrency: Currency;
    summary: {
        cost: string;
        income: string;
        count: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Reports',
                href: report(),
            },
        ],
    },
});

const ranges: Array<{ label: string; value: ReportRange }> = [
    { label: 'This month', value: 'this_month' },
    { label: 'This season', value: 'this_season' },
    { label: 'Yearly', value: 'yearly' },
    { label: 'Custom', value: 'custom' },
];

const selectedRange = ref<ReportRange>(props.filters.range);
const fromDate = ref(props.filters.from);
const toDate = ref(props.filters.to);
const selectedCurrency = ref<Currency>(props.selectedCurrency);

const balanceLabel = computed(() => {
    const income = Number(props.summary.income);
    const cost = Number(props.summary.cost);

    return formatMoney((income - cost).toFixed(2), props.selectedCurrency);
});

watch(
    () => props.filters,
    (filters) => {
        selectedRange.value = filters.range;
        fromDate.value = filters.from;
        toDate.value = filters.to;
    },
    { deep: true },
);

watch(
    () => props.selectedCurrency,
    (value) => {
        selectedCurrency.value = value;
    },
);

watch(selectedCurrency, (value) => {
    if (value === props.selectedCurrency) {
        return;
    }

    applyFilters(selectedRange.value, value);
});

function selectRange(range: ReportRange): void {
    selectedRange.value = range;

    if (range === 'custom') {
        return;
    }

    applyFilters(range);
}

function applyFilters(
    range: ReportRange = selectedRange.value,
    currency: Currency = selectedCurrency.value,
): void {
    router.get(
        report.url(),
        {
            range,
            from: fromDate.value,
            to: toDate.value,
            currency,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function formatMoney(amount: string | number, currency: Currency): string {
    const value = Number(amount);

    return `${new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 2,
        minimumFractionDigits: value % 1 === 0 ? 0 : 2,
    }).format(value)} ${currency.toUpperCase()}`;
}
</script>
