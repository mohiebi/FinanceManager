<template>
    <Head :title="t('finance.bills.title')" />

    <div
        class="flex min-h-[calc(100vh-92px)] shrink-0 flex-col overflow-x-auto bg-[#111111]"
    >
        <!-- ── Summary bar — total due for this month. The mock's own
             screen has no header of its own (the shell already owns the
             page title). ─────────────────────────────────────────────── -->
        <section
            class="mx-[18px] mt-5 flex flex-wrap items-center gap-5 rounded-[16px] bg-[#1a1a1a] px-6 py-5 ring-1 ring-white/10"
        >
            <div>
                <p class="mb-2 text-xs text-[#989898]">
                    {{ t('finance.reports.this_month') }} ·
                    {{ currencyLabel(props.monthlyBillSummary.currency) }}
                </p>
                <p class="text-[30px] leading-none font-semibold text-white">
                    <span v-if="monthlyTotal !== null" :class="maskClass">{{
                        formatAmount(monthlyTotal)
                    }}</span>
                    <span
                        v-else
                        aria-hidden="true"
                        class="inline-block h-[1em] w-24 animate-pulse rounded bg-white/10 align-middle"
                    />
                </p>
            </div>
        </section>

        <!-- ── Empty state ────────────────────────────────────────── -->
        <div
            v-if="bills.length === 0"
            class="mx-[18px] my-[18px] flex flex-col items-center justify-center rounded-[16px] bg-[#1a1a1a] px-8 py-20 ring-1 ring-white/10"
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
            <Button
                class="mt-6 h-11 rounded-full bg-[linear-gradient(90deg,#02CD86_0%,#00a36e_100%)] px-5 text-[#101010]"
                @click="openCreateDialog()"
            >
                {{ t('finance.bills.add_bill') }}
            </Button>
        </div>

        <!-- ── Scheduled ──────────────────────────────────────────── -->
        <section
            v-else
            class="mx-[18px] my-[18px] overflow-x-auto rounded-[16px] bg-[#1a1a1a] p-5 pt-5 pb-2.5 ring-1 ring-white/10"
        >
            <div
                class="mb-3 flex min-w-[520px] items-center justify-between gap-3"
            >
                <p class="text-[14.5px] font-medium text-white">
                    {{ t('finance.bills.upcoming') }}
                </p>
                <button
                    type="button"
                    class="cursor-pointer text-[12.5px] text-[#02CD86] hover:underline"
                    @click="openCreateDialog()"
                >
                    + {{ t('finance.bills.add_bill') }}
                </button>
            </div>

            <div
                v-for="bill in bills"
                :key="bill.id"
                class="group grid min-w-[520px] grid-cols-[8px_minmax(130px,1fr)_112px_112px_124px] items-center gap-3.5 border-t border-white/[0.06] py-3.5 transition-colors hover:bg-white/[0.02]"
            >
                <span
                    class="size-2 shrink-0 rounded-full"
                    :style="{ backgroundColor: statusDotColor(bill) }"
                />
                <div class="min-w-0">
                    <p class="truncate text-sm text-white">
                        <Ciphered :value="bill.title" table="bills" />
                    </p>
                    <p
                        class="mt-0.5 truncate text-[11px] text-[#686868]"
                        dir="ltr"
                    >
                        {{ cadenceLabel(bill) }}
                    </p>
                </div>
                <span class="text-[12.5px] text-[#989898]">
                    {{
                        bill.next_occurrence
                            ? displayDate(bill.next_occurrence.due_date)
                            : t('finance.bills.no_upcoming')
                    }}
                </span>
                <span
                    class="text-[12.5px] font-medium"
                    :style="{ color: statusDotColor(bill) }"
                >
                    {{ statusLabel(bill) }}
                </span>
                <div class="flex items-center justify-end gap-2">
                    <span
                        class="text-[14.5px] text-white tabular-nums"
                        :class="maskClass"
                        dir="ltr"
                    >
                        <CipheredMoney
                            :amount="bill.amount"
                            :display-amount="bill.display_amount"
                            :currency="bill.currency as CurrencyCode"
                            :display-currency="
                                bill.display_currency as CurrencyCode
                            "
                            :rates="props.rates"
                            table="bills"
                        />
                    </span>
                    <div
                        class="flex shrink-0 items-center gap-1 opacity-0 transition group-hover:opacity-100"
                    >
                        <button
                            v-if="bill.next_occurrence"
                            type="button"
                            :disabled="payingId === bill.next_occurrence.id"
                            :aria-label="t('finance.bills.mark_paid')"
                            class="cursor-pointer rounded-md p-1 text-[#02CD86] hover:bg-[#02CD86]/10 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="void markPaid(bill)"
                        >
                            <Check class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            :aria-label="t('common.edit')"
                            class="cursor-pointer rounded-md p-1 text-[#6C4EE9] hover:bg-[#6C4EE9]/10"
                            @click="void openEditDialog(bill)"
                        >
                            <Pencil class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            :aria-label="t('common.delete')"
                            class="cursor-pointer rounded-md p-1 text-[#E94E50] hover:bg-[#E94E50]/10"
                            @click="void requestDelete(bill)"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
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
                        <DialogHeader class="mb-6 space-y-2 text-start">
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
                                    <div
                                        v-if="isTomanBillCurrency"
                                        :class="moneyFieldClass"
                                    >
                                        <Input
                                            id="bill-amount"
                                            v-model="displayBillAmount"
                                            class="h-full min-w-0 flex-1 border-0 bg-transparent px-[17px] py-0 text-[16px] leading-[18px] font-normal text-white shadow-none ring-0 outline-none placeholder:text-[#686868] focus-visible:border-0 focus-visible:ring-0"
                                            inputmode="numeric"
                                            placeholder="0"
                                        />
                                        <button
                                            type="button"
                                            class="finance-dialog-money-button"
                                            title="x 1,000"
                                            @click="multiplyBillTomanAmount"
                                        >
                                            000
                                        </button>
                                    </div>
                                    <Input
                                        v-else
                                        id="bill-amount"
                                        v-model="displayBillAmount"
                                        :class="fieldClass"
                                        inputmode="decimal"
                                        placeholder="0.00"
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

                            <div
                                v-if="form.recurrence_type === 'monthly'"
                                class="rounded-xl border border-white/10 bg-white/[0.03] p-4"
                            >
                                <div class="grid gap-2">
                                    <Label
                                        class="finance-dialog-label"
                                        for="bill-payment-duration"
                                    >
                                        {{
                                            t('finance.bills.payment_duration')
                                        }}
                                    </Label>
                                    <Select
                                        v-model="form.recurrence_limit_type"
                                    >
                                        <SelectTrigger
                                            id="bill-payment-duration"
                                            :class="fieldClass"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent
                                            class="finance-dialog-select-content"
                                        >
                                            <SelectItem value="infinite">
                                                {{
                                                    t(
                                                        'finance.bills.continues_indefinitely',
                                                    )
                                                }}
                                            </SelectItem>
                                            <SelectItem value="count">
                                                {{
                                                    t(
                                                        'finance.bills.limit_by_count',
                                                    )
                                                }}
                                            </SelectItem>
                                            <SelectItem value="date">
                                                {{
                                                    t(
                                                        'finance.bills.limit_by_date',
                                                    )
                                                }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        :message="
                                            form.errors.recurrence_limit_type
                                        "
                                    />
                                </div>

                                <div
                                    v-if="
                                        form.recurrence_limit_type === 'count'
                                    "
                                    class="mt-4 grid gap-2"
                                >
                                    <Label
                                        class="finance-dialog-label"
                                        for="bill-recurrence-count"
                                    >
                                        {{ t('finance.bills.total_payments') }}
                                    </Label>
                                    <Input
                                        id="bill-recurrence-count"
                                        v-model="form.recurrence_count"
                                        type="number"
                                        :min="minimumPaymentCount"
                                        max="600"
                                        inputmode="numeric"
                                        :class="fieldClass"
                                    />
                                    <p class="text-xs leading-5 text-[#989898]">
                                        {{
                                            t('finance.bills.count_hint', {
                                                count:
                                                    normalizedPreviewCount ??
                                                    minimumPaymentCount,
                                            })
                                        }}
                                    </p>
                                    <InputError
                                        :message="form.errors.recurrence_count"
                                    />
                                </div>

                                <div
                                    v-else-if="
                                        form.recurrence_limit_type === 'date'
                                    "
                                    class="mt-4 grid gap-2"
                                >
                                    <Label
                                        class="finance-dialog-label"
                                        for="bill-recurrence-end-date"
                                    >
                                        {{
                                            t('finance.bills.last_payment_date')
                                        }}
                                    </Label>
                                    <Input
                                        id="bill-recurrence-end-date"
                                        v-model="form.recurrence_end_date"
                                        type="date"
                                        :min="minimumRecurrenceEndDate"
                                        :class="fieldClass"
                                    />
                                    <p
                                        v-if="normalizedPreviewCount !== null"
                                        aria-live="polite"
                                        class="text-xs leading-5 text-[#a995ff]"
                                    >
                                        {{
                                            t(
                                                'finance.bills.calculated_payments',
                                                {
                                                    count: normalizedPreviewCount,
                                                },
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-else
                                        class="text-xs leading-5 text-[#989898]"
                                    >
                                        {{ t('finance.bills.date_hint') }}
                                    </p>
                                    <InputError
                                        :message="
                                            form.errors.recurrence_end_date
                                        "
                                    />
                                </div>
                            </div>

                            <label
                                class="flex cursor-pointer items-center gap-2.5"
                            >
                                <Checkbox
                                    :checked="form.telegram_reminder_enabled"
                                    @update:checked="
                                        (val: boolean | 'indeterminate') => {
                                            form.telegram_reminder_enabled =
                                                val === true;
                                        }
                                    "
                                />
                                <span class="text-sm text-white/85">{{
                                    t('finance.bills.telegram_reminder')
                                }}</span>
                            </label>

                            <div
                                v-if="form.telegram_reminder_enabled"
                                class="rounded-xl border border-white/10 bg-white/[0.03] p-4"
                            >
                                <label
                                    class="finance-dialog-label mb-2 block"
                                    >{{
                                        t('finance.bills.reminder_time')
                                    }}</label
                                >
                                <div class="flex items-center gap-2">
                                    <Select v-model="form.reminder_time">
                                        <SelectTrigger
                                            :class="fieldClass"
                                            class="w-[120px] shrink-0"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent
                                            class="finance-dialog-select-content max-h-48"
                                        >
                                            <SelectItem
                                                v-for="time in timeOptions"
                                                :key="time"
                                                :value="time"
                                            >
                                                {{ time }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>

                                    <Select v-model="form.reminder_timezone">
                                        <SelectTrigger
                                            :class="fieldClass"
                                            class="min-w-0 flex-1"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent
                                            class="finance-dialog-select-content max-h-56"
                                        >
                                            <SelectItem
                                                v-for="tz in props.timezones"
                                                :key="tz.value"
                                                :value="tz.value"
                                            >
                                                {{ tz.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <InputError
                                    class="mt-1"
                                    :message="form.errors.reminder_time"
                                />
                                <InputError
                                    :message="form.errors.reminder_timezone"
                                />
                            </div>
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
                            :disabled="form.processing || sealing"
                            class="h-11 flex-1 rounded-xl bg-[#02CD86] text-[#101010] shadow-none hover:bg-[#08dd93]"
                        >
                            <Spinner v-if="form.processing || sealing" />
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
                          title: deleteTargetTitle,
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
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Check, Pencil, Receipt, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import Ciphered from '@/components/Ciphered.vue';
import CipheredMoney from '@/components/CipheredMoney.vue';
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
import { useAmountMask } from '@/composables/useAmountMask';
import { useVault } from '@/composables/useVault';
import {
    countMonthlyPaymentsThrough,
    nextMonthlyDueDate,
} from '@/lib/bill-recurrence';
import { formatAppDate } from '@/lib/date';
import { convert as convertMoney } from '@/lib/money';
import type { CurrencyCode, Rates } from '@/lib/money';
import { dashboard } from '@/routes';
import {
    index as billsIndex,
    destroy as destroyBill,
    store as storeBill,
    update as updateBill,
} from '@/routes/bills';
import { pay as payBill } from '@/routes/bills/occurrences';
import type { Encrypted } from '@/types/vault';

type BillOccurrence = {
    id: number;
    due_date: string;
    payment_number: number | null;
};
type Bill = {
    id: number;
    title: Encrypted<string>;
    amount: Encrypted<string | number>;
    currency: string;
    display_amount: string | null;
    display_currency: string;
    /** Occurrences falling in the current calendar month, for the header total. */
    month_occurrence_count: number;
    recurrence_type: 'one_time' | 'monthly';
    due_day_of_month: number | null;
    due_date: string | null;
    recurrence_limit_type: 'count' | 'date' | null;
    recurrence_count: number | null;
    recurrence_end_date: string | null;
    payments_made: number;
    schedule_search_date: string;
    telegram_reminder_enabled: boolean;
    reminder_time: string;
    reminder_timezone: string;
    is_active: boolean;
    category_id: number | null;
    category_name: string | null;
    next_occurrence: BillOccurrence | null;
};
type MonthlyBillSummary = {
    /** Null under the vault — the server cannot total what it cannot read. */
    amount: string | null;
    currency: string;
    count: number;
    from: string;
    to: string;
};

type UpcomingOccurrence = {
    occurrence_id: number;
    bill_id: number;
    title: Encrypted<string>;
    amount: Encrypted<string | number>;
    currency: string;
    display_amount: string | null;
    display_currency: string;
    due_date: string;
    payment_number: number | null;
    payment_count: number | null;
    is_overdue: boolean;
    is_due_today: boolean;
};

const props = defineProps<{
    bills: Bill[];
    categories: { id: number; name: string }[];
    currencies: { label: string; value: string }[];
    timezones: { value: string; label: string }[];
    selectedCurrency: string;
    rates: Rates | null;
    monthlyBillSummary: MonthlyBillSummary;
    upcomingOccurrences: UpcomingOccurrence[];
    userCalendar: string;
    today: string;
}>();

const { t } = useI18n();
const { revealAsync, sealForSubmit, isArmed, trackKey } = useVault();
const { masked } = useAmountMask();
const maskClass = computed(() =>
    masked.value
        ? 'blur-[6px] transition-[filter] duration-150 select-none'
        : 'transition-[filter] duration-150',
);
const page = usePage();
/** The account timezone set in Settings > Preferences; new bill reminders
 *  default to it, though each bill can still override it. */
const accountTimezone = computed(
    () => (page.props.timezone as string | undefined) ?? 'UTC',
);
const bills = computed(() => props.bills);

/**
 * The month's bill total.
 *
 * Comes straight off the server unless the vault is armed, in which case the
 * amounts are ciphertext and the sum has to be rebuilt here from the decrypted
 * values and each bill's occurrence count for the month.
 */
const clientMonthlyTotal = ref<number | null>(null);

const monthlyTotal = computed<number | string | null>(
    () => props.monthlyBillSummary.amount ?? clientMonthlyTotal.value,
);

watchEffect(async () => {
    // Tracked before any await, so unlocking fills the header total in place.
    trackKey();

    if (props.monthlyBillSummary.amount !== null || props.rates === null) {
        clientMonthlyTotal.value = null;

        return;
    }

    const target = props.monthlyBillSummary.currency as CurrencyCode;
    let total = 0;

    for (const bill of props.bills) {
        if (bill.month_occurrence_count === 0) {
            continue;
        }

        const amount = await revealAsync<string | number>(
            bill.amount,
            'bills',
            'decimal',
        );

        if (amount === undefined) {
            clientMonthlyTotal.value = null;

            return;
        }

        total +=
            convertMoney(
                amount,
                bill.currency as CurrencyCode,
                target,
                props.rates,
            ) * bill.month_occurrence_count;
    }

    clientMonthlyTotal.value = Math.round(total * 100) / 100;
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

function cadenceLabel(bill: Bill): string {
    return bill.recurrence_type === 'monthly'
        ? `${t('finance.bills.recurrence_monthly')} — ${t('finance.bills.due_day_label', { day: bill.due_day_of_month })}`
        : t('finance.bills.recurrence_one_time');
}

/** Overdue once past today's date, due today on it, scheduled otherwise —
 *  the same "nothing left to check" once a bill has no next occurrence at
 *  all reads as scheduled too, since there is nothing urgent to flag. */
function statusLabel(bill: Bill): string {
    if (!bill.next_occurrence) {
        return t('finance.bills.scheduled');
    }

    if (bill.next_occurrence.due_date < props.today) {
        return t('finance.bills.overdue');
    }

    if (bill.next_occurrence.due_date === props.today) {
        return t('finance.bills.due_today');
    }

    return t('finance.bills.scheduled');
}

function statusDotColor(bill: Bill): string {
    if (!bill.next_occurrence) {
        return '#686868';
    }

    if (bill.next_occurrence.due_date < props.today) {
        return '#E94E50';
    }

    if (bill.next_occurrence.due_date === props.today) {
        return '#F59E0B';
    }

    return '#686868';
}

const fieldClass =
    'finance-dialog-field finance-dialog-field-income focus-visible:ring-[#02CD86]/25';

const isDialogOpen = ref(false);
const editingId = ref<number | null>(null);
const editingBill = ref<Bill | null>(null);

/** True while the browser is wrapping a payload, so the button stays disabled. */
const sealing = ref(false);

const form = useForm({
    title: '',
    amount: '',
    currency: 'toman',
    category_id: '',
    recurrence_type: 'monthly' as 'one_time' | 'monthly',
    due_day_of_month: '1',
    due_date: '',
    recurrence_limit_type: 'infinite' as 'infinite' | 'count' | 'date',
    recurrence_count: '12',
    recurrence_end_date: '',
    telegram_reminder_enabled: false,
    reminder_time: '09:00',
    reminder_timezone: accountTimezone.value,
});

const minimumPaymentCount = computed(() =>
    Math.max(1, editingBill.value?.payments_made ?? 0),
);

const scheduleSearchDate = computed(
    () => editingBill.value?.schedule_search_date ?? props.today,
);

const minimumRecurrenceEndDate = computed(() => {
    const dueDay = Number(form.due_day_of_month);

    if (!Number.isInteger(dueDay) || dueDay < 1 || dueDay > 31) {
        return scheduleSearchDate.value;
    }

    return nextMonthlyDueDate(
        dueDay,
        props.userCalendar,
        scheduleSearchDate.value,
    );
});

const normalizedPreviewCount = computed<number | null>(() => {
    if (
        form.recurrence_type !== 'monthly' ||
        form.recurrence_limit_type === 'infinite'
    ) {
        return null;
    }

    if (form.recurrence_limit_type === 'count') {
        const count = Number(form.recurrence_count);

        return Number.isInteger(count) && count > 0 ? count : null;
    }

    const dueDay = Number(form.due_day_of_month);

    if (
        !Number.isInteger(dueDay) ||
        dueDay < 1 ||
        dueDay > 31 ||
        !/^\d{4}-\d{2}-\d{2}$/.test(form.recurrence_end_date)
    ) {
        return null;
    }

    return (
        (editingBill.value?.payments_made ?? 0) +
        countMonthlyPaymentsThrough(
            dueDay,
            props.userCalendar,
            scheduleSearchDate.value,
            form.recurrence_end_date,
        )
    );
});

const timeOptions = Array.from(
    { length: 24 },
    (_, i) => `${String(i).padStart(2, '0')}:00`,
);

const categoryModel = computed({
    get: () => (form.category_id ? String(form.category_id) : undefined),
    set: (value: string | undefined) => {
        form.category_id = value ?? '';
    },
});

const moneyFieldClass =
    'finance-dialog-money-field finance-dialog-field-income focus-within:border-[#02CD86] focus-within:ring-2 focus-within:ring-[#02CD86]/25';

const isTomanBillCurrency = computed(() => form.currency === 'toman');

function normalizeMoneyInput(value: string, currency: string): string {
    const normalizedDigits = value
        .replace(/[۰-۹]/g, (digit) => String(digit.charCodeAt(0) - 0x06f0))
        .replace(/[٠-٩]/g, (digit) => String(digit.charCodeAt(0) - 0x0660))
        .replace(/٫/g, '.')
        .replace(/[٬،]/g, '');
    let normalized = '';
    let hasDecimal = false;

    for (const character of normalizedDigits) {
        if (/\d/.test(character)) {
            normalized += character;
            continue;
        }

        if (currency === 'toman' && character === '.') {
            break;
        }

        if (currency !== 'toman' && character === '.' && !hasDecimal) {
            normalized += character;
            hasDecimal = true;
        }
    }

    if (normalized.startsWith('.')) {
        return `0${normalized}`;
    }

    return normalized;
}

function formatBillMoneyInput(value: string): string {
    if (value === '') {
        return '';
    }

    const normalized = normalizeMoneyInput(value, form.currency);
    const [integerPart, decimalPart] = normalized.split('.');
    const formattedInteger = (integerPart ?? '').replace(
        /\B(?=(\d{3})+(?!\d))/g,
        ',',
    );

    if (normalized.includes('.')) {
        return `${formattedInteger}.${decimalPart ?? ''}`;
    }

    return formattedInteger;
}

const displayBillAmount = computed({
    get: () => formatBillMoneyInput(form.amount),
    set: (value: string) => {
        form.amount = normalizeMoneyInput(value, form.currency);
    },
});

function multiplyBillTomanAmount(): void {
    const amount = Number(normalizeMoneyInput(form.amount, 'toman'));

    if (!Number.isFinite(amount) || amount <= 0) {
        return;
    }

    form.amount = String(Math.trunc(amount * 1000));
}

function openCreateDialog(): void {
    editingId.value = null;
    editingBill.value = null;
    form.clearErrors();
    form.reset();
    form.currency = props.currencies[0]?.value ?? 'toman';
    form.recurrence_type = 'monthly';
    form.due_day_of_month = '1';
    form.recurrence_limit_type = 'infinite';
    form.recurrence_count = '12';
    form.recurrence_end_date = '';
    form.reminder_time = '09:00';
    form.reminder_timezone = accountTimezone.value;
    isDialogOpen.value = true;
}

async function openEditDialog(bill: Bill): Promise<void> {
    editingId.value = bill.id;
    editingBill.value = bill;
    form.clearErrors();
    form.title = (await revealAsync<string>(bill.title, 'bills')) ?? '';
    form.amount = normalizeMoneyInput(
        String(
            (await revealAsync<string | number>(
                bill.amount,
                'bills',
                'decimal',
            )) ?? '',
        ),
        bill.currency,
    );
    form.currency = bill.currency;
    form.category_id = bill.category_id ? String(bill.category_id) : '';
    form.recurrence_type = bill.recurrence_type;
    form.due_day_of_month = bill.due_day_of_month
        ? String(bill.due_day_of_month)
        : '1';
    form.due_date = bill.due_date ?? '';
    form.recurrence_limit_type = bill.recurrence_limit_type ?? 'infinite';
    form.recurrence_count = String(bill.recurrence_count ?? 12);
    form.recurrence_end_date = bill.recurrence_end_date ?? '';
    form.telegram_reminder_enabled = Boolean(bill.telegram_reminder_enabled);
    const storedHour =
        (bill.reminder_time ?? '09:00').split(':')[0]?.padStart(2, '0') ?? '09';
    form.reminder_time = `${storedHour}:00`;
    form.reminder_timezone = bill.reminder_timezone ?? accountTimezone.value;
    isDialogOpen.value = true;
}

function closeDialog(): void {
    isDialogOpen.value = false;
    editingId.value = null;
    editingBill.value = null;
    form.clearErrors();
}

function handleDialogOpenChange(value: boolean): void {
    if (!value) {
        closeDialog();
    }
}

/**
 * Encrypts the title and amount before they leave the browser when the vault is
 * armed, and is a no-op otherwise.
 *
 * `transform` rather than mutating the form: the inputs stay bound to plaintext,
 * so the dialog still shows what the user typed if validation comes back failing.
 */
async function submitBill(): Promise<void> {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeDialog(),
    };

    sealing.value = true;

    try {
        const payload = await sealForSubmit({ ...form.data() }, 'bills', {
            title: 'string',
            amount: 'decimal',
        });

        form.transform(() => payload);
    } catch {
        return;
    } finally {
        sealing.value = false;
    }

    if (editingId.value !== null) {
        form.put(updateBill.url(editingId.value), options);
    } else {
        form.post(storeBill.url(), options);
    }
}

const deleteTarget = ref<Bill | null>(null);
const deleteTargetTitle = ref('');
const deleteForm = useForm({});

async function requestDelete(bill: Bill): Promise<void> {
    deleteTarget.value = bill;
    // Resolved rather than interpolated straight in: under the vault `bill.title`
    // is a ciphertext object, which would render as [object Object].
    deleteTargetTitle.value =
        (await revealAsync<string>(bill.title, 'bills')) ?? '';
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

/**
 * Marking a bill paid writes a Cost transaction.
 *
 * Under the vault the browser has to build that transaction's title and amount
 * itself: a ciphertext is bound to its table by the AAD, so the bill's blobs
 * cannot simply be copied across, and the server holds no key to re-seal them.
 */
async function markPaid(bill: Bill): Promise<void> {
    if (!bill.next_occurrence) {
        return;
    }

    payingId.value = bill.next_occurrence.id;

    const payload: { title?: string; amount?: string } = {};

    if (isArmed()) {
        const title = await revealAsync<string>(bill.title, 'bills');
        const amount = await revealAsync<string | number>(
            bill.amount,
            'bills',
            'decimal',
        );

        if (title === undefined || amount === undefined) {
            payingId.value = null;

            return;
        }

        const sealed = await sealForSubmit(
            { title, amount: String(amount) },
            'transactions',
            { title: 'string', amount: 'decimal' },
        );

        payload.title = sealed.title;
        payload.amount = sealed.amount;
    }

    const payForm = useForm(payload);
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
    () => form.currency,
    (currency) => {
        form.amount = normalizeMoneyInput(form.amount, currency);
    },
);

watch(
    () => form.recurrence_type,
    (value) => {
        if (value === 'monthly' && !form.due_day_of_month) {
            form.due_day_of_month = '1';
        }
    },
);

watch(
    () => form.recurrence_limit_type,
    (value) => {
        if (value === 'count' && !form.recurrence_count) {
            form.recurrence_count = String(
                Math.max(12, minimumPaymentCount.value),
            );
        }

        if (value === 'date' && !form.recurrence_end_date) {
            form.recurrence_end_date = minimumRecurrenceEndDate.value;
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
