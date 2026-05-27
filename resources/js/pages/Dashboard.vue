<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6">
        <section
            class="overflow-hidden rounded-3xl border border-neutral-200/70 bg-[radial-gradient(circle_at_top_left,_#f7f0dc,_transparent_32%),linear-gradient(135deg,_#fffefa,_#eef8f1)] p-6 shadow-sm dark:border-neutral-800 dark:bg-[radial-gradient(circle_at_top_left,_#332f22,_transparent_36%),linear-gradient(135deg,_#101211,_#172018)]"
        >
            <div
                class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
            >
                <div class="max-w-2xl space-y-3">
                    <p
                        class="text-xs font-semibold tracking-[0.35em] text-emerald-700 uppercase dark:text-emerald-300"
                    >
                        Finance command center
                    </p>
                    <div class="space-y-2">
                        <h1
                            class="text-3xl font-semibold tracking-tight text-neutral-950 sm:text-4xl dark:text-neutral-50"
                        >
                            Track every toman with a little less chaos.
                        </h1>
                        <p
                            class="text-sm text-neutral-600 dark:text-neutral-300"
                        >
                            Costs and incomes live side by side with one shared
                            display currency, so every amount stays easy to
                            compare.
                        </p>
                    </div>
                </div>

                <div class="flex flex-col gap-4 lg:min-w-lg">
                    <div class="flex flex-col gap-2 sm:max-w-xs lg:max-w-3xs">
                        <Label
                            for="display_currency"
                            class="text-xs font-semibold tracking-[0.2em] text-neutral-500 uppercase dark:text-neutral-400"
                        >
                            Display currency
                        </Label>
                        <Select v-model="selectedCurrency">
                            <SelectTrigger
                                id="display_currency"
                                class="w-full rounded-2xl border-white/70 bg-white/80 dark:border-white/10 dark:bg-white/10"
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

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div
                            class="rounded-2xl border border-white/70 bg-white/70 p-4 shadow-xs backdrop-blur dark:border-white/10 dark:bg-white/5"
                        >
                            <p
                                class="text-xs text-neutral-500 dark:text-neutral-400"
                            >
                                Income
                            </p>
                            <p
                                class="mt-2 text-sm font-semibold text-emerald-700 dark:text-emerald-300"
                            >
                                {{
                                    formatMoney(
                                        props.summary.income,
                                        props.selectedCurrency,
                                    )
                                }}
                            </p>
                        </div>
                        <div
                            class="rounded-2xl border border-white/70 bg-white/70 p-4 shadow-xs backdrop-blur dark:border-white/10 dark:bg-white/5"
                        >
                            <p
                                class="text-xs text-neutral-500 dark:text-neutral-400"
                            >
                                Costs
                            </p>
                            <p
                                class="mt-2 text-sm font-semibold text-rose-700 dark:text-rose-300"
                            >
                                {{
                                    formatMoney(
                                        props.summary.cost,
                                        props.selectedCurrency,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

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
                    <Button
                        class="rounded-full"
                        size="sm"
                        @click="openCreateForm('cost')"
                    >
                        <Plus class="size-4" />
                        Add cost
                    </Button>
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
                                <th
                                    class="px-3 py-3 text-right font-medium sm:px-5"
                                >
                                    Actions
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
                                        formatAmount(transaction.display_amount)
                                    }}
                                </td>
                                <td class="px-3 py-4 sm:px-5">
                                    <div
                                        class="flex justify-end gap-1 sm:gap-2"
                                    >
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 w-7 rounded-full p-0 sm:h-9 sm:w-9"
                                            @click="openEditForm(transaction)"
                                        >
                                            <Pencil class="size-3 sm:size-4" />
                                            <span class="sr-only">Edit</span>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 w-7 rounded-full p-0 text-rose-700 hover:text-rose-800 sm:h-9 sm:w-9 dark:text-rose-300"
                                            @click="
                                                deleteTransaction(transaction)
                                            "
                                        >
                                            <Trash2 class="size-3 sm:size-4" />
                                            <span class="sr-only">Delete</span>
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="props.transactions.costs.length === 0">
                                <td
                                    colspan="5"
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
                    <Button
                        class="rounded-full bg-emerald-700 hover:bg-emerald-800"
                        size="sm"
                        @click="openCreateForm('income')"
                    >
                        <Plus class="size-4" />
                        Add income
                    </Button>
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
                                <th
                                    class="px-3 py-3 text-right font-medium sm:px-5"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="transaction in props.transactions
                                    .incomes"
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
                                        formatAmount(transaction.display_amount)
                                    }}
                                </td>
                                <td class="px-3 py-4 sm:px-5">
                                    <div
                                        class="flex justify-end gap-1 sm:gap-2"
                                    >
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 w-7 rounded-full p-0 sm:h-9 sm:w-9"
                                            @click="openEditForm(transaction)"
                                        >
                                            <Pencil class="size-3 sm:size-4" />
                                            <span class="sr-only">Edit</span>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 w-7 rounded-full p-0 text-rose-700 hover:text-rose-800 sm:h-9 sm:w-9 dark:text-rose-300"
                                            @click="
                                                deleteTransaction(transaction)
                                            "
                                        >
                                            <Trash2 class="size-3 sm:size-4" />
                                            <span class="sr-only">Delete</span>
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="props.transactions.incomes.length === 0">
                                <td
                                    colspan="5"
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
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
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
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
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

const deleteTransaction = (transaction: Transaction) => {
    if (!window.confirm(`Delete "${transaction.title}"?`)) {
        return;
    }

    router.delete(`/transactions/${transaction.id}`, {
        preserveScroll: true,
    });
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

    router.get(
        dashboard.url(),
        { currency: value },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
});

watch(transactionDays, (availableDays) => {
    if (
        selectedDateDay.value &&
        !availableDays.includes(selectedDateDay.value)
    ) {
        selectedDateDay.value = availableDays.at(-1) ?? '';
    }
});

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

function formatMoney(amount: string | number, currency: Currency): string {
    const value = formatAmount(amount);

    return `${value} ${currency.toUpperCase()}`;
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
