<template>
    <Head :title="t('finance.bills.title')" />

    <div
        class="flex h-full min-h-[calc(100vh-92px)] flex-1 flex-col overflow-x-auto bg-[#111111]"
    >
        <!-- ── Header ─────────────────────────────────────────────── -->
        <section
            class="mx-[18px] mt-5 rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
        >
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1
                        class="text-2xl font-semibold tracking-tight text-white"
                    >
                        {{ t('finance.bills.title') }}
                    </h1>
                    <p class="mt-1 max-w-lg text-sm text-[#989898]">
                        {{ t('finance.bills.description') }}
                    </p>
                </div>
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-end"
                >
                    <div
                        class="flex flex-col items-start gap-0.5 rounded-2xl bg-white/5 px-4 py-2.5 ring-1 ring-white/10 sm:items-end"
                    >
                        <p
                            class="text-[10px] font-medium tracking-widest text-[#6b6b6b] uppercase"
                        >
                            {{ t('finance.fields.total_cost') }} · {{ t('finance.reports.this_month') }}
                        </p>
                        <p class="text-2xl font-bold leading-none text-white">
                            {{ formatAmount(props.monthlyBillSummary.amount) }}
                            <span class="ml-1 text-xs font-normal text-[#989898]">{{
                                currencyLabel(props.monthlyBillSummary.currency)
                            }}</span>
                        </p>
                    </div>
                    <Button
                        class="h-11 w-max shrink-0 rounded-full bg-[linear-gradient(90deg,#02CD86_0%,#00a36e_100%)] px-5 text-[#101010] shadow-[0_10px_20px_rgba(2,205,134,0.22)] hover:brightness-105"
                        @click="openCreateDialog()"
                    >
                        <Plus class="size-4" />
                        {{ t('finance.bills.add_bill') }}
                    </Button>
                </div>
            </div>
        </section>

        <!-- ── Empty state ────────────────────────────────────────── -->
        <div
            v-if="bills.length === 0"
            class="mx-[18px] my-[18px] flex flex-col items-center justify-center rounded-[22px] bg-[#1a1a1a] px-8 py-20 ring-1 ring-white/10"
        >
            <span
                class="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#24212f]"
            >
                <Receipt class="size-8 text-[#6C4EE9]" />
            </span>
            <h2 class="mt-4 text-xl font-semibold text-white">
                {{ t('finance.bills.empty') }}
            </h2>
            <p class="mt-2 max-w-sm text-center text-sm text-[#989898]">
                {{ t('finance.bills.empty_description') }}
            </p>
        </div>

        <!-- ── Bill cards ─────────────────────────────────────────── -->
        <div
            v-else
            class="mx-[18px] my-[18px] grid grid-cols-1 gap-[18px] sm:grid-cols-2 xl:grid-cols-3"
        >
            <div
                v-for="bill in bills"
                :key="bill.id"
                class="flex flex-col gap-4 overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate text-base font-semibold text-white">
                            {{ bill.title }}
                        </p>
                        <p
                            v-if="bill.category_name"
                            class="mt-0.5 truncate text-xs text-[#989898]"
                        >
                            {{ bill.category_name }}
                        </p>
                    </div>
                    <div
                        class="flex shrink-0 items-center gap-1.5 opacity-0 transition group-hover:opacity-100 sm:opacity-100"
                    >
                        <button
                            type="button"
                            class="rounded-md bg-white/5 px-2 py-1 text-xs text-[#6C4EE9] ring-1 ring-white/10 hover:bg-white/10"
                            @click="openEditDialog(bill)"
                        >
                            {{ t('common.edit') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-md p-1.5 hover:bg-[#fff0f0]"
                            @click="requestDelete(bill)"
                        >
                            <Trash2 class="size-3.5 text-[#E94E50]" />
                        </button>
                    </div>
                </div>

                <p class="text-2xl font-bold text-white">
                    {{ formatAmount(bill.display_amount) }}
                    <span class="text-sm font-normal text-[#989898]">{{
                        currencyLabel(bill.display_currency)
                    }}</span>
                </p>

                <div class="flex items-center gap-2 text-xs text-[#989898]">
                    <CalendarClock class="size-3.5 shrink-0" />
                    <span v-if="bill.recurrence_type === 'monthly'">
                        {{ t('finance.bills.recurrence_monthly') }} —
                        {{
                            t('finance.bills.due_day_label', {
                                day: bill.due_day_of_month,
                            })
                        }}
                    </span>
                    <span v-else>{{
                        t('finance.bills.recurrence_one_time')
                    }}</span>
                </div>

                <div
                    class="mt-auto flex items-center justify-between gap-3 border-t border-white/10 pt-4"
                >
                    <div>
                        <p
                            class="text-[10px] font-medium tracking-wide text-[#6b6b6b] uppercase"
                        >
                            {{ t('finance.bills.next_due') }}
                        </p>
                        <p class="mt-0.5 text-sm font-medium text-white">
                            {{
                                bill.next_occurrence
                                    ? displayDate(bill.next_occurrence.due_date)
                                    : t('finance.bills.no_upcoming')
                            }}
                        </p>
                    </div>
                    <button
                        v-if="bill.next_occurrence"
                        type="button"
                        :disabled="payingId === bill.next_occurrence.id"
                        class="shrink-0 cursor-pointer rounded-full bg-[#02CD86]/10 px-3 py-1.5 text-xs font-medium text-[#02CD86] ring-1 ring-[#02CD86]/25 transition-colors hover:bg-[#02CD86]/20 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="markPaid(bill)"
                    >
                        {{ t('finance.bills.mark_paid') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Upcoming occurrences timeline ────────────────────── -->
        <section
            v-if="upcomingOccurrences.length > 0"
            class="mx-[18px] mb-[18px] rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
        >
            <h2
                class="mb-4 text-[10px] font-medium tracking-wide text-[#6b6b6b] uppercase"
            >
                {{ t('finance.bills.upcoming') }}
            </h2>
            <div
                v-for="group in groupedUpcoming"
                :key="group.month"
                class="mb-5 last:mb-0"
            >
                <p class="mb-2 text-xs font-medium text-[#6b6b6b]">
                    {{ group.label }}
                </p>
                <div class="space-y-2">
                    <div
                        v-for="occ in group.occurrences"
                        :key="occ.occurrence_id"
                        class="flex items-center justify-between gap-3 rounded-xl bg-white/5 px-4 py-3 ring-1 ring-white/5"
                    >
                        <p class="min-w-0 flex-1 truncate text-sm font-medium text-white">
                            {{ occ.title }}
                        </p>
                        <div class="flex shrink-0 items-center gap-2">
                            <span
                                v-if="occ.is_overdue"
                                class="rounded-md bg-[#2f1717] px-2 py-0.5 text-[10px] font-medium text-[#E94E50]"
                            >{{ t('finance.bills.overdue') }}</span>
                            <span
                                v-else-if="occ.is_due_today"
                                class="rounded-md bg-[#0d2620] px-2 py-0.5 text-[10px] font-medium text-[#02CD86]"
                            >{{ t('finance.bills.due_today') }}</span>
                            <span
                                v-else
                                class="text-xs text-[#989898]"
                            >{{ displayDate(occ.due_date) }}</span>
                            <span class="text-sm font-semibold text-white">
                                {{ formatAmount(occ.display_amount) }}
                                <span class="text-xs font-normal text-[#989898]">{{
                                    currencyLabel(occ.display_currency)
                                }}</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Add / Edit dialog ──────────────────────────────────── -->
        <Dialog :open="isDialogOpen" @update:open="handleDialogOpenChange">
            <DialogContent
                class="max-h-[calc(100dvh-1rem)] overflow-hidden rounded-[20px] border-0 bg-[#1a1a1a] p-0 text-white shadow-2xl ring-1 ring-white/10 sm:max-h-[calc(100vh-2rem)] sm:max-w-[480px] sm:rounded-[25px]"
                :show-close-button="false"
            >
                <form
                    class="flex max-h-[calc(100dvh-1rem)] flex-col sm:max-h-[calc(100vh-2rem)]"
                    @submit.prevent="submitBill"
                >
                    <div
                        class="flex-1 overflow-y-auto px-6 pt-10 pb-5 sm:px-10 sm:pt-12 sm:pb-6"
                    >
                        <DialogHeader class="mb-6 space-y-2 text-left">
                            <DialogTitle
                                class="text-[20px] leading-normal font-medium text-white"
                            >
                                {{
                                    editingId !== null
                                        ? t('finance.bills.edit_bill')
                                        : t('finance.bills.add_bill')
                                }}
                            </DialogTitle>
                        </DialogHeader>

                        <div class="space-y-5">
                            <div class="grid gap-2">
                                <Label
                                    class="finance-dialog-label"
                                    for="bill-title"
                                    >{{ t('finance.fields.subject') }}</Label
                                >
                                <Input
                                    id="bill-title"
                                    v-model="form.title"
                                    :class="fieldClass"
                                    autocomplete="off"
                                />
                                <InputError :message="form.errors.title" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="grid gap-2">
                                    <Label
                                        class="finance-dialog-label"
                                        for="bill-amount"
                                        >{{ t('finance.fields.amount') }}</Label
                                    >
                                    <Input
                                        id="bill-amount"
                                        v-model="form.amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :class="fieldClass"
                                    />
                                    <InputError :message="form.errors.amount" />
                                </div>
                                <div class="grid gap-2">
                                    <Label
                                        class="finance-dialog-label"
                                        for="bill-currency"
                                        >{{
                                            t('finance.fields.currency')
                                        }}</Label
                                    >
                                    <Select v-model="form.currency">
                                        <SelectTrigger
                                            id="bill-currency"
                                            :class="fieldClass"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent
                                            class="finance-dialog-select-content"
                                        >
                                            <SelectItem
                                                v-for="c in props.currencies"
                                                :key="c.value"
                                                :value="c.value"
                                            >
                                                {{ c.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        :message="form.errors.currency"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label
                                    class="finance-dialog-label"
                                    for="bill-category"
                                    >{{ t('finance.fields.category') }}</Label
                                >
                                <Select v-model="categoryModel">
                                    <SelectTrigger
                                        id="bill-category"
                                        :class="fieldClass"
                                    >
                                        <SelectValue
                                            :placeholder="
                                                t(
                                                    'finance.categories.uncategorized',
                                                )
                                            "
                                        />
                                    </SelectTrigger>
                                    <SelectContent
                                        class="finance-dialog-select-content"
                                    >
                                        <SelectItem
                                            v-for="c in props.categories"
                                            :key="c.id"
                                            :value="String(c.id)"
                                        >
                                            {{ c.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="form.errors.category_id"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label
                                    class="finance-dialog-label"
                                    for="bill-recurrence"
                                    >{{ t('finance.bills.recurrence') }}</Label
                                >
                                <Select v-model="form.recurrence_type">
                                    <SelectTrigger
                                        id="bill-recurrence"
                                        :class="fieldClass"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent
                                        class="finance-dialog-select-content"
                                    >
                                        <SelectItem value="monthly">{{
                                            t(
                                                'finance.bills.recurrence_monthly',
                                            )
                                        }}</SelectItem>
                                        <SelectItem value="one_time">{{
                                            t(
                                                'finance.bills.recurrence_one_time',
                                            )
                                        }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="form.errors.recurrence_type"
                                />
                            </div>

                            <div
                                v-if="form.recurrence_type === 'monthly'"
                                class="grid gap-2"
                            >
                                <Label
                                    class="finance-dialog-label"
                                    for="bill-due-day"
                                    >{{
                                        t('finance.bills.due_day_of_month')
                                    }}</Label
                                >
                                <Input
                                    id="bill-due-day"
                                    v-model="form.due_day_of_month"
                                    type="number"
                                    min="1"
                                    max="31"
                                    :class="fieldClass"
                                />
                                <InputError
                                    :message="form.errors.due_day_of_month"
                                />
                            </div>
                            <div v-else class="grid gap-2">
                                <Label
                                    class="finance-dialog-label"
                                    for="bill-due-date"
                                    >{{ t('finance.bills.due_date') }}</Label
                                >
                                <Input
                                    id="bill-due-date"
                                    v-model="form.due_date"
                                    type="date"
                                    :class="fieldClass"
                                />
                                <InputError :message="form.errors.due_date" />
                            </div>

                            <label
                                class="flex cursor-pointer items-center gap-2.5"
                            >
                                <Checkbox
                                    :checked="form.telegram_reminder_enabled"
                                    @update:checked="setTelegramReminder"
                                />
                                <span class="text-sm text-white/85">{{
                                    t('finance.bills.telegram_reminder')
                                }}</span>
                            </label>
                        </div>
                    </div>

                    <div
                        class="flex items-center gap-3 border-t border-white/10 px-6 py-4 sm:px-10"
                    >
                        <Button
                            type="button"
                            class="h-11 flex-1 rounded-xl bg-white/5 text-white/70 shadow-none ring-1 ring-white/10 hover:bg-white/10 hover:text-white"
                            @click="closeDialog"
                        >
                            {{ t('common.cancel') }}
                        </Button>
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="h-11 flex-1 rounded-xl bg-[#02CD86] text-[#101010] shadow-none hover:bg-[#08dd93]"
                        >
                            <Spinner v-if="form.processing" />
                            {{ t('common.save') }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ── Delete confirm ─────────────────────────────────────── -->
        <ConfirmDeleteModal
            :open="deleteTarget !== null"
            :title="
                deleteTarget
                    ? t('finance.delete.bill_title', {
                          title: deleteTarget.title,
                      })
                    : undefined
            "
            :description="t('finance.delete.bill_description')"
            :processing="deleteForm.processing"
            @update:open="(value) => !value && (deleteTarget = null)"
            @confirm="confirmDelete"
        />
    </div>
</template>

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { CalendarClock, Plus, Receipt, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
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
import { formatAppDate } from '@/lib/date';
import { dashboard } from '@/routes';
import {
    index as billsIndex,
    destroy as destroyBill,
    store as storeBill,
    update as updateBill,
} from '@/routes/bills';
import { pay as payBill } from '@/routes/bills/occurrences';

type BillOccurrence = { id: number; due_date: string };
type Bill = {
    id: number;
    title: string;
    amount: number;
    currency: string;
    display_amount: string;
    display_currency: string;
    recurrence_type: 'one_time' | 'monthly';
    due_day_of_month: number | null;
    due_date: string | null;
    telegram_reminder_enabled: boolean;
    is_active: boolean;
    category_id: number | null;
    category_name: string | null;
    next_occurrence: BillOccurrence | null;
};
type MonthlyBillSummary = {
    amount: string;
    currency: string;
    count: number;
    from: string;
    to: string;
};

type UpcomingOccurrence = {
    occurrence_id: number;
    bill_id: number;
    title: string;
    display_amount: string;
    display_currency: string;
    due_date: string;
    is_overdue: boolean;
    is_due_today: boolean;
};

const props = defineProps<{
    bills: Bill[];
    categories: { id: number; name: string }[];
    currencies: { label: string; value: string }[];
    selectedCurrency: string;
    monthlyBillSummary: MonthlyBillSummary;
    upcomingOccurrences: UpcomingOccurrence[];
    userCalendar: string;
}>();

const { t } = useI18n();
const bills = computed(() => props.bills);

const groupedUpcoming = computed(() => {
    const groups = new Map<
        string,
        { month: string; label: string; occurrences: UpcomingOccurrence[] }
    >();
    for (const occ of props.upcomingOccurrences) {
        const [y, m] = occ.due_date.split('-').map(Number);
        const key = `${y}-${String(m).padStart(2, '0')}`;
        if (!groups.has(key)) {
            const label = new Date(y, m - 1, 1).toLocaleString('default', {
                month: 'long',
                year: 'numeric',
            });
            groups.set(key, { month: key, label, occurrences: [] });
        }
        groups.get(key)!.occurrences.push(occ);
    }
    return [...groups.values()].slice(0, 2);
});

const displayDate = (value: string): string =>
    formatAppDate(value, props.userCalendar);
const formatAmount = (value: number | string): string => {
    const amount = Number(value);

    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 2,
        minimumFractionDigits: amount % 1 === 0 ? 0 : 2,
    }).format(amount);
};
const currencyLabel = (value: string): string =>
    props.currencies.find((currency) => currency.value === value)?.label ??
    t(`finance.currencies.${value}`);

const fieldClass =
    'finance-dialog-field finance-dialog-field-income focus-visible:ring-[#02CD86]/25';

const isDialogOpen = ref(false);
const editingId = ref<number | null>(null);

const form = useForm({
    title: '',
    amount: '',
    currency: 'toman',
    category_id: '',
    recurrence_type: 'monthly' as 'one_time' | 'monthly',
    due_day_of_month: '1',
    due_date: '',
    telegram_reminder_enabled: true,
});

type CheckboxState = boolean | 'indeterminate';

const categoryModel = computed({
    get: () => (form.category_id ? String(form.category_id) : undefined),
    set: (value: string | undefined) => {
        form.category_id = value ?? '';
    },
});

function openCreateDialog(): void {
    editingId.value = null;
    form.clearErrors();
    form.reset();
    form.currency = props.currencies[0]?.value ?? 'toman';
    form.recurrence_type = 'monthly';
    form.due_day_of_month = '1';
    isDialogOpen.value = true;
}

function openEditDialog(bill: Bill): void {
    editingId.value = bill.id;
    form.clearErrors();
    form.title = bill.title;
    form.amount = String(bill.amount);
    form.currency = bill.currency;
    form.category_id = bill.category_id ? String(bill.category_id) : '';
    form.recurrence_type = bill.recurrence_type;
    form.due_day_of_month = bill.due_day_of_month
        ? String(bill.due_day_of_month)
        : '1';
    form.due_date = bill.due_date ?? '';
    form.telegram_reminder_enabled = Boolean(bill.telegram_reminder_enabled);
    isDialogOpen.value = true;
}

function setTelegramReminder(value: CheckboxState): void {
    form.telegram_reminder_enabled = value === true;
}

function closeDialog(): void {
    isDialogOpen.value = false;
    editingId.value = null;
    form.clearErrors();
}

function handleDialogOpenChange(value: boolean): void {
    if (!value) {
        closeDialog();
    }
}

function submitBill(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeDialog(),
    };

    form.transform((data) => ({
        ...data,
        telegram_reminder_enabled: data.telegram_reminder_enabled ? 1 : 0,
    }));

    if (editingId.value !== null) {
        form.put(updateBill.url(editingId.value), options);
    } else {
        form.post(storeBill.url(), options);
    }
}

const deleteTarget = ref<Bill | null>(null);
const deleteForm = useForm({});

function requestDelete(bill: Bill): void {
    deleteTarget.value = bill;
}

function confirmDelete(): void {
    if (!deleteTarget.value) {
        return;
    }

    deleteForm.delete(destroyBill.url(deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleteTarget.value = null;
        },
    });
}

const payingId = ref<number | null>(null);

function markPaid(bill: Bill): void {
    if (!bill.next_occurrence) {
        return;
    }

    payingId.value = bill.next_occurrence.id;

    const payForm = useForm({});
    payForm.post(
        payBill.url({ bill: bill.id, occurrence: bill.next_occurrence.id }),
        {
            preserveScroll: true,
            onFinish: () => {
                payingId.value = null;
            },
        },
    );
}

watch(
    () => form.recurrence_type,
    (value) => {
        if (value === 'monthly' && !form.due_day_of_month) {
            form.due_day_of_month = '1';
        }
    },
);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Bills', href: billsIndex() },
        ],
    },
});
</script>
