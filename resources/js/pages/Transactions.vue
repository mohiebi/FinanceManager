<template>
    <Head :title="t('finance.transactions.title')" />

    <div
        class="finance-dense flex min-h-[calc(100vh-92px)] shrink-0 flex-col overflow-x-hidden bg-[#111111]"
    >
        <!-- ── Filter bar: one dense row of pills, matching the mock exactly
             — no field labels, no explicit Apply button. ─────────────────── -->
        <div
            class="mx-[18px] mt-5 flex flex-wrap items-center gap-2.5 rounded-[16px] bg-[#1a1a1a] p-3.5 ring-1 ring-white/10"
        >
            <Input
                id="transaction_search"
                v-model="filterSearch"
                :class="[filterFieldClass, 'min-w-[220px] flex-1']"
                :placeholder="t('finance.filters.title_or_note')"
                :aria-label="t('finance.fields.search')"
                @keyup.enter="applyFilters()"
            />

            <BirthdatePicker
                v-model="filterFrom"
                name="transaction_from"
                :required="false"
                :years-back="16"
                :years-forward="1"
                :trigger-class="filterFieldClass"
            />
            <BirthdatePicker
                v-model="filterTo"
                name="transaction_to"
                :required="false"
                :years-back="16"
                :years-forward="1"
                :trigger-class="filterFieldClass"
            />

            <Select v-model="filterCategory">
                <SelectTrigger
                    id="transaction_category"
                    :class="filterFieldClass"
                    :aria-label="t('finance.fields.category')"
                >
                    <SelectValue
                        :placeholder="t('finance.filters.all_categories')"
                    />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{{
                        t('finance.filters.all_categories')
                    }}</SelectItem>
                    <!-- Where the logbook's uncategorised count links to;
                         without the option the filter would be active but
                         invisible in the control. -->
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

            <a
                :href="`/transactions/export?currency=${selectedCurrency}`"
                class="inline-flex h-9 shrink-0 items-center rounded-[10px] bg-[#252525] px-[13px] text-[13px] whitespace-nowrap text-[#989898] ring-1 ring-white/[0.08] transition-colors hover:text-white"
            >
                {{ t('finance.actions.export_transactions') }}
            </a>
        </div>

        <div
            class="grid items-start gap-[18px] px-[18px] py-[18px] xl:grid-cols-2"
        >
            <!-- ── Money going out ──────────────────────────────────────── -->
            <section
                class="overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 pt-5 pb-2.5 ring-1 ring-white/10"
            >
                <div
                    class="mb-4 flex flex-wrap items-start justify-between gap-3.5"
                >
                    <div class="min-w-0">
                        <div class="mb-2 flex items-center gap-2">
                            <span
                                class="size-[7px] shrink-0 rounded-[2px] bg-[#6C4EE9]"
                            />
                            <p
                                class="text-[11px] font-medium tracking-[0.13em] text-[#947BFF] uppercase"
                            >
                                {{ t('finance.tables.money_going_out') }}
                            </p>
                        </div>
                        <p
                            class="text-[25px] leading-none font-semibold text-white"
                        >
                            <span
                                v-if="summaryCost !== null"
                                :class="maskClass"
                                >{{ summaryCost }}</span
                            >
                            <span v-else class="text-base text-[#686868]"
                                >—</span
                            >
                        </p>
                        <p class="mt-1.5 text-[11.5px] text-[#686868]">
                            {{ costTotal }}
                            {{ t('finance.reports.transactions') }} ·
                            {{ selectedCurrencyLabel }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 cursor-pointer rounded-[10px] bg-[#6C4EE9] px-3.5 py-2 text-[13px] font-medium text-white transition hover:brightness-110"
                        @click="openCreateForm('cost')"
                    >
                        + {{ t('finance.form.add_cost') }}
                    </button>
                </div>

                <div
                    class="grid grid-cols-[minmax(120px,1fr)_112px_92px_118px] items-center gap-3 pb-2.5 text-[10px] font-medium tracking-[0.1em] text-[#686868] uppercase"
                >
                    <div>{{ t('finance.fields.subject') }}</div>
                    <div>{{ t('finance.fields.category') }}</div>
                    <div>{{ t('finance.fields.date') }}</div>
                    <div class="text-end">{{ t('finance.fields.amount') }}</div>
                </div>

                <div
                    v-for="transaction in props.transactions.costs"
                    :key="transaction.id"
                    class="grid grid-cols-[minmax(120px,1fr)_112px_92px_118px] items-center gap-3 border-t border-white/[0.06] py-2.5 transition-colors hover:bg-white/[0.02]"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm text-white">
                            <Ciphered
                                :value="transaction.title"
                                table="transactions"
                            />
                        </p>
                        <p
                            v-if="transaction.description"
                            class="mt-0.5 truncate text-[11px] text-[#686868]"
                        >
                            <Ciphered
                                :value="transaction.description"
                                table="transactions"
                            />
                        </p>
                    </div>
                    <CategoryChip
                        :name="categoryName(transaction)"
                        :color="categoryColor(transaction)"
                    />
                    <span dir="ltr" class="text-xs text-[#686868] tabular-nums">
                        {{ displayDate(transaction.occurred_at) }}
                    </span>
                    <span
                        class="text-end text-[14.5px] text-[#947BFF] tabular-nums"
                        :class="maskClass"
                        dir="ltr"
                    >
                        <CipheredMoney
                            :amount="transaction.amount"
                            :display-amount="transaction.display_amount"
                            :currency="transaction.currency"
                            :display-currency="transaction.display_currency"
                            :rates="props.rates"
                        />
                    </span>
                </div>

                <p
                    v-if="props.transactions.costs.length === 0"
                    class="py-10 text-center text-sm text-[#989898]"
                >
                    {{ t('finance.dashboard.no_costs') }}
                </p>

                <!-- Cost table pagination -->
                <div
                    v-if="
                        props.transactions.meta?.costs &&
                        props.transactions.meta.costs.last_page > 1
                    "
                    class="flex items-center justify-center gap-3 py-4 text-xs text-[#686868]"
                >
                    <button
                        type="button"
                        :disabled="
                            props.transactions.meta.costs.current_page <= 1
                        "
                        class="cursor-pointer rounded-[7px] bg-[#252525] px-[11px] py-[5px] text-white transition-colors hover:bg-[#2e2e2e] disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeCostPage(
                                props.transactions.meta.costs.current_page - 1,
                            )
                        "
                    >
                        ‹
                    </button>
                    <span>
                        {{ props.transactions.meta.costs.current_page }} /
                        {{ props.transactions.meta.costs.last_page }} ·
                        {{ props.transactions.meta.costs.total }}
                        {{ t('finance.reports.transactions') }}
                    </span>
                    <button
                        type="button"
                        :disabled="
                            props.transactions.meta.costs.current_page >=
                            props.transactions.meta.costs.last_page
                        "
                        class="cursor-pointer rounded-[7px] bg-[#252525] px-[11px] py-[5px] text-white transition-colors hover:bg-[#2e2e2e] disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeCostPage(
                                props.transactions.meta.costs.current_page + 1,
                            )
                        "
                    >
                        ›
                    </button>
                </div>
            </section>

            <!-- ── Money coming in ──────────────────────────────────────── -->
            <section
                class="overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 pt-5 pb-2.5 ring-1 ring-white/10"
            >
                <div
                    class="mb-4 flex flex-wrap items-start justify-between gap-3.5"
                >
                    <div class="min-w-0">
                        <div class="mb-2 flex items-center gap-2">
                            <span
                                class="size-[7px] shrink-0 rounded-[2px] bg-[#02CD86]"
                            />
                            <p
                                class="text-[11px] font-medium tracking-[0.13em] text-[#02CD86] uppercase"
                            >
                                {{ t('finance.tables.money_coming_in') }}
                            </p>
                        </div>
                        <p
                            class="text-[25px] leading-none font-semibold text-white"
                        >
                            <span
                                v-if="summaryIncome !== null"
                                :class="maskClass"
                                >{{ summaryIncome }}</span
                            >
                            <span v-else class="text-base text-[#686868]"
                                >—</span
                            >
                        </p>
                        <p class="mt-1.5 text-[11.5px] text-[#686868]">
                            {{ incomeTotal }}
                            {{ t('finance.reports.transactions') }} ·
                            {{ selectedCurrencyLabel }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 cursor-pointer rounded-[10px] bg-[#02CD86] px-3.5 py-2 text-[13px] font-medium text-[#101010] transition hover:brightness-110"
                        @click="openCreateForm('income')"
                    >
                        + {{ t('finance.form.add_income') }}
                    </button>
                </div>

                <div
                    class="grid grid-cols-[minmax(120px,1fr)_112px_92px_118px] items-center gap-3 pb-2.5 text-[10px] font-medium tracking-[0.1em] text-[#686868] uppercase"
                >
                    <div>{{ t('finance.fields.subject') }}</div>
                    <div>{{ t('finance.fields.category') }}</div>
                    <div>{{ t('finance.fields.date') }}</div>
                    <div class="text-end">{{ t('finance.fields.amount') }}</div>
                </div>

                <div
                    v-for="transaction in props.transactions.incomes"
                    :key="transaction.id"
                    class="grid grid-cols-[minmax(120px,1fr)_112px_92px_118px] items-center gap-3 border-t border-white/[0.06] py-2.5 transition-colors hover:bg-white/[0.02]"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm text-white">
                            <Ciphered
                                :value="transaction.title"
                                table="transactions"
                            />
                        </p>
                        <p
                            v-if="transaction.description"
                            class="mt-0.5 truncate text-[11px] text-[#686868]"
                        >
                            <Ciphered
                                :value="transaction.description"
                                table="transactions"
                            />
                        </p>
                    </div>
                    <CategoryChip
                        :name="categoryName(transaction)"
                        :color="categoryColor(transaction)"
                    />
                    <span dir="ltr" class="text-xs text-[#686868] tabular-nums">
                        {{ displayDate(transaction.occurred_at) }}
                    </span>
                    <span
                        class="text-end text-[14.5px] text-[#02CD86] tabular-nums"
                        :class="maskClass"
                        dir="ltr"
                    >
                        <CipheredMoney
                            :amount="transaction.amount"
                            :display-amount="transaction.display_amount"
                            :currency="transaction.currency"
                            :display-currency="transaction.display_currency"
                            :rates="props.rates"
                        />
                    </span>
                </div>

                <p
                    v-if="props.transactions.incomes.length === 0"
                    class="py-10 text-center text-sm text-[#989898]"
                >
                    {{ t('finance.dashboard.no_incomes') }}
                </p>

                <!-- Income table pagination -->
                <div
                    v-if="
                        props.transactions.meta?.incomes &&
                        props.transactions.meta.incomes.last_page > 1
                    "
                    class="flex items-center justify-center gap-3 py-4 text-xs text-[#686868]"
                >
                    <button
                        type="button"
                        :disabled="
                            props.transactions.meta.incomes.current_page <= 1
                        "
                        class="cursor-pointer rounded-[7px] bg-[#252525] px-[11px] py-[5px] text-white transition-colors hover:bg-[#2e2e2e] disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeIncomePage(
                                props.transactions.meta.incomes.current_page -
                                    1,
                            )
                        "
                    >
                        ‹
                    </button>
                    <span>
                        {{ props.transactions.meta.incomes.current_page }} /
                        {{ props.transactions.meta.incomes.last_page }} ·
                        {{ props.transactions.meta.incomes.total }}
                        {{ t('finance.reports.transactions') }}
                    </span>
                    <button
                        type="button"
                        :disabled="
                            props.transactions.meta.incomes.current_page >=
                            props.transactions.meta.incomes.last_page
                        "
                        class="cursor-pointer rounded-[7px] bg-[#252525] px-[11px] py-[5px] text-white transition-colors hover:bg-[#2e2e2e] disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeIncomePage(
                                props.transactions.meta.incomes.current_page +
                                    1,
                            )
                        "
                    >
                        ›
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
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BirthdatePicker from '@/components/BirthdatePicker.vue';
import Ciphered from '@/components/Ciphered.vue';
import CipheredMoney from '@/components/CipheredMoney.vue';
import CategoryChip from '@/components/transactions/CategoryChip.vue';
import TransactionDialog from '@/components/transactions/TransactionDialog.vue';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAmountMask } from '@/composables/useAmountMask';
import { useDisplayAmounts } from '@/composables/useDisplayAmounts';
import { usePageSubtitle } from '@/composables/usePageSubtitle';
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
const { masked } = useAmountMask();
const maskClass = computed(() =>
    masked.value
        ? 'blur-[6px] transition-[filter] duration-150 select-none'
        : 'transition-[filter] duration-150',
);
const displayCalendar = computed(
    () => (page.props.calendar as string | undefined) ?? 'gregorian',
);

const selectedCurrency = ref<Currency>(props.selectedCurrency);
const selectedCurrencyLabel = computed(
    () =>
        props.currencies.find(
            (currency) => currency.value === selectedCurrency.value,
        )?.label ?? t(`finance.currencies.${selectedCurrency.value}`),
);
const filterSearch = ref(props.filters.search);
const filterCategory = ref(props.filters.category?.toString() ?? 'all');
const filterFrom = ref(props.filters.from);
const filterTo = ref(props.filters.to);
const filterFieldClass =
    'h-9 w-full rounded-[10px] !border-white/[0.08] !bg-[#252525] px-3 text-[13px] font-normal !text-[#e5e5e5] shadow-none [color-scheme:dark] placeholder:!text-[#686868] focus-visible:!border-[#947BFF] focus-visible:!ring-2 focus-visible:!ring-[#947BFF]/25 [&_svg]:!text-[#989898]';

const filterCategories = computed(() => [
    ...props.categories.cost,
    ...props.categories.income,
]);

// The mock's per-row category tag colours text on a fixed dark chip using
// each category's own colour — never a generic badge. Falls back to the
// prop lookup for rows whose relation wasn't eager-loaded.
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

const costTotal = computed(
    () =>
        props.transactions.meta?.costs?.total ??
        props.transactions.costs.length,
);
const incomeTotal = computed(
    () =>
        props.transactions.meta?.incomes?.total ??
        props.transactions.incomes.length,
);

// The server's `summary` is the true filtered total (every page, not just the
// one shown) and is used whenever it could read the amounts. Under the vault
// it can't, so `summary` arrives null — this page's own rows are decrypted
// here instead, but only stand in for the true total when there is exactly
// one page, since a page total presented as the grand total would be wrong
// the moment there is a second page.
const { totalOf } = useDisplayAmounts(
    () => [...props.transactions.costs, ...props.transactions.incomes],
    () => selectedCurrency.value,
    () => props.rates,
);

const costIsSinglePage = computed(
    () => (props.transactions.meta?.costs?.last_page ?? 1) <= 1,
);
const incomeIsSinglePage = computed(
    () => (props.transactions.meta?.incomes?.last_page ?? 1) <= 1,
);

const summaryCost = computed<string | null>(() => {
    if (props.summary) {
        return props.summary.cost;
    }

    if (!costIsSinglePage.value) {
        return null;
    }

    const total = totalOf(props.transactions.costs);

    return total === null ? null : formatAmount(total);
});

const summaryIncome = computed<string | null>(() => {
    if (props.summary) {
        return props.summary.income;
    }

    if (!incomeIsSinglePage.value) {
        return null;
    }

    const total = totalOf(props.transactions.incomes);

    return total === null ? null : formatAmount(total);
});

function formatAmount(amount: string | number): string {
    const numericAmount = Number(String(amount).replace(/,/g, ''));

    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 2,
        minimumFractionDigits: numericAmount % 1 === 0 ? 0 : 2,
    }).format(numericAmount);
}

usePageSubtitle(() =>
    t('finance.transactions.subtitle', {
        count: props.transactions.meta
            ? (props.transactions.meta.costs?.total ?? 0) +
              (props.transactions.meta.incomes?.total ?? 0)
            : (props.summary?.count ?? 0),
    }),
);

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
