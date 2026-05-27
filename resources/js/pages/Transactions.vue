<template>
    <Head title="Transactions" />

    <div class="auth-theme flex min-h-screen bg-[#2d2d2d] text-black">
        <aside
            class="hidden w-[92px] shrink-0 flex-col items-center justify-between bg-[#353535] pt-28 pb-11 lg:flex"
        >
            <nav class="flex flex-col gap-4">
                <Link
                    :href="dashboard()"
                    class="grid size-9 place-items-center rounded-md bg-[#2d2d2d] text-white transition-colors hover:bg-[#424242]"
                    title="Dashboard"
                >
                    <LayoutGrid class="size-5" />
                </Link>
                <button
                    class="grid size-9 place-items-center rounded-md bg-[#2d2d2d] text-white transition-colors hover:bg-[#424242]"
                    title="Filters"
                    type="button"
                    @click="showFilters = !showFilters"
                >
                    <ListFilter class="size-5" />
                </button>
                <Link
                    :href="report()"
                    class="grid size-9 place-items-center rounded-md bg-[#2d2d2d] text-white transition-colors hover:bg-[#424242]"
                    title="Reports"
                >
                    <ChartPie class="size-5" />
                </Link>
                <button
                    class="grid size-9 place-items-center rounded-md bg-[#2d2d2d] text-white transition-colors hover:bg-[#424242]"
                    type="button"
                >
                    <Shapes class="size-5" />
                    <span class="sr-only">Categories</span>
                </button>
                <button
                    class="grid size-9 place-items-center rounded-md bg-[#2d2d2d] text-white transition-colors hover:bg-[#424242]"
                    type="button"
                >
                    <LogOut class="size-5" />
                    <span class="sr-only">Transfers</span>
                </button>
            </nav>

            <nav class="flex flex-col gap-4">
                <button
                    class="grid size-9 place-items-center rounded-md bg-[#2d2d2d] text-white transition-colors hover:bg-[#424242]"
                    type="button"
                >
                    <Download class="size-5" />
                    <span class="sr-only">Export</span>
                </button>
                <button
                    class="grid size-9 place-items-center rounded-md bg-[#2d2d2d] text-white transition-colors hover:bg-[#424242]"
                    type="button"
                >
                    <Settings class="size-5" />
                    <span class="sr-only">Settings</span>
                </button>
            </nav>
        </aside>

        <main class="flex min-w-0 flex-1 flex-col overflow-x-auto bg-[#2d2d2d]">
            <section
                class="flex h-[92px] shrink-0 items-center bg-[#454545] px-7 text-white shadow-sm"
            >
                <div
                    class="relative flex w-full flex-col gap-5 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div class="flex items-center gap-4">
                        <img :src="logoGreen" alt="" class="h-9 w-8 shrink-0" />
                        <div class="flex items-center gap-3 text-xl">
                            <span class="font-bold">Cashoilot</span>
                            <span class="text-white/55">|</span>
                            <span>Transaction</span>
                        </div>
                    </div>

                    <div class="hidden items-center gap-2 lg:flex">
                        <button
                            class="relative grid size-9 place-items-center rounded-md bg-[#2d2d2d] text-white"
                            type="button"
                        >
                            <Bell class="size-5" />
                            <span
                                class="absolute -top-2 -left-2 rounded-full bg-[#02cd86] px-1.5 py-0.5 text-[10px] leading-none font-bold text-[#2d2d2d]"
                            >
                                12
                            </span>
                            <span class="sr-only">Notifications</span>
                        </button>
                        <button
                            class="grid size-9 place-items-center rounded-md bg-[#2d2d2d] text-white"
                            type="button"
                        >
                            <User class="size-5" />
                            <span class="sr-only">Account</span>
                        </button>
                    </div>

                    <div
                        class="flex items-center gap-4 lg:absolute lg:left-1/2 lg:-translate-x-1/2"
                    >
                        <Label
                            for="display_currency"
                            class="text-[22px] font-normal text-white"
                        >
                            Currency
                        </Label>
                        <Select v-model="selectedCurrency">
                            <SelectTrigger
                                id="display_currency"
                                class="h-10 min-w-28 rounded-md border-0 bg-[#2d2d2d] px-5 text-base text-white shadow-none"
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
            </section>

            <section
                v-if="showFilters"
                class="mx-[18px] mt-5 rounded-[22px] bg-white p-5 shadow-sm"
            >
                <div
                    class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr_0.9fr_0.8fr_0.8fr_auto]"
                >
                    <div class="grid gap-2">
                        <Label for="transaction_search">Search</Label>
                        <Input
                            id="transaction_search"
                            v-model="filterSearch"
                            placeholder="Title or note"
                            @keyup.enter="applyFilters()"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="transaction_type">Type</Label>
                        <select
                            id="transaction_type"
                            v-model="filterType"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            @change="applyTypeFilter"
                        >
                            <option value="all">All types</option>
                            <option value="cost">Costs</option>
                            <option value="income">Incomes</option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="transaction_category">Category</Label>
                        <select
                            id="transaction_category"
                            v-model="filterCategory"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            @change="applyFilters()"
                        >
                            <option value="all">All categories</option>
                            <option
                                v-for="category in filterCategories"
                                :key="category.id"
                                :value="category.id.toString()"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="transaction_from">From</Label>
                        <BirthdatePicker
                            v-model="filterFrom"
                            name="transaction_from"
                            :required="false"
                            :years-back="16"
                            :years-forward="1"
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
                        />
                    </div>

                    <div class="flex items-end gap-2">
                        <Button class="rounded-full" @click="applyFilters()">
                            <Search class="size-4" />
                            Filter
                        </Button>
                        <Button
                            variant="outline"
                            class="rounded-full"
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
                <section
                    class="overflow-hidden rounded-[22px] bg-white shadow-sm"
                >
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
                            class="h-14 rounded-md bg-[linear-gradient(90deg,#947BFF_0%,#6C4EE9_100%)] px-3.5 text-[22px] leading-none font-bold text-white shadow-[0_10px_20px_rgba(108,78,233,0.22)] transition hover:brightness-105"
                            @click="openCreateForm('cost')"
                        >
                            Add Cost
                            <span
                                class="grid size-9 place-items-center rounded-md border border-white/25 bg-[linear-gradient(135deg,rgba(255,255,255,0.24)_0%,rgba(45,45,45,0.72)_42%,rgba(45,45,45,0.96)_100%)] shadow-[inset_0_1px_0_rgba(255,255,255,0.22)]"
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
                                        .costs"
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
                                    <td
                                        class="px-3 py-[17px] text-center sm:px-5"
                                    >
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
                                            formatAmount(
                                                transaction.display_amount,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="hidden px-3 py-[17px] text-center text-[17px] leading-none font-normal text-black sm:table-cell sm:px-5"
                                    >
                                        {{ transaction.occurred_at }}
                                    </td>
                                </tr>
                                <tr
                                    v-if="props.transactions.costs.length === 0"
                                >
                                    <td
                                        colspan="4"
                                        class="px-5 py-12 text-center text-neutral-500"
                                    >
                                        No costs yet. Add the first one when
                                        money leaves the building.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section
                    class="overflow-hidden rounded-[22px] bg-white shadow-sm"
                >
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
                            class="h-14 rounded-md bg-[linear-gradient(90deg,#02CD86_0%,#00A96F_100%)] px-3.5 text-[22px] leading-none font-bold text-white shadow-[0_10px_20px_rgba(2,205,134,0.22)] transition hover:brightness-105"
                            @click="openCreateForm('income')"
                        >
                            Add Income
                            <span
                                class="grid size-9 place-items-center rounded-md border border-white/25 bg-[linear-gradient(135deg,rgba(255,255,255,0.24)_0%,rgba(45,45,45,0.72)_42%,rgba(45,45,45,0.96)_100%)] shadow-[inset_0_1px_0_rgba(255,255,255,0.22)]"
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
                                    <td
                                        class="px-3 py-[17px] text-center sm:px-5"
                                    >
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
                                            formatAmount(
                                                transaction.display_amount,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="hidden px-3 py-[17px] text-center text-[17px] leading-none font-normal text-black sm:table-cell sm:px-5"
                                    >
                                        {{ transaction.occurred_at }}
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        props.transactions.incomes.length === 0
                                    "
                                >
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
                <DialogContent class="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>{{ dialogTitle }}</DialogTitle>
                        <DialogDescription>
                            The same form handles both tables. The transaction
                            type follows the table action you selected.
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        class="grid gap-4"
                        @submit.prevent="submitTransaction"
                    >
                        <input type="hidden" name="type" :value="form.type" />

                        <div class="grid gap-2">
                            <Label for="title">Title</Label>
                            <Input
                                id="title"
                                v-model="form.title"
                                required
                                placeholder="Groceries, salary, rent..."
                            />
                            <InputError :message="form.errors.title" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="amount">Amount</Label>
                                <Input
                                    id="amount"
                                    v-model="form.amount"
                                    required
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    placeholder="0.00"
                                />
                                <InputError :message="form.errors.amount" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="currency">Currency</Label>
                                <select
                                    id="currency"
                                    v-model="form.currency"
                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                >
                                    <option
                                        v-for="currency in props.currencies"
                                        :key="currency.value"
                                        :value="currency.value"
                                    >
                                        {{ currency.label }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.currency" />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="category">Category</Label>
                                <select
                                    id="category"
                                    v-model="form.category_id"
                                    required
                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                >
                                    <option value="" disabled>
                                        Select category
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

                            <div class="grid gap-2">
                                <Label for="occurred_at">Date</Label>
                                <BirthdatePicker
                                    v-model="form.occurred_at"
                                    name="occurred_at"
                                    :years-back="16"
                                    :years-forward="1"
                                />
                                <div class="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        class="rounded-full"
                                        @click="selectRelativeDate(0)"
                                    >
                                        Today
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        class="rounded-full"
                                        @click="selectRelativeDate(-1)"
                                    >
                                        Yesterday
                                    </Button>
                                </div>
                                <InputError
                                    :message="form.errors.occurred_at"
                                />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="description">Description</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs ring-offset-background transition-colors placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                placeholder="Optional note"
                            />
                            <InputError :message="form.errors.description" />
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                @click="isDialogOpen = false"
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                :disabled="
                                    form.processing ||
                                    selectedCategories.length === 0
                                "
                            >
                                <Spinner v-if="form.processing" />
                                {{
                                    isEditing
                                        ? 'Save changes'
                                        : 'Add transaction'
                                }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </main>
    </div>
</template>

<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Bell,
    ChartPie,
    Download,
    LayoutGrid,
    ListFilter,
    LogOut,
    Plus,
    RotateCcw,
    Search,
    Settings,
    Shapes,
    User,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import BirthdatePicker from '@/components/BirthdatePicker.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
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
import { dashboard, report } from '@/routes';
import { index as transactionsIndex } from '@/routes/transactions';
import logoGreen from '../../img/Logo-green.svg';

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
    layout: [],
});

const isDialogOpen = ref(false);
const editingTransactionId = ref<number | null>(null);
const showFilters = ref(false);
const selectedCurrency = ref<Currency>(props.selectedCurrency);
const filterSearch = ref(props.filters.search);
const filterType = ref<FilterType>(props.filters.type);
const filterCategory = ref(props.filters.category?.toString() ?? 'all');
const filterFrom = ref(props.filters.from);
const filterTo = ref(props.filters.to);

const today = () => new Date().toISOString().slice(0, 10);

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

const selectRelativeDate = (dayOffset: number) => {
    const date = new Date();

    date.setDate(date.getDate() + dayOffset);
    form.occurred_at = date.toISOString().slice(0, 10);
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

function formatAmount(amount: string | number): string {
    const number = Number(amount);

    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 2,
        minimumFractionDigits: number % 1 === 0 ? 0 : 2,
    }).format(number);
}
</script>
