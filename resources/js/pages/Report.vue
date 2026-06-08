<template>
    <Head :title="t('finance.reports.title')" />

    <div
        class="flex h-full min-h-[calc(100vh-92px)] flex-1 flex-col overflow-x-auto bg-[#111111]"
    >
        <!-- Hero / period summary -->
        <section class="mx-[18px] mt-5 rounded-[22px] bg-[#1a1a1a] p-5 ring-1 ring-white/10 shadow-[0_18px_45px_rgba(0,0,0,0.2)]">
            <div
                class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between"
            >
                <div class="max-w-2xl space-y-3">
                    <p
                        class="text-xs font-semibold tracking-[0.35em] text-[#6C4EE9] uppercase"
                    >
                        {{ t('finance.reports.eyebrow') }}
                    </p>
                    <div class="space-y-2">
                        <h1
                            class="text-3xl font-semibold tracking-tight text-white sm:text-4xl"
                        >
                            {{ t('finance.reports.heading') }}
                        </h1>
                        <p class="text-sm text-[#989898]">
                            {{ t('finance.reports.description') }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:min-w-xl">
                    <div
                        class="rounded-[14px] border border-white/10 bg-[#252525] p-4 shadow-xs"
                    >
                        <p
                            class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                        >
                            {{ t('finance.fields.range') }}
                        </p>
                        <p class="mt-2 text-sm font-semibold text-white">
                            {{ props.period.label }}
                        </p>
                    </div>

                    <div
                        class="rounded-[14px] border border-white/10 bg-[#252525] p-4 shadow-xs"
                    >
                        <p
                            class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                        >
                            {{ t('finance.reports.transactions') }}
                        </p>
                        <p class="mt-2 text-sm font-semibold text-white">
                            {{ props.summary.count }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Filters -->
        <section
            class="mx-[18px] mt-[18px] rounded-[22px] bg-[#1a1a1a] p-5 ring-1 ring-white/10 shadow-[0_18px_45px_rgba(0,0,0,0.2)]"
        >
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
                                    ? 'bg-[#111111] text-white'
                                    : 'bg-white/5 text-[#989898] ring-1 ring-white/10 hover:bg-white/10 hover:text-white',
                            ]"
                            @click="selectRange(range.value)"
                        >
                            {{ range.label }}
                        </button>
                    </div>

                    <!-- Currency selector -->
                    <div class="grid gap-2 lg:min-w-56">
                        <Label for="report_currency">{{
                            t('finance.fields.display_currency')
                        }}</Label>
                        <Select v-model="selectedCurrency">
                            <SelectTrigger
                                id="report_currency"
                                :class="filterFieldClass"
                            >
                                <SelectValue
                                    :placeholder="
                                        t('finance.filters.select_currency')
                                    "
                                />
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
                <div class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr_0.9fr_auto]">
                    <div class="grid gap-2">
                        <Label for="report_search">{{
                            t('finance.fields.search')
                        }}</Label>
                        <Input
                            id="report_search"
                            v-model="search"
                            :class="filterFieldClass"
                            :placeholder="t('finance.filters.title_or_note')"
                            @keyup.enter="applyFilters()"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="report_type">{{
                            t('finance.fields.type')
                        }}</Label>
                        <Select
                            v-model="selectedType"
                            @update:model-value="applyTypeFilter"
                        >
                            <SelectTrigger
                                id="report_type"
                                :class="filterFieldClass"
                            >
                                <SelectValue
                                    :placeholder="
                                        t('finance.filters.all_types')
                                    "
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{{
                                    t('finance.filters.all_types')
                                }}</SelectItem>
                                <SelectItem value="cost">{{
                                    t('finance.filters.costs')
                                }}</SelectItem>
                                <SelectItem value="income">{{
                                    t('finance.filters.incomes')
                                }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="report_category">{{
                            t('finance.fields.category')
                        }}</Label>
                        <Select
                            v-model="selectedCategory"
                            @update:model-value="applyFilters()"
                        >
                            <SelectTrigger
                                id="report_category"
                                :class="filterFieldClass"
                            >
                                <SelectValue
                                    :placeholder="
                                        t('finance.filters.all_categories')
                                    "
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{{
                                    t('finance.filters.all_categories')
                                }}</SelectItem>
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
                            class="rounded-full bg-white/10 px-5 text-white shadow-none ring-1 ring-white/20 hover:bg-white/15"
                            @click="applyFilters()"
                        >
                            <Search class="size-4" />
                            {{ t('finance.actions.filter') }}
                        </Button>
                        <Button
                            variant="outline"
                            class="size-10 rounded-full bg-white/10 p-0 text-[#989898] ring-1 ring-white/15 hover:bg-white/15"
                            @click="clearTransactionFilters"
                        >
                            <RotateCcw class="size-4" />
                            <span class="sr-only">{{
                                t('finance.actions.reset_filters')
                            }}</span>
                        </Button>
                    </div>
                </div>

                <!-- Custom date range -->
                <div
                    v-if="selectedRange === 'custom'"
                    class="grid gap-4 lg:grid-cols-[1fr_1fr_auto]"
                >
                    <div class="grid gap-2">
                        <Label for="from_date">{{
                            t('finance.fields.from')
                        }}</Label>
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
                        <Label for="to_date">{{ t('finance.fields.to') }}</Label>
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
                            class="w-full rounded-full bg-white/10 px-5 text-white shadow-none ring-1 ring-white/20 hover:bg-white/15 lg:w-auto"
                            @click="applyFilters()"
                        >
                            {{ t('finance.actions.apply_custom_range') }}
                        </Button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Summary cards -->
        <div class="grid gap-[18px] px-[18px] pt-[18px] md:grid-cols-3">
            <article
                class="kpi-card-income overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 ring-1 ring-white/10 shadow-[0_18px_45px_rgba(0,0,0,0.2)]"
            >
                <p
                    class="text-xs font-medium tracking-[0.2em] text-[#02CD86] uppercase"
                >
                    {{ t('finance.metrics.income') }}
                </p>
                <p class="mt-3 text-2xl font-semibold text-white">
                    {{
                        formatMoney(
                            props.summary.income,
                            props.selectedCurrency,
                        )
                    }}
                </p>
            </article>

            <article
                class="kpi-card-cost overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 ring-1 ring-white/10 shadow-[0_18px_45px_rgba(0,0,0,0.2)]"
            >
                <p
                    class="text-xs font-medium tracking-[0.2em] text-[#6C4EE9] uppercase"
                >
                    {{ t('finance.metrics.costs') }}
                </p>
                <p class="mt-3 text-2xl font-semibold text-white">
                    {{
                        formatMoney(props.summary.cost, props.selectedCurrency)
                    }}
                </p>
            </article>

            <article
                class="kpi-card-neutral overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 ring-1 ring-white/10 shadow-[0_18px_45px_rgba(0,0,0,0.2)]"
            >
                <p
                    class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                >
                    {{ t('finance.metrics.balance') }}
                </p>
                <p class="mt-3 text-2xl font-semibold text-white">
                    {{ balanceLabel }}
                </p>
            </article>
        </div>

        <!-- Transaction tables -->
        <div
            class="grid items-start gap-[18px] px-[18px] py-[18px] pb-[38px] lg:grid-cols-2"
        >
            <!-- Costs table -->
            <section class="kpi-card-cost overflow-hidden rounded-[22px] bg-[#1a1a1a] ring-1 ring-white/10 shadow-[0_18px_45px_rgba(0,0,0,0.2)]">
                <div
                    class="flex items-center justify-between gap-4 px-5 py-[29px]"
                >
                    <div>
                        <p class="text-xs font-medium text-[#6C4EE9] uppercase">
                            {{ t('finance.metrics.costs') }}
                        </p>
                        <h2
                            class="text-[22px] leading-none font-normal text-white"
                        >
                            {{ t('finance.tables.money_going_out') }}
                        </h2>
                    </div>
                    <span class="text-xs text-[#989898]">
                        {{ props.transactions.costs.length }}
                        {{ t('finance.metrics.entries') }}
                    </span>
                </div>

                <div class="overflow-x-auto px-3 pb-5">
                    <table
                        class="w-full border-separate border-spacing-y-0 text-sm"
                    >
                        <thead>
                            <tr class="text-left text-base">
                                <th
                                    class="rounded-l-2xl bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                                >
                                    {{ t('finance.fields.subject') }}
                                </th>
                                <th
                                    class="bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                                >
                                    {{ t('finance.fields.category') }}
                                </th>
                                <th
                                    class="bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                                >
                                    {{ t('finance.fields.amount') }}
                                </th>
                                <th
                                    class="hidden rounded-r-2xl bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:table-cell sm:px-5"
                                >
                                    {{ t('finance.fields.date') }}
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
                                    class="px-3 py-[17px] text-center text-[17px] leading-none font-normal text-white sm:px-5"
                                >
                                    {{ transaction.title }}
                                    <div
                                        v-if="transaction.description"
                                        class="mt-1 line-clamp-1 text-xs text-[#989898]"
                                    >
                                        {{ transaction.description }}
                                    </div>
                                </td>
                                <td class="px-3 py-[17px] text-center sm:px-5">
                                    <span
                                        class="inline-flex min-w-[118px] justify-center rounded-md bg-white/10 px-4 py-2 text-[17px] leading-none font-normal text-white"
                                    >
                                        {{
                                            transaction.category?.name ??
                                            t('finance.categories.uncategorized')
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-[17px] text-center text-[17px] leading-none font-semibold text-[#6C4EE9] sm:px-5"
                                >
                                    {{
                                        formatMoney(
                                            transaction.display_amount,
                                            transaction.display_currency,
                                        )
                                    }}
                                </td>
                                <td
                                    class="hidden px-3 py-[17px] text-center text-[17px] leading-none font-normal text-white sm:table-cell sm:px-5"
                                >
                                    {{ displayDate(transaction.occurred_at) }}
                                </td>
                            </tr>
                            <tr v-if="props.transactions.costs.length === 0">
                                <td
                                    colspan="4"
                                    class="px-5 py-12 text-center text-[#989898]"
                                >
                                    {{ t('finance.dashboard.no_costs') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Incomes table -->
            <section class="kpi-card-income overflow-hidden rounded-[22px] bg-[#1a1a1a] ring-1 ring-white/10 shadow-[0_18px_45px_rgba(0,0,0,0.2)]">
                <div
                    class="flex items-center justify-between gap-4 px-5 py-[29px]"
                >
                    <div>
                        <p
                            class="text-xs font-medium text-[#02CD86] uppercase"
                        >
                            {{ t('finance.filters.incomes') }}
                        </p>
                        <h2
                            class="text-[22px] leading-none font-normal text-white"
                        >
                            {{ t('finance.tables.money_coming_in') }}
                        </h2>
                    </div>
                    <span class="text-xs text-[#989898]">
                        {{ props.transactions.incomes.length }}
                        {{ t('finance.metrics.entries') }}
                    </span>
                </div>

                <div class="overflow-x-auto px-3 pb-5">
                    <table
                        class="w-full border-separate border-spacing-y-0 text-sm"
                    >
                        <thead>
                            <tr class="text-left text-base">
                                <th
                                    class="rounded-l-2xl bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                                >
                                    {{ t('finance.fields.subject') }}
                                </th>
                                <th
                                    class="bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                                >
                                    {{ t('finance.fields.category') }}
                                </th>
                                <th
                                    class="bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                                >
                                    {{ t('finance.fields.amount') }}
                                </th>
                                <th
                                    class="hidden rounded-r-2xl bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:table-cell sm:px-5"
                                >
                                    {{ t('finance.fields.date') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="transaction in props.transactions
                                    .incomes"
                                :key="transaction.id"
                                class="group"
                            >
                                <td
                                    class="px-3 py-[17px] text-center text-[17px] leading-none font-normal text-white sm:px-5"
                                >
                                    {{ transaction.title }}
                                    <div
                                        v-if="transaction.description"
                                        class="mt-1 line-clamp-1 text-xs text-[#989898]"
                                    >
                                        {{ transaction.description }}
                                    </div>
                                </td>
                                <td class="px-3 py-[17px] text-center sm:px-5">
                                    <span
                                        class="inline-flex min-w-[118px] justify-center rounded-md bg-white/10 px-4 py-2 text-[17px] leading-none font-normal text-white"
                                    >
                                        {{
                                            transaction.category?.name ??
                                            t('finance.categories.uncategorized')
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-[17px] text-center text-[17px] leading-none font-semibold text-[#02CD86] sm:px-5"
                                >
                                    {{
                                        formatMoney(
                                            transaction.display_amount,
                                            transaction.display_currency,
                                        )
                                    }}
                                </td>
                                <td
                                    class="hidden px-3 py-[17px] text-center text-[17px] leading-none font-normal text-white sm:table-cell sm:px-5"
                                >
                                    {{ displayDate(transaction.occurred_at) }}
                                </td>
                            </tr>
                            <tr v-if="props.transactions.incomes.length === 0">
                                <td
                                    colspan="4"
                                    class="px-5 py-12 text-center text-[#989898]"
                                >
                                    {{ t('finance.dashboard.no_incomes') }}
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
import { Head, router, usePage } from '@inertiajs/vue3';
import { RotateCcw, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
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
import { formatAppDate } from '@/lib/date';
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

const { t } = useI18n();

const ranges = computed<Array<{ label: string; value: ReportRange }>>(() => [
    { label: t('finance.reports.this_month'), value: 'this_month' },
    { label: t('finance.reports.this_season'), value: 'this_season' },
    { label: t('finance.reports.yearly'), value: 'yearly' },
    { label: t('finance.reports.custom'), value: 'custom' },
]);

const filterFieldClass =
    'h-9 w-full rounded-md !border-white/10 !bg-[#252525] px-3 text-sm font-normal !text-white shadow-none [color-scheme:dark] placeholder:!text-[#686868] focus-visible:!border-[#947BFF] focus-visible:!ring-2 focus-visible:!ring-[#947BFF]/25 [&_svg]:!text-[#989898]';

const selectedRange = ref<ReportRange>(props.filters.range);
const fromDate = ref(props.filters.from);
const toDate = ref(props.filters.to);
const search = ref(props.filters.search);
const selectedType = ref<FilterType>(props.filters.type);
const selectedCategory = ref(props.filters.category?.toString() ?? 'all');
const selectedCurrency = ref<Currency>(props.selectedCurrency);
const page = usePage();
const displayCalendar = computed(
    () => (page.props.calendar as string | undefined) ?? 'gregorian',
);

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

function displayDate(value: string): string {
    return formatAppDate(value, displayCalendar.value);
}
</script>
