<template>
    <Head :title="t('finance.transactions.title')" />

    <div
        class="finance-dense flex min-h-[calc(100vh-92px)] shrink-0 flex-col overflow-x-hidden bg-[#111111]"
    >
        <section
            class="mx-[18px] mt-5 rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
        >
            <div class="flex flex-wrap items-end gap-3">
                <div class="grid min-w-[160px] flex-1 gap-1.5 sm:max-w-[220px]">
                    <Label for="transaction_search" class="text-xs">{{
                        t('finance.fields.search')
                    }}</Label>
                    <Input
                        id="transaction_search"
                        v-model="filterSearch"
                        :class="filterFieldClass"
                        :placeholder="t('finance.filters.title_or_note')"
                        @keyup.enter="applyFilters()"
                    />
                </div>

                <div class="grid min-w-[130px] flex-1 gap-1.5 sm:max-w-[170px]">
                    <Label for="transaction_category" class="text-xs">{{
                        t('finance.fields.category')
                    }}</Label>
                    <Select v-model="filterCategory">
                        <SelectTrigger
                            id="transaction_category"
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
                            <!-- Where the logbook's uncategorised count links
                                 to; without the option the filter would be
                                 active but invisible in the control. -->
                            <SelectItem value="none">{{
                                t('finance.filters.uncategorised')
                            }}</SelectItem>
                            <SelectItem
                                v-for="category in filterCategories"
                                :key="category.id"
                                :value="category.id.toString()"
                            >
                                {{ category.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid min-w-[210px] flex-1 gap-1.5 sm:max-w-[260px]">
                    <Label for="transaction_from" class="text-xs">{{
                        t('finance.fields.from')
                    }}</Label>
                    <BirthdatePicker
                        v-model="filterFrom"
                        name="transaction_from"
                        :required="false"
                        :years-back="16"
                        :years-forward="1"
                        :trigger-class="filterFieldClass"
                    />
                </div>

                <div class="grid min-w-[210px] flex-1 gap-1.5 sm:max-w-[260px]">
                    <Label for="transaction_to" class="text-xs">{{
                        t('finance.fields.to')
                    }}</Label>
                    <BirthdatePicker
                        v-model="filterTo"
                        name="transaction_to"
                        :required="false"
                        :years-back="16"
                        :years-forward="1"
                        :trigger-class="filterFieldClass"
                    />
                </div>

                <a
                    :href="`/transactions/export?currency=${selectedCurrency}`"
                    class="inline-flex h-9 shrink-0 items-center gap-2 rounded-full bg-white/5 px-4 text-xs whitespace-nowrap text-white ring-1 ring-white/15 transition-colors hover:bg-white/10 sm:text-sm"
                >
                    <Download class="size-4" />
                    {{ t('finance.actions.export_transactions') }}
                </a>
            </div>
        </section>

        <div
            class="grid items-start gap-[18px] px-[18px] py-6 sm:py-[38px] xl:grid-cols-2"
        >
            <section
                class="kpi-card-cost overflow-hidden rounded-[22px] bg-[#1a1a1a] shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 px-5 py-5"
                >
                    <div>
                        <h2
                            class="text-lg leading-none font-normal text-white sm:text-xl"
                        >
                            {{ t('finance.tables.money_going_out') }}
                        </h2>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <Button
                            class="h-10 w-max justify-between rounded-md bg-[linear-gradient(90deg,#947BFF_0%,#6C4EE9_100%)] px-3.5 text-sm leading-none font-bold text-white shadow-[0_10px_20px_rgba(108,78,233,0.22)] transition hover:brightness-105 sm:h-11 sm:text-base"
                            @click="openCreateForm('cost')"
                        >
                            <span class="grid text-left">
                                <span class="col-start-1 row-start-1">
                                    {{ t('finance.actions.add_cost') }}
                                </span>
                                <span
                                    class="invisible col-start-1 row-start-1"
                                    aria-hidden="true"
                                >
                                    {{ t('finance.actions.add_income') }}
                                </span>
                            </span>
                            <span
                                class="grid h-[1.65em] w-[1.65em] min-w-[1.65em] shrink-0 place-items-center rounded-md border border-white/25 bg-[linear-gradient(135deg,rgba(255,255,255,0.24)_0%,rgba(45,45,45,0.72)_42%,rgba(45,45,45,0.96)_100%)] shadow-[inset_0_1px_0_rgba(255,255,255,0.22)]"
                            >
                                <Plus class="size-5" />
                            </span>
                        </Button>
                    </div>
                </div>

                <div class="overflow-x-auto px-3 pb-5">
                    <table
                        class="w-full border-separate border-spacing-y-0 text-sm"
                    >
                        <thead>
                            <tr class="text-left">
                                <th
                                    class="rounded-l-2xl bg-[#252525] px-3 py-3 text-center font-normal text-[#989898] sm:px-5"
                                >
                                    {{ t('finance.fields.subject') }}
                                </th>
                                <th
                                    class="bg-[#252525] px-3 py-3 text-center font-normal text-[#989898] sm:px-5"
                                >
                                    {{ t('finance.fields.category') }}
                                </th>
                                <th
                                    class="hidden bg-[#252525] px-3 py-3 text-center font-normal whitespace-nowrap text-[#989898] sm:table-cell sm:px-5"
                                >
                                    {{ t('finance.fields.date') }}
                                </th>
                                <th
                                    class="rounded-r-2xl bg-[#252525] px-3 py-3 text-center font-normal text-[#989898] sm:px-5"
                                >
                                    {{ t('finance.fields.amount') }}
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
                                    class="px-3 py-3 text-center leading-tight font-normal text-white sm:px-5"
                                >
                                    <Ciphered
                                        :value="transaction.title"
                                        table="transactions"
                                    />
                                    <div
                                        v-if="transaction.description"
                                        class="mt-1 line-clamp-1 text-xs text-[#989898]"
                                    >
                                        <Ciphered
                                            :value="transaction.description"
                                            table="transactions"
                                        />
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-center sm:px-5">
                                    <span
                                        class="inline-flex min-w-[88px] justify-center rounded-md bg-white/10 px-3 py-1.5 leading-tight font-normal text-white"
                                    >
                                        {{ categoryName(transaction) }}
                                    </span>
                                </td>
                                <td
                                    class="hidden px-3 py-3 text-center leading-tight font-normal whitespace-nowrap text-white sm:table-cell sm:px-5"
                                >
                                    {{ displayDate(transaction.occurred_at) }}
                                </td>
                                <td
                                    class="px-3 py-3 text-center leading-tight font-normal whitespace-nowrap text-white sm:px-5"
                                >
                                    <CipheredMoney
                                        :amount="transaction.amount"
                                        :display-amount="
                                            transaction.display_amount
                                        "
                                        :currency="transaction.currency"
                                        :display-currency="
                                            transaction.display_currency
                                        "
                                        :rates="props.rates"
                                    />
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
                <!-- Cost table pagination -->
                <div
                    v-if="
                        props.transactions.meta?.costs &&
                        props.transactions.meta.costs.last_page > 1
                    "
                    class="flex items-center justify-center gap-3 border-t border-white/[0.07] px-5 py-3 text-xs"
                >
                    <button
                        :disabled="
                            props.transactions.meta.costs.current_page <= 1
                        "
                        class="rounded-full bg-white/10 px-3 py-1.5 text-white ring-1 ring-white/15 transition-colors hover:bg-white/15 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeCostPage(
                                props.transactions.meta.costs.current_page - 1,
                            )
                        "
                    >
                        ←
                    </button>
                    <span class="text-[#989898]">
                        {{ props.transactions.meta.costs.current_page }}
                        /
                        {{ props.transactions.meta.costs.last_page }}
                        <span class="ml-1 text-[#6b6b6b]"
                            >({{ props.transactions.meta.costs.total }})</span
                        >
                    </span>
                    <button
                        :disabled="
                            props.transactions.meta.costs.current_page >=
                            props.transactions.meta.costs.last_page
                        "
                        class="rounded-full bg-white/10 px-3 py-1.5 text-white ring-1 ring-white/15 transition-colors hover:bg-white/15 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeCostPage(
                                props.transactions.meta.costs.current_page + 1,
                            )
                        "
                    >
                        →
                    </button>
                </div>
            </section>

            <section
                class="kpi-card-income overflow-hidden rounded-[22px] bg-[#1a1a1a] shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 px-5 py-5"
                >
                    <div>
                        <h2
                            class="text-lg leading-none font-normal text-white sm:text-xl"
                        >
                            {{ t('finance.tables.money_coming_in') }}
                        </h2>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <Button
                            class="h-10 w-max justify-between rounded-md bg-[linear-gradient(90deg,#02CD86_0%,#00A96F_100%)] px-3.5 text-sm leading-none font-bold text-white shadow-[0_10px_20px_rgba(2,205,134,0.22)] transition hover:brightness-105 sm:h-11 sm:text-base"
                            @click="openCreateForm('income')"
                        >
                            <span class="grid text-left">
                                <span class="col-start-1 row-start-1">
                                    {{ t('finance.actions.add_income') }}
                                </span>
                                <span
                                    class="invisible col-start-1 row-start-1"
                                    aria-hidden="true"
                                >
                                    {{ t('finance.actions.add_income') }}
                                </span>
                            </span>
                            <span
                                class="grid h-[1.65em] w-[1.65em] min-w-[1.65em] shrink-0 place-items-center rounded-md border border-white/25 bg-[linear-gradient(135deg,rgba(255,255,255,0.24)_0%,rgba(45,45,45,0.72)_42%,rgba(45,45,45,0.96)_100%)] shadow-[inset_0_1px_0_rgba(255,255,255,0.22)]"
                            >
                                <Plus class="size-5" />
                            </span>
                        </Button>
                    </div>
                </div>

                <div class="overflow-x-auto px-3 pb-5">
                    <table
                        class="w-full border-separate border-spacing-y-0 text-sm"
                    >
                        <thead>
                            <tr class="text-left">
                                <th
                                    class="rounded-l-2xl bg-[#252525] px-3 py-3 text-center font-normal text-[#989898] sm:px-5"
                                >
                                    {{ t('finance.fields.subject') }}
                                </th>
                                <th
                                    class="bg-[#252525] px-3 py-3 text-center font-normal text-[#989898] sm:px-5"
                                >
                                    {{ t('finance.fields.category') }}
                                </th>
                                <th
                                    class="hidden bg-[#252525] px-3 py-3 text-center font-normal whitespace-nowrap text-[#989898] sm:table-cell sm:px-5"
                                >
                                    {{ t('finance.fields.date') }}
                                </th>
                                <th
                                    class="rounded-r-2xl bg-[#252525] px-3 py-3 text-center font-normal text-[#989898] sm:px-5"
                                >
                                    {{ t('finance.fields.amount') }}
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
                                    class="px-3 py-3 text-center leading-tight font-normal text-white sm:px-5"
                                >
                                    <Ciphered
                                        :value="transaction.title"
                                        table="transactions"
                                    />
                                    <div
                                        v-if="transaction.description"
                                        class="mt-1 line-clamp-1 text-xs text-[#989898]"
                                    >
                                        <Ciphered
                                            :value="transaction.description"
                                            table="transactions"
                                        />
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-center sm:px-5">
                                    <span
                                        class="inline-flex min-w-[88px] justify-center rounded-md bg-white/10 px-3 py-1.5 leading-tight font-normal text-white"
                                    >
                                        {{ categoryName(transaction) }}
                                    </span>
                                </td>
                                <td
                                    class="hidden px-3 py-3 text-center leading-tight font-normal whitespace-nowrap text-white sm:table-cell sm:px-5"
                                >
                                    {{ displayDate(transaction.occurred_at) }}
                                </td>
                                <td
                                    class="px-3 py-3 text-center leading-tight font-normal whitespace-nowrap text-white sm:px-5"
                                >
                                    <CipheredMoney
                                        :amount="transaction.amount"
                                        :display-amount="
                                            transaction.display_amount
                                        "
                                        :currency="transaction.currency"
                                        :display-currency="
                                            transaction.display_currency
                                        "
                                        :rates="props.rates"
                                    />
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
                <!-- Income table pagination -->
                <div
                    v-if="
                        props.transactions.meta?.incomes &&
                        props.transactions.meta.incomes.last_page > 1
                    "
                    class="flex items-center justify-center gap-3 border-t border-white/[0.07] px-5 py-3 text-xs"
                >
                    <button
                        :disabled="
                            props.transactions.meta.incomes.current_page <= 1
                        "
                        class="rounded-full bg-white/10 px-3 py-1.5 text-white ring-1 ring-white/15 transition-colors hover:bg-white/15 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeIncomePage(
                                props.transactions.meta.incomes.current_page -
                                    1,
                            )
                        "
                    >
                        ←
                    </button>
                    <span class="text-[#989898]">
                        {{ props.transactions.meta.incomes.current_page }}
                        /
                        {{ props.transactions.meta.incomes.last_page }}
                        <span class="ml-1 text-[#6b6b6b]"
                            >({{ props.transactions.meta.incomes.total }})</span
                        >
                    </span>
                    <button
                        :disabled="
                            props.transactions.meta.incomes.current_page >=
                            props.transactions.meta.incomes.last_page
                        "
                        class="rounded-full bg-white/10 px-3 py-1.5 text-white ring-1 ring-white/15 transition-colors hover:bg-white/15 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeIncomePage(
                                props.transactions.meta.incomes.current_page +
                                    1,
                            )
                        "
                    >
                        →
                    </button>
                </div>
            </section>
        </div>

        <TransactionDialog
            v-model:open="isDialogOpen"
            :type="dialogTransactionType"
            :transaction="editingTransaction"
            :categories="props.categories"
            :currencies="props.currencies"
        />
    </div>
</template>

<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Download, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BirthdatePicker from '@/components/BirthdatePicker.vue';
import Ciphered from '@/components/Ciphered.vue';
import CipheredMoney from '@/components/CipheredMoney.vue';
import TransactionDialog from '@/components/transactions/TransactionDialog.vue';
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
import type { Rates } from '@/lib/money';
import { dashboard } from '@/routes';
import { index as transactionsIndex } from '@/routes/transactions';
import type { Encrypted } from '@/types/vault';

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

type CurrencyOption = {
    label: string;
    value: Currency;
};

const props = defineProps<{
    filters: {
        search: string;
        type: FilterType;
        /** A category id, `'none'` for uncategorised-only, or null for all. */
        category: number | 'none' | null;
        from: string;
        to: string;
    };
    transactions: {
        costs: Transaction[];
        incomes: Transaction[];
        meta?: {
            costs: {
                current_page: number;
                last_page: number;
                total: number;
            } | null;
            incomes: {
                current_page: number;
                last_page: number;
                total: number;
            } | null;
        } | null;
    };
    categories: Record<TransactionType, Category[]>;
    currencies: CurrencyOption[];
    selectedCurrency: Currency;
    rates: Rates | null;
    summary: {
        cost: string;
        income: string;
        count: number;
    } | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Transactions',
                href: transactionsIndex(),
            },
        ],
    },
});

const isDialogOpen = ref(false);
const dialogTransactionType = ref<TransactionType>('cost');
const editingTransaction = ref<Transaction | null>(null);
const page = usePage();
const { t } = useI18n();
const displayCalendar = computed(
    () => (page.props.calendar as string | undefined) ?? 'gregorian',
);

const selectedCurrency = ref<Currency>(props.selectedCurrency);
const filterSearch = ref(props.filters.search);
const filterCategory = ref(props.filters.category?.toString() ?? 'all');
const filterFrom = ref(props.filters.from);
const filterTo = ref(props.filters.to);
const filterFieldClass =
    'h-9 w-full rounded-md !border-white/10 !bg-[#252525] px-3 text-sm font-normal !text-white shadow-none [color-scheme:dark] placeholder:!text-[#686868] focus-visible:!border-[#947BFF] focus-visible:!ring-2 focus-visible:!ring-[#947BFF]/25 [&_svg]:!text-[#989898]';

const filterCategories = computed(() => [
    ...props.categories.cost,
    ...props.categories.income,
]);

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

const openCreateForm = (type: TransactionType) => {
    dialogTransactionType.value = type;
    editingTransaction.value = null;
    isDialogOpen.value = true;
};

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

    applyFilters(value);
});

watch(
    () => props.filters,
    (filters) => {
        filterSearch.value = filters.search;
        filterCategory.value = filters.category?.toString() ?? 'all';
        filterFrom.value = filters.from;
        filterTo.value = filters.to;
    },
    { deep: true },
);

// No explicit "Filter" button in the design — category and date changes
// apply immediately; the search field applies on Enter.
watch([filterCategory, filterFrom, filterTo], () => applyFilters());

function applyFilters(
    currency: Currency = selectedCurrency.value,
    costPage: number | null = null,
    incomePage: number | null = null,
): void {
    router.get(
        transactionsIndex.url(),
        {
            search: filterSearch.value || null,
            category:
                filterCategory.value === 'all' ? null : filterCategory.value,
            from: filterFrom.value || null,
            to: filterTo.value || null,
            currency,
            cost_page: costPage && costPage > 1 ? costPage : null,
            income_page: incomePage && incomePage > 1 ? incomePage : null,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function changeCostPage(page: number): void {
    const ip = props.transactions.meta?.incomes?.current_page;
    applyFilters(selectedCurrency.value, page, ip && ip > 1 ? ip : null);
}

function changeIncomePage(page: number): void {
    const cp = props.transactions.meta?.costs?.current_page;
    applyFilters(selectedCurrency.value, cp && cp > 1 ? cp : null, page);
}

function displayDate(value: string): string {
    return formatAppDate(value, displayCalendar.value);
}
</script>
