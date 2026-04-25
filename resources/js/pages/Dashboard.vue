<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
const isEditing = computed(() => editingTransactionId.value !== null);
const dialogTitle = computed(() =>
    isEditing.value
        ? `Edit ${form.type === 'cost' ? 'cost' : 'income'}`
        : `Add ${form.type === 'cost' ? 'cost' : 'income'}`,
);

const costTotals = computed(() => totalsByCurrency(props.transactions.costs));
const incomeTotals = computed(() =>
    totalsByCurrency(props.transactions.incomes),
);
const balanceTotals = computed(() => {
    const totals = emptyCurrencyTotals();

    props.currencies.forEach((currency) => {
        totals[currency.value] =
            incomeTotals.value[currency.value] -
            costTotals.value[currency.value];
    });

    return totals;
});

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

const deleteTransaction = (transaction: Transaction) => {
    if (!window.confirm(`Delete "${transaction.title}"?`)) {
        return;
    }

    router.delete(`/transactions/${transaction.id}`, {
        preserveScroll: true,
    });
};

function emptyCurrencyTotals(): Record<Currency, number> {
    return {
        toman: 0,
        usd: 0,
        eur: 0,
    };
}

function totalsByCurrency(
    transactions: Transaction[],
): Record<Currency, number> {
    return transactions.reduce((totals, transaction) => {
        totals[transaction.currency] += Number(transaction.amount);

        return totals;
    }, emptyCurrencyTotals());
}

function formatMoney(amount: string | number, currency: Currency): string {
    const number = Number(amount);
    const value = new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 2,
        minimumFractionDigits: number % 1 === 0 ? 0 : 2,
    }).format(number);

    return `${value} ${currency.toUpperCase()}`;
}
</script>

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
                            Costs and incomes live side by side, each with quick
                            actions for adding, editing, and cleaning up
                            records.
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3 lg:min-w-xl">
                    <div
                        class="rounded-2xl border border-white/70 bg-white/70 p-4 shadow-xs backdrop-blur dark:border-white/10 dark:bg-white/5"
                    >
                        <p
                            class="text-xs text-neutral-500 dark:text-neutral-400"
                        >
                            Income
                        </p>
                        <div class="mt-2 space-y-1">
                            <p
                                v-for="currency in props.currencies"
                                :key="currency.value"
                                class="text-sm font-semibold text-emerald-700 dark:text-emerald-300"
                            >
                                {{
                                    formatMoney(
                                        incomeTotals[currency.value],
                                        currency.value,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                    <div
                        class="rounded-2xl border border-white/70 bg-white/70 p-4 shadow-xs backdrop-blur dark:border-white/10 dark:bg-white/5"
                    >
                        <p
                            class="text-xs text-neutral-500 dark:text-neutral-400"
                        >
                            Costs
                        </p>
                        <div class="mt-2 space-y-1">
                            <p
                                v-for="currency in props.currencies"
                                :key="currency.value"
                                class="text-sm font-semibold text-rose-700 dark:text-rose-300"
                            >
                                {{
                                    formatMoney(
                                        costTotals[currency.value],
                                        currency.value,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                    <div
                        class="rounded-2xl border border-white/70 bg-white/70 p-4 shadow-xs backdrop-blur dark:border-white/10 dark:bg-white/5"
                    >
                        <p
                            class="text-xs text-neutral-500 dark:text-neutral-400"
                        >
                            Balance
                        </p>
                        <div class="mt-2 space-y-1">
                            <p
                                v-for="currency in props.currencies"
                                :key="currency.value"
                                class="text-sm font-semibold"
                                :class="
                                    balanceTotals[currency.value] >= 0
                                        ? 'text-slate-900 dark:text-slate-100'
                                        : 'text-amber-700 dark:text-amber-300'
                                "
                            >
                                {{
                                    formatMoney(
                                        balanceTotals[currency.value],
                                        currency.value,
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
                                <th class="px-3 py-3 font-medium sm:px-5">Title</th>
                                <th class="px-3 py-3 font-medium sm:px-5">Category</th>
                                <th class="hidden px-3 py-3 font-medium sm:table-cell sm:px-5">Date</th>
                                <th class="px-3 py-3 text-right font-medium sm:px-5">
                                    Amount
                                </th>
                                <th class="px-3 py-3 text-right font-medium sm:px-5">
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
                                    <div class="font-medium text-xs sm:text-sm">
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
                                    class="px-3 py-4 text-right font-semibold text-xs text-rose-700 sm:px-5 sm:text-sm dark:text-rose-300"
                                >
                                    {{
                                        formatMoney(
                                            transaction.amount,
                                            transaction.currency,
                                        )
                                    }}
                                </td>
                                <td class="px-3 py-4 sm:px-5">
                                    <div class="flex justify-end gap-1 sm:gap-2">
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
                                <th class="px-3 py-3 font-medium sm:px-5">Title</th>
                                <th class="px-3 py-3 font-medium sm:px-5">Category</th>
                                <th class="hidden px-3 py-3 font-medium sm:table-cell sm:px-5">Date</th>
                                <th class="px-3 py-3 text-right font-medium sm:px-5">
                                    Amount
                                </th>
                                <th class="px-3 py-3 text-right font-medium sm:px-5">
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
                                    <div class="font-medium text-xs sm:text-sm">
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
                                    class="px-3 py-4 text-right font-semibold text-xs text-emerald-700 sm:px-5 sm:text-sm dark:text-emerald-300"
                                >
                                    {{
                                        formatMoney(
                                            transaction.amount,
                                            transaction.currency,
                                        )
                                    }}
                                </td>
                                <td class="px-3 py-4 sm:px-5">
                                    <div class="flex justify-end gap-1 sm:gap-2">
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
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{ dialogTitle }}</DialogTitle>
                    <DialogDescription>
                        The same form handles both tables. The transaction type
                        follows the table action you selected.
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-4" @submit.prevent="submitTransaction">
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
                            <InputError :message="form.errors.category_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="occurred_at">Date</Label>
                            <Input
                                id="occurred_at"
                                v-model="form.occurred_at"
                                required
                                type="date"
                            />
                            <InputError :message="form.errors.occurred_at" />
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
                            {{ isEditing ? 'Save changes' : 'Add transaction' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
