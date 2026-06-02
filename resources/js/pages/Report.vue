<template>
    <Head title="Reports" />

    <div
        class="flex h-full min-h-[calc(100vh-92px)] flex-1 flex-col overflow-x-auto bg-[#2d2d2d] text-black"
    >
        <!-- Hero / period summary -->
        <section class="mx-[18px] mt-5 rounded-[22px] bg-white p-5 shadow-sm">
            <div
                class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between"
            >
                <div class="max-w-2xl space-y-3">
                    <p
                        class="text-xs font-semibold tracking-[0.35em] text-amber-700 uppercase"
                    >
                        Time-based reports
                    </p>
                    <div class="space-y-2">
                        <h1
                            class="text-3xl font-semibold tracking-tight text-neutral-950 sm:text-4xl"
                        >
                            Slice your money story by month, season, year, or
                            any custom range.
                        </h1>
                        <p class="text-sm text-neutral-600">
                            Each filter refreshes the transaction list and
                            totals so you can compare what came in and what went
                            out over the period you care about.
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:min-w-xl">
                    <div
                        class="rounded-[14px] border border-[#e6e6e6] bg-[#f9f9f9] p-4 shadow-xs"
                    >
                        <p
                            class="text-xs font-medium tracking-[0.2em] text-neutral-500 uppercase"
                        >
                            Range
                        </p>
                        <p
                            class="mt-2 text-sm font-semibold text-neutral-900"
                        >
                            {{ props.period.label }}
                        </p>
                    </div>

                    <div
                        class="rounded-[14px] border border-[#e6e6e6] bg-[#f9f9f9] p-4 shadow-xs"
                    >
                        <p
                            class="text-xs font-medium tracking-[0.2em] text-neutral-500 uppercase"
                        >
                            Transactions
                        </p>
                        <p
                            class="mt-2 text-sm font-semibold text-neutral-900"
                        >
                            {{ props.summary.count }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Filters -->
        <section class="mx-[18px] mt-[18px] rounded-[22px] bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-5">
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                >
                    <!-- Range buttons -->
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="range in ranges"
                            :key="range.value"
                            type="button"
                            :class="[
                                'rounded-full px-4 py-1.5 text-sm font-normal transition',
                                selectedRange === range.value
                                    ? 'bg-[#2d2d2d] text-white'
                                    : 'bg-white text-[#2d2d2d] ring-1 ring-[#e6e6e6] hover:bg-[#f7f7f7]',
                            ]"
                            @click="selectRange(range.value)"
                        >
                            {{ range.label }}
                        </button>
                    </div>

                    <!-- Currency selector -->
                    <div class="grid gap-2 lg:min-w-56">
                        <Label for="report_currency">Display currency</Label>
                        <Select v-model="selectedCurrency">
                            <SelectTrigger
                                id="report_currency"
                                :class="filterFieldClass"
                            >
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

                <!-- Search / type / category filters -->
                <div
                    class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr_0.9fr_auto]"
                >
                    <div class="grid gap-2">
                        <Label for="report_search">Search</Label>
                        <Input
                            id="report_search"
                            v-model="search"
                            :class="filterFieldClass"
                            placeholder="Title or note"
                            @keyup.enter="applyFilters()"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="report_type">Type</Label>
                        <Select
                            v-model="selectedType"
                            @update:model-value="applyTypeFilter"
                        >
                            <SelectTrigger
                                id="report_type"
                                :class="filterFieldClass"
                            >
                                <SelectValue placeholder="All types" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All types</SelectItem>
                                <SelectItem value="cost">Costs</SelectItem>
                                <SelectItem value="income">Incomes</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="report_category">Category</Label>
                        <Select
                            v-model="selectedCategory"
                            @update:model-value="applyFilters()"
                        >
                            <SelectTrigger
                                id="report_category"
                                :class="filterFieldClass"
                            >
                                <SelectValue placeholder="All categories" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All categories</SelectItem>
                                <SelectItem
                                    v-for="category in reportCategories"
                                    :key="category.id"
                                    :value="category.id.toString()"
                                >
                                    {{ category.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex items-end gap-2">
                        <Button
                            class="rounded-full bg-white px-5 text-[#2d2d2d] shadow-none ring-1 ring-[#e6e6e6] hover:bg-[#f7f7f7]"
                            @click="applyFilters()"
                        >
                            <Search class="size-4" />
                            Filter
                        </Button>
                        <Button
                            variant="outline"
                            class="size-10 rounded-full border-[#d9d9d9] bg-[#d9d9d9] p-0 text-[#2d2d2d] hover:bg-[#cfcfcf]"
                            @click="clearTransactionFilters"
                        >
                            <RotateCcw class="size-4" />
                            <span class="sr-only">Reset filters</span>
                        </Button>
                    </div>
                </div>

                <!-- Custom date range -->
                <div
                    v-if="selectedRange === 'custom'"
                    class="grid gap-4 lg:grid-cols-[1fr_1fr_auto]"
                >
                    <div class="grid gap-2">
                        <Label for="from_date">From</Label>
                        <BirthdatePicker
                            v-model="fromDate"
                            name="from_date"
                            :required="false"
                            :years-back="16"
                            :years-forward="1"
                            :trigger-class="filterFieldClass"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="to_date">To</Label>
                        <BirthdatePicker
                            v-model="toDate"
                            name="to_date"
                            :required="false"
                            :years-back="16"
                            :years-forward="1"
                            :trigger-class="filterFieldClass"
                        />
                    </div>

                    <div class="flex items-end">
                        <Button
                            class="w-full rounded-full bg-white px-5 text-[#2d2d2d] shadow-none ring-1 ring-[#e6e6e6] hover:bg-[#f7f7f7] lg:w-auto"
                            @click="applyFilters()"
                        >
                            Apply custom range
                        </Button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Summary cards -->
        <div
            class="grid gap-[18px] px-[18px] pt-[18px] md:grid-cols-3"
        >
            <article class="overflow-hidden rounded-[22px] bg-white p-5 shadow-sm">
                <p
                    class="text-xs font-medium tracking-[0.2em] text-emerald-600 uppercase"
                >
                    Income
                </p>
                <p
                    class="mt-3 text-2xl font-semibold text-neutral-950"
                >
                    {{
                        formatMoney(
                            props.summary.income,
                            props.selectedCurrency,
                        )
                    }}
                </p>
            </article>

            <article class="overflow-hidden rounded-[22px] bg-white p-5 shadow-sm">
                <p
                    class="text-xs font-medium tracking-[0.2em] text-rose-600 uppercase"
                >
                    Costs
                </p>
                <p
                    class="mt-3 text-2xl font-semibold text-neutral-950"
                >
                    {{
                        formatMoney(props.summary.cost, props.selectedCurrency)
                    }}
                </p>
            </article>

            <article class="overflow-hidden rounded-[22px] bg-white p-5 shadow-sm">
                <p
                    class="text-xs font-medium tracking-[0.2em] text-neutral-500 uppercase"
                >
                    Balance
                </p>
                <p
                    class="mt-3 text-2xl font-semibold text-neutral-950"
                >
                    {{ balanceLabel }}
                </p>
            </article>
        </div>

        <!-- Transaction tables -->
        <div
            class="grid items-start gap-[18px] px-[18px] py-[18px] pb-[38px] lg:grid-cols-2"
        >
            <!-- Costs table -->
            <section class="overflow-hidden rounded-[22px] bg-white shadow-sm">
                <div
                    class="flex items-center justify-between gap-4 px-5 py-[29px]"
                >
                    <div>
                        <p class="text-xs font-medium text-rose-600 uppercase">
                            Costs
                        </p>
                        <h2
                            class="text-[22px] leading-none font-normal text-black"
                        >
                            Money going out
                        </h2>
                    </div>
                    <span class="text-xs text-neutral-500">
                        {{ props.transactions.costs.length }} entries
                    </span>
                </div>

                <div class="overflow-x-auto px-3 pb-5">
                    <table
                        class="w-full border-separate border-spacing-y-0 text-sm"
                    >
                        <thead>
                            <tr class="text-left text-base text-black">
                                <th
                                    class="rounded-l-2xl bg-[#f0ecff] px-3 py-4 text-center font-normal sm:px-5"
                                >
                                    Subject
                                </th>
                                <th
                                    class="bg-[#f0ecff] px-3 py-4 text-center font-normal sm:px-5"
                                >
                                    Category
                                </th>
                                <th
                                    class="bg-[#f0ecff] px-3 py-4 text-center font-normal sm:px-5"
                                >
                                    Amount
                                </th>
                                <th
                                    class="hidden rounded-r-2xl bg-[#f0ecff] px-3 py-4 text-center font-normal sm:table-cell sm:px-5"
                                >
                                    Date
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="transaction in props.transactions.costs"
                                :key="transaction.id"
                                class="group"
                            >
                                <td
                                    class="px-3 py-[17px] text-center text-[17px] leading-none font-normal text-black sm:px-5"
                                >
                                    {{ transaction.title }}
                                    <div
                                        v-if="transaction.description"
                                        class="mt-1 line-clamp-1 text-xs text-neutral-500"
                                    >
                                        {{ transaction.description }}
                                    </div>
                                </td>
                                <td class="px-3 py-[17px] text-center sm:px-5">
                                    <span
                                        class="inline-flex min-w-[118px] justify-center rounded-md bg-[#d9d9d9] px-4 py-2 text-[17px] leading-none font-normal text-black"
                                    >
                                        {{
                                            transaction.category?.name ??
                                            'Uncategorized'
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-[17px] text-center text-[17px] leading-none font-semibold text-rose-700 sm:px-5"
                                >
                                    {{
                                        formatMoney(
                                            transaction.display_amount,
                                            transaction.display_currency,
                                        )
                                    }}
                                </td>
                                <td
                                    class="hidden px-3 py-[17px] text-center text-[17px] leading-none font-normal text-black sm:table-cell sm:px-5"
                                >
                                    {{ transaction.occurred_at }}
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

            <!-- Incomes table -->
            <section class="overflow-hidden rounded-[22px] bg-white shadow-sm">
                <div
                    class="flex items-center justify-between gap-4 px-5 py-[29px]"
                >
                    <div>
                        <p
                            class="text-xs font-medium text-emerald-600 uppercase"
                        >
                            Incomes
                        </p>
                        <h2
                            class="text-[22px] leading-none font-normal text-black"
                        >
                            Money coming in
                        </h2>
                    </div>
                    <span class="text-xs text-neutral-500">
                        {{ props.transactions.incomes.length }} entries
                    </span>
                </div>

                <div class="overflow-x-auto px-3 pb-5">
                    <table
                        class="w-full border-separate border-spacing-y-0 text-sm"
                    >
                        <thead>
                            <tr class="text-left text-base text-black">
                                <th
                                    class="rounded-l-2xl bg-[#f0ecff] px-3 py-4 text-center font-normal sm:px-5"
                                >
                                    Subject
                                </th>
                                <th
                                    class="bg-[#f0ecff] px-3 py-4 text-center font-normal sm:px-5"
                                >
                                    Category
                                </th>
                                <th
                                    class="bg-[#f0ecff] px-3 py-4 text-center font-normal sm:px-5"
                                >
                                    Amount
                                </th>
                                <th
                                    class="hidden rounded-r-2xl bg-[#f0ecff] px-3 py-4 text-center font-normal sm:table-cell sm:px-5"
                                >
                                    Date
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="transaction in props.transactions.incomes"
                                :key="transaction.id"
                                class="group"
                            >
                                <td
                                    class="px-3 py-[17px] text-center text-[17px] leading-none font-normal text-black sm:px-5"
                                >
                                    {{ transaction.title }}
                                    <div
                                        v-if="transaction.description"
                                        class="mt-1 line-clamp-1 text-xs text-neutral-500"
                                    >
                                        {{ transaction.description }}
                                    </div>
                                </td>
                                <td class="px-3 py-[17px] text-center sm:px-5">
                                    <span
                                        class="inline-flex min-w-[118px] justify-center rounded-md bg-[#d9d9d9] px-4 py-2 text-[17px] leading-none font-normal text-black"
                                    >
                                        {{
                                            transaction.category?.name ??
                                            'Uncategorized'
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-[17px] text-center text-[17px] leading-none font-semibold text-emerald-700 sm:px-5"
                                >
                                    {{
                                        formatMoney(
                                            transaction.display_amount,
                                            transaction.display_currency,
                                        )
                                    }}
                                </td>
                                <td
                                    class="hidden px-3 py-[17px] text-center text-[17px] leading-none font-normal text-black sm:table-cell sm:px-5"
                                >
                                    {{ transaction.occurred_at }}
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
import { RotateCcw, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import BirthdatePicker from '@/components/BirthdatePicker.vue';
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
type FilterType = TransactionType | 'all';
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
        search: string;
        type: FilterType;
        category: number | null;
    };
    period: {
        label: string;
    };
    transactions: {
        costs: Transaction[];
        incomes: Transaction[];
    };
    categories: Record<TransactionType, Category[]>;
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

const filterFieldClass =
    'h-9 w-full rounded-md !border-[#989898] !bg-[#f4f4f4] px-3 text-sm font-normal !text-[#2d2d2d] shadow-none [color-scheme:light] placeholder:!text-[#989898] focus-visible:!border-[#947BFF] focus-visible:!ring-2 focus-visible:!ring-[#947BFF]/25 dark:!border-[#989898] dark:!bg-[#f4f4f4] dark:!text-[#2d2d2d] dark:hover:!bg-[#eeeeee] [&_svg]:!text-[#2d2d2d]';

const selectedRange = ref<ReportRange>(props.filters.range);
const fromDate = ref(props.filters.from);
const toDate = ref(props.filters.to);
const search = ref(props.filters.search);
const selectedType = ref<FilterType>(props.filters.type);
const selectedCategory = ref(props.filters.category?.toString() ?? 'all');
const selectedCurrency = ref<Currency>(props.selectedCurrency);

const balanceLabel = computed(() => {
    const income = Number(props.summary.income);
    const cost = Number(props.summary.cost);

    return formatMoney((income - cost).toFixed(2), props.selectedCurrency);
});

const reportCategories = computed(() => {
    if (selectedType.value === 'cost' || selectedType.value === 'income') {
        return props.categories[selectedType.value] ?? [];
    }

    return [...props.categories.cost, ...props.categories.income];
});

watch(
    () => props.filters,
    (filters) => {
        selectedRange.value = filters.range;
        fromDate.value = filters.from;
        toDate.value = filters.to;
        search.value = filters.search;
        selectedType.value = filters.type;
        selectedCategory.value = filters.category?.toString() ?? 'all';
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

watch(selectedType, () => {
    if (
        selectedCategory.value !== 'all' &&
        !reportCategories.value.some(
            (category) => category.id.toString() === selectedCategory.value,
        )
    ) {
        selectedCategory.value = 'all';
    }
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
            search: search.value || null,
            type: selectedType.value === 'all' ? null : selectedType.value,
            category:
                selectedCategory.value === 'all'
                    ? null
                    : selectedCategory.value,
            currency,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function applyTypeFilter(): void {
    if (
        selectedCategory.value !== 'all' &&
        !reportCategories.value.some(
            (category) => category.id.toString() === selectedCategory.value,
        )
    ) {
        selectedCategory.value = 'all';
    }

    applyFilters();
}

function clearTransactionFilters(): void {
    search.value = '';
    selectedType.value = 'all';
    selectedCategory.value = 'all';
    applyFilters();
}

function formatMoney(amount: string | number, currency: Currency): string {
    const value = Number(amount);

    return `${new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 2,
        minimumFractionDigits: value % 1 === 0 ? 0 : 2,
    }).format(value)} ${currency.toUpperCase()}`;
}
</script>
