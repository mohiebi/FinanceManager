<template>
    <Head title="Transactions" />

    <div
        class="finance-dark-page flex h-full min-h-[calc(100vh-92px)] flex-1 flex-col overflow-x-auto bg-[#2d2d2d] text-black"
    >
        <section class="mx-[18px] mt-5 rounded-[22px] bg-white p-5 shadow-sm">
            <div
                class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr_0.9fr_0.8fr_0.8fr_auto]"
            >
                <div class="grid gap-2">
                    <Label for="transaction_search">Search</Label>
                    <Input
                        id="transaction_search"
                        v-model="filterSearch"
                        :class="filterFieldClass"
                        placeholder="Title or note"
                        @keyup.enter="applyFilters()"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="transaction_type">Type</Label>
                    <Select
                        v-model="filterType"
                        @update:model-value="applyTypeFilter"
                    >
                        <SelectTrigger
                            id="transaction_type"
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
                    <Label for="transaction_category">Category</Label>
                    <Select
                        v-model="filterCategory"
                        @update:model-value="applyFilters()"
                    >
                        <SelectTrigger
                            id="transaction_category"
                            :class="filterFieldClass"
                        >
                            <SelectValue placeholder="All categories" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All categories</SelectItem>
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

                <div class="grid gap-2">
                    <Label for="transaction_from">From</Label>
                    <BirthdatePicker
                        v-model="filterFrom"
                        name="transaction_from"
                        :required="false"
                        :years-back="16"
                        :years-forward="1"
                        :trigger-class="filterFieldClass"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="transaction_to">To</Label>
                    <BirthdatePicker
                        v-model="filterTo"
                        name="transaction_to"
                        :required="false"
                        :years-back="16"
                        :years-forward="1"
                        :trigger-class="filterFieldClass"
                    />
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
                        @click="clearFilters"
                    >
                        <RotateCcw class="size-4" />
                        <span class="sr-only">Reset filters</span>
                    </Button>
                </div>
            </div>
        </section>

        <div
            class="grid items-start gap-[18px] px-[18px] py-[38px] lg:grid-cols-2"
        >
            <section class="overflow-hidden rounded-[22px] bg-white shadow-sm">
                <div
                    class="flex items-center justify-between gap-4 px-5 py-[29px]"
                >
                    <div>
                        <h2
                            class="text-[22px] leading-none font-normal text-black"
                        >
                            Money going out
                        </h2>
                    </div>
                    <Button
                        class="h-14 w-max justify-between rounded-md bg-[linear-gradient(90deg,#947BFF_0%,#6C4EE9_100%)] px-3.5 text-[22px] leading-none font-bold text-white shadow-[0_10px_20px_rgba(108,78,233,0.22)] transition hover:brightness-105"
                        @click="openCreateForm('cost')"
                    >
                        <span class="grid text-left">
                            <span class="col-start-1 row-start-1">
                                Add Cost
                            </span>
                            <span
                                class="invisible col-start-1 row-start-1"
                                aria-hidden="true"
                            >
                                Add Income
                            </span>
                        </span>
                        <span
                            class="grid h-[1.65em] w-[1.65em] min-w-[1.65em] shrink-0 place-items-center rounded-md border border-white/25 bg-[linear-gradient(135deg,rgba(255,255,255,0.24)_0%,rgba(45,45,45,0.72)_42%,rgba(45,45,45,0.96)_100%)] shadow-[inset_0_1px_0_rgba(255,255,255,0.22)]"
                        >
                            <Plus class="size-6" />
                        </span>
                    </Button>
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
                                    <button
                                        class="text-black"
                                        type="button"
                                        @click="openEditForm(transaction)"
                                    >
                                        {{ transaction.title }}
                                    </button>
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
                                    class="px-3 py-[17px] text-center text-[17px] leading-none font-normal text-black sm:px-5"
                                >
                                    {{
                                        formatAmount(transaction.display_amount)
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
                                    No costs yet. Add the first one when money
                                    leaves the building.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-[22px] bg-white shadow-sm">
                <div
                    class="flex items-center justify-between gap-4 px-5 py-[29px]"
                >
                    <div>
                        <h2
                            class="text-[22px] leading-none font-normal text-black"
                        >
                            Money coming in
                        </h2>
                    </div>
                    <Button
                        class="h-14 w-max justify-between rounded-md bg-[linear-gradient(90deg,#02CD86_0%,#00A96F_100%)] px-3.5 text-[22px] leading-none font-bold text-white shadow-[0_10px_20px_rgba(2,205,134,0.22)] transition hover:brightness-105"
                        @click="openCreateForm('income')"
                    >
                        <span class="grid text-left">
                            <span class="col-start-1 row-start-1">
                                Add Income
                            </span>
                            <span
                                class="invisible col-start-1 row-start-1"
                                aria-hidden="true"
                            >
                                Add Income
                            </span>
                        </span>
                        <span
                            class="grid h-[1.65em] w-[1.65em] min-w-[1.65em] shrink-0 place-items-center rounded-md border border-white/25 bg-[linear-gradient(135deg,rgba(255,255,255,0.24)_0%,rgba(45,45,45,0.72)_42%,rgba(45,45,45,0.96)_100%)] shadow-[inset_0_1px_0_rgba(255,255,255,0.22)]"
                        >
                            <Plus class="size-6" />
                        </span>
                    </Button>
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
                                v-for="transaction in props.transactions
                                    .incomes"
                                :key="transaction.id"
                                class="group"
                            >
                                <td
                                    class="px-3 py-[17px] text-center text-[17px] leading-none font-normal text-black sm:px-5"
                                >
                                    <button
                                        class="text-black"
                                        type="button"
                                        @click="openEditForm(transaction)"
                                    >
                                        {{ transaction.title }}
                                    </button>
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
                                    class="px-3 py-[17px] text-center text-[17px] leading-none font-normal text-black sm:px-5"
                                >
                                    {{
                                        formatAmount(transaction.display_amount)
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
                                    No incomes yet. Add salary, gifts, or
                                    freelance wins here.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <Dialog v-model:open="isDialogOpen">
            <DialogContent
                class="max-h-[calc(100vh-2rem)] overflow-y-auto rounded-[25px] border-0 bg-white p-0 text-[#2d2d2d] shadow-2xl sm:min-h-[654px] sm:max-w-[618px]"
                :show-close-button="false"
            >
                <form
                    class="px-6 pt-16 pb-12 sm:px-[100px] sm:pt-[83px]"
                    @submit.prevent="submitTransaction"
                >
                    <DialogHeader class="mb-7 space-y-2 text-left">
                        <DialogTitle
                            class="text-[20px] leading-normal font-medium text-[#2d2d2d]"
                        >
                            {{ dialogTitle }}
                        </DialogTitle>
                        <DialogDescription
                            class="max-w-[418px] text-[16px] leading-[18px] font-light text-[#2d2d2d]"
                        >
                            The same form handles both tables. The transaction
                            type follows the table action you selected.
                        </DialogDescription>
                    </DialogHeader>

                    <input type="hidden" name="type" :value="form.type" />

                    <div class="space-y-5">
                        <div class="grid gap-2">
                            <div class="grid gap-2 sm:grid-cols-[276px_134px]">
                                <Label class="finance-dialog-label" for="title">
                                    Subject
                                </Label>
                                <Label
                                    class="finance-dialog-label"
                                    for="category"
                                >
                                    Category
                                </Label>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-[276px_134px]">
                                <div>
                                    <Input
                                        id="title"
                                        v-model="form.title"
                                        :class="fieldControlClass"
                                        required
                                        placeholder="Hamburger, Fresh Restaurant"
                                    />
                                    <InputError :message="form.errors.title" />
                                </div>

                                <div>
                                    <select
                                        id="category"
                                        v-model="form.category_id"
                                        required
                                        class="finance-dialog-field"
                                        :class="fieldControlClass"
                                    >
                                        <option value="" disabled>
                                            Select
                                        </option>
                                        <option
                                            v-for="category in selectedCategories"
                                            :key="category.id"
                                            :value="category.id.toString()"
                                        >
                                            {{ category.name }}
                                        </option>
                                    </select>
                                    <InputError
                                        :message="form.errors.category_id"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label
                                class="finance-dialog-label"
                                for="occurred_at"
                            >
                                Date
                            </Label>
                            <input
                                id="occurred_at"
                                type="hidden"
                                :value="form.occurred_at"
                            />
                            <div
                                class="grid gap-2 sm:grid-cols-[134px_134px_134px]"
                            >
                                <Select v-model="selectedDateMonth" required>
                                    <SelectTrigger
                                        class="finance-dialog-field"
                                        :class="fieldControlClass"
                                    >
                                        <SelectValue placeholder="Month" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="month in months"
                                            :key="month.value"
                                            :value="month.value"
                                        >
                                            {{ month.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>

                                <Select v-model="selectedDateDay" required>
                                    <SelectTrigger
                                        class="finance-dialog-field"
                                        :class="fieldControlClass"
                                    >
                                        <SelectValue placeholder="Day" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="day in transactionDays"
                                            :key="day"
                                            :value="day"
                                        >
                                            {{ Number(day) }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>

                                <Select v-model="selectedDateYear" required>
                                    <SelectTrigger
                                        class="finance-dialog-field"
                                        :class="fieldControlClass"
                                    >
                                        <SelectValue placeholder="Year" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="year in transactionYears"
                                            :key="year"
                                            :value="year"
                                        >
                                            {{ year }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <InputError :message="form.errors.occurred_at" />
                        </div>

                        <div class="grid gap-2">
                            <div class="grid gap-2 sm:grid-cols-[276px_134px]">
                                <Label
                                    class="finance-dialog-label"
                                    for="amount"
                                >
                                    Amount
                                </Label>
                                <Label
                                    class="finance-dialog-label"
                                    for="currency"
                                >
                                    Currency
                                </Label>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-[276px_134px]">
                                <div>
                                    <Input
                                        id="amount"
                                        v-model="form.amount"
                                        :class="fieldControlClass"
                                        required
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        placeholder="000.000.000"
                                    />
                                    <InputError :message="form.errors.amount" />
                                </div>

                                <div>
                                    <select
                                        id="currency"
                                        v-model="form.currency"
                                        class="finance-dialog-field"
                                        :class="fieldControlClass"
                                    >
                                        <option
                                            v-for="currency in props.currencies"
                                            :key="currency.value"
                                            :value="currency.value"
                                        >
                                            {{ currency.label }}
                                        </option>
                                    </select>
                                    <InputError
                                        :message="form.errors.currency"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label
                                class="finance-dialog-label"
                                for="description"
                            >
                                Description
                            </Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="1"
                                class="finance-dialog-field min-h-9 resize-none"
                                :class="fieldControlClass"
                                placeholder="Optional note"
                            />
                            <InputError :message="form.errors.description" />
                        </div>
                    </div>

                    <div class="mt-7 flex justify-end gap-2">
                        <Button
                            type="button"
                            class="h-9 w-[99px] rounded-[8px] bg-[#effffa] px-[10px] py-[3px] text-[20px] font-normal text-[#2d2d2d] shadow-none hover:bg-[#e1fff5]"
                            @click="isDialogOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            class="h-9 w-[135px] rounded-[8px] bg-[#2d2d2d] px-[10px] py-[3px] text-[20px] font-normal text-white shadow-none hover:bg-[#1f1f1f]"
                            :disabled="
                                form.processing ||
                                selectedCategories.length === 0
                            "
                        >
                            <Spinner v-if="form.processing" />
                            Confirm
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>

<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, RotateCcw, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import BirthdatePicker from '@/components/BirthdatePicker.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { index as transactionsIndex } from '@/routes/transactions';

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
        search: string;
        type: FilterType;
        category: number | null;
        from: string;
        to: string;
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
                title: 'Transactions',
                href: transactionsIndex(),
            },
        ],
    },
});

const isDialogOpen = ref(false);
const editingTransactionId = ref<number | null>(null);
const selectedDateYear = ref('');
const selectedDateMonth = ref('');
const selectedDateDay = ref('');
const selectedCurrency = ref<Currency>(props.selectedCurrency);
const filterSearch = ref(props.filters.search);
const filterType = ref<FilterType>(props.filters.type);
const filterCategory = ref(props.filters.category?.toString() ?? 'all');
const filterFrom = ref(props.filters.from);
const filterTo = ref(props.filters.to);
const filterFieldClass =
    'h-9 w-full rounded-md !border-[#989898] !bg-[#f4f4f4] px-3 text-sm font-normal !text-[#2d2d2d] shadow-none [color-scheme:light] placeholder:!text-[#989898] focus-visible:!border-[#947BFF] focus-visible:!ring-2 focus-visible:!ring-[#947BFF]/25 dark:!border-[#989898] dark:!bg-[#f4f4f4] dark:!text-[#2d2d2d] dark:hover:!bg-[#eeeeee] [&_svg]:!text-[#2d2d2d]';

const today = () => new Date().toISOString().slice(0, 10);

const transactionYears = computed(() => {
    const currentYear = new Date().getFullYear();

    return Array.from({ length: 17 }, (_, index) =>
        String(currentYear + 1 - index),
    );
});

const months = [
    { value: '01', label: 'January' },
    { value: '02', label: 'February' },
    { value: '03', label: 'March' },
    { value: '04', label: 'April' },
    { value: '05', label: 'May' },
    { value: '06', label: 'June' },
    { value: '07', label: 'July' },
    { value: '08', label: 'August' },
    { value: '09', label: 'September' },
    { value: '10', label: 'October' },
    { value: '11', label: 'November' },
    { value: '12', label: 'December' },
];

const transactionDays = computed(() => {
    const year = Number(selectedDateYear.value || new Date().getFullYear());
    const month = Number(selectedDateMonth.value || 1);
    const daysInMonth = new Date(year, month, 0).getDate();

    return Array.from({ length: daysInMonth }, (_, index) =>
        String(index + 1).padStart(2, '0'),
    );
});

const form = useForm({
    type: 'cost' as TransactionType,
    category_id: '',
    amount: '',
    currency: 'toman' as Currency,
    title: '',
    description: '',
    occurred_at: today(),
});

const selectedCategories = computed(() => props.categories[form.type] ?? []);
const filterCategories = computed(() => {
    if (filterType.value === 'cost' || filterType.value === 'income') {
        return props.categories[filterType.value] ?? [];
    }

    return [...props.categories.cost, ...props.categories.income];
});
const isEditing = computed(() => editingTransactionId.value !== null);
const dialogTitle = computed(() =>
    isEditing.value
        ? `Edit ${form.type === 'cost' ? 'cost' : 'income'}`
        : `Add ${form.type === 'cost' ? 'cost' : 'income'}`,
);
const fieldControlClass = computed(() =>
    form.type === 'cost'
        ? 'finance-dialog-field finance-dialog-field-cost focus-visible:ring-[#947BFF]/30'
        : 'finance-dialog-field finance-dialog-field-income focus-visible:ring-[#02CD86]/25',
);

const resetForm = (type: TransactionType) => {
    const categories = props.categories[type] ?? [];

    form.clearErrors();
    form.reset();
    form.type = type;
    form.category_id = categories[0]?.id.toString() ?? '';
    form.amount = '';
    form.currency = 'toman';
    form.title = '';
    form.description = '';
    form.occurred_at = today();
    syncDatePicker(form.occurred_at);
};

const openCreateForm = (type: TransactionType) => {
    editingTransactionId.value = null;
    resetForm(type);
    isDialogOpen.value = true;
};

const openEditForm = (transaction: Transaction) => {
    editingTransactionId.value = transaction.id;
    form.clearErrors();
    form.type = transaction.type;
    form.category_id = transaction.category_id.toString();
    form.amount = transaction.amount;
    form.currency = transaction.currency;
    form.title = transaction.title;
    form.description = transaction.description ?? '';
    form.occurred_at = transaction.occurred_at;
    syncDatePicker(transaction.occurred_at);
    isDialogOpen.value = true;
};

const submitTransaction = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            isDialogOpen.value = false;
            editingTransactionId.value = null;
            resetForm(form.type);
        },
    };

    if (editingTransactionId.value) {
        form.patch(`/transactions/${editingTransactionId.value}`, options);

        return;
    }

    form.post('/transactions', options);
};

watch(
    [selectedDateYear, selectedDateMonth, selectedDateDay],
    updateOccurredAtFromPicker,
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

    applyFilters(value);
});

watch(
    () => props.filters,
    (filters) => {
        filterSearch.value = filters.search;
        filterType.value = filters.type;
        filterCategory.value = filters.category?.toString() ?? 'all';
        filterFrom.value = filters.from;
        filterTo.value = filters.to;
    },
    { deep: true },
);

watch(filterType, () => {
    if (
        filterCategory.value !== 'all' &&
        !filterCategories.value.some(
            (category) => category.id.toString() === filterCategory.value,
        )
    ) {
        filterCategory.value = 'all';
    }
});

watch(transactionDays, (availableDays) => {
    if (
        selectedDateDay.value &&
        !availableDays.includes(selectedDateDay.value)
    ) {
        selectedDateDay.value = availableDays.at(-1) ?? '';
    }
});

function applyFilters(currency: Currency = selectedCurrency.value): void {
    router.get(
        transactionsIndex.url(),
        {
            search: filterSearch.value || null,
            type: filterType.value === 'all' ? null : filterType.value,
            category:
                filterCategory.value === 'all' ? null : filterCategory.value,
            from: filterFrom.value || null,
            to: filterTo.value || null,
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
        filterCategory.value !== 'all' &&
        !filterCategories.value.some(
            (category) => category.id.toString() === filterCategory.value,
        )
    ) {
        filterCategory.value = 'all';
    }

    applyFilters();
}

function clearFilters(): void {
    filterSearch.value = '';
    filterType.value = 'all';
    filterCategory.value = 'all';
    filterFrom.value = '';
    filterTo.value = '';
    applyFilters();
}

function syncDatePicker(date: string): void {
    const [year, month, day] = date.split('-');

    selectedDateYear.value = year ?? '';
    selectedDateMonth.value = month ?? '';
    selectedDateDay.value = day ?? '';
}

function updateOccurredAtFromPicker(): void {
    if (
        !selectedDateYear.value ||
        !selectedDateMonth.value ||
        !selectedDateDay.value
    ) {
        return;
    }

    form.occurred_at = `${selectedDateYear.value}-${selectedDateMonth.value}-${selectedDateDay.value}`;
}

function formatAmount(amount: string | number): string {
    const number = Number(amount);

    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 2,
        minimumFractionDigits: number % 1 === 0 ? 0 : 2,
    }).format(number);
}

syncDatePicker(form.occurred_at);
</script>
