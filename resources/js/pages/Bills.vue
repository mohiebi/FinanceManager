<template>
    <Head :title="t('finance.bills.title')" />

    <div
        class="flex min-h-[calc(100vh-92px)] shrink-0 flex-col overflow-x-auto bg-[#111111]"
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
                            {{ t('finance.fields.total_cost') }} ·
                            {{ t('finance.reports.this_month') }}
                        </p>
                        <p class="text-2xl leading-none font-bold text-white">
                            <span v-if="monthlyTotal !== null">{{
                                formatAmount(monthlyTotal)
                            }}</span>
                            <span
                                v-else
                                aria-hidden="true"
                                class="inline-block h-[1em] w-24 animate-pulse rounded bg-white/10 align-middle"
                            />
                            <span
                                class="ml-1 text-xs font-normal text-[#989898]"
                                >{{
                                    currencyLabel(
                                        props.monthlyBillSummary.currency,
                                    )
                                }}</span
                            >
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
                            <!-- Passes plaintext straight through today; the same
                                 markup handles ciphertext once a vault is armed. -->
                            <Ciphered :value="bill.title" table="bills" />
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
                            @click="void openEditDialog(bill)"
                        >
                            {{ t('common.edit') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-md p-1.5 hover:bg-[#fff0f0]"
                            @click="void requestDelete(bill)"
                        >
                            <Trash2 class="size-3.5 text-[#E94E50]" />
                        </button>
                    </div>
                </div>

                <p class="text-2xl font-bold text-white">
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
                        @click="void markPaid(bill)"
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
                class="mb-5 text-[10px] font-medium tracking-widest text-[#6b6b6b] uppercase"
            >
                {{ t('finance.bills.upcoming') }}
            </h2>

            <div
                v-for="(group, gi) in groupedUpcoming"
                :key="group.month"
                :class="{ 'mt-6': gi > 0 }"
            >
                <!-- Month separator -->
                <div class="mb-1 flex items-center gap-3">
                    <span class="shrink-0 text-xs font-semibold text-white">
                        {{ group.label }}
                    </span>
                    <div class="h-px flex-1 bg-white/[0.08]" />
                </div>

                <!-- Bill rows -->
                <div class="divide-y divide-white/[0.05]">
                    <div
                        v-for="occ in group.occurrences"
                        :key="occ.occurrence_id"
                        class="flex items-center gap-3 py-2.5 first:pt-2 last:pb-0"
                    >
                        <!-- Day badge — colour encodes urgency -->
                        <div
                            :class="[
                                'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-[11px] font-semibold tabular-nums',
                                occ.is_overdue
                                    ? 'bg-[#2f1717] text-[#E94E50]'
                                    : occ.is_due_today
                                      ? 'bg-[#0d2620] text-[#02CD86]'
                                      : 'bg-white/[0.06] text-[#6b6b6b]',
                            ]"
                        >
                            {{ displayDate(occ.due_date).split('-')[2] }}
                        </div>

                        <!-- Bill title -->
                        <p
                            class="min-w-0 flex-1 truncate text-sm font-medium text-white"
                        >
                            <Ciphered :value="occ.title" table="bills" />
                        </p>

                        <!-- Status chip + amount -->
                        <div class="flex shrink-0 items-center gap-3">
                            <span
                                v-if="occ.is_overdue"
                                class="rounded-md bg-[#2f1717] px-2 py-0.5 text-[10px] font-medium text-[#E94E50]"
                                >{{ t('finance.bills.overdue') }}</span
                            >
                            <span
                                v-else-if="occ.is_due_today"
                                class="rounded-md bg-[#0d2620] px-2 py-0.5 text-[10px] font-medium text-[#02CD86]"
                                >{{ t('finance.bills.due_today') }}</span
                            >
                            <span
                                v-else
                                class="hidden text-xs text-[#6b6b6b] tabular-nums sm:inline"
                                >{{ displayDate(occ.due_date) }}</span
                            >

                            <span
                                class="min-w-[110px] text-right text-sm font-bold text-white tabular-nums"
                            >
                                <CipheredMoney
                                    :amount="occ.amount"
                                    :display-amount="occ.display_amount"
                                    :currency="occ.currency as CurrencyCode"
                                    :display-currency="
                                        occ.display_currency as CurrencyCode
                                    "
                                    :rates="props.rates"
                                    table="bills"
                                />
                                <span
                                    class="text-[10px] font-normal text-[#6b6b6b]"
                                    >{{
                                        currencyLabel(occ.display_currency)
                                    }}</span
                                >
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
import { toJalaali } from 'jalaali-js';
import { CalendarClock, Plus, Receipt, Trash2 } from 'lucide-vue-next';
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
import { useVault } from '@/composables/useVault';
import {
    formatAppDate,
    jalaliMonthAbbreviations,
    monthBucketKeyFromIso,
} from '@/lib/date';
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

type BillOccurrence = { id: number; due_date: string };
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
}>();

const { t } = useI18n();
const { revealAsync, sealForSubmit, isArmed, trackKey } = useVault();
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

const groupedUpcoming = computed(() => {
    const groups = new Map<
        string,
        { month: string; label: string; occurrences: UpcomingOccurrence[] }
    >();

    for (const occ of props.upcomingOccurrences) {
        const key = monthBucketKeyFromIso(occ.due_date, props.userCalendar);

        if (!groups.has(key)) {
            const [gy, gm, gd] = occ.due_date.split('-').map(Number);
            let label: string;

            if (props.userCalendar === 'jalali') {
                const j = toJalaali(gy, gm, gd);
                label = `${jalaliMonthAbbreviations[j.jm - 1]} ${j.jy}`;
            } else {
                label = new Date(gy, gm - 1, 1).toLocaleString('default', {
                    month: 'long',
                    year: 'numeric',
                });
            }

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
    telegram_reminder_enabled: false,
    reminder_time: '09:00',
    reminder_timezone: accountTimezone.value,
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
    form.clearErrors();
    form.reset();
    form.currency = props.currencies[0]?.value ?? 'toman';
    form.recurrence_type = 'monthly';
    form.due_day_of_month = '1';
    form.reminder_time = '09:00';
    form.reminder_timezone = accountTimezone.value;
    isDialogOpen.value = true;
}

async function openEditDialog(bill: Bill): Promise<void> {
    editingId.value = bill.id;
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

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Bills', href: billsIndex() },
        ],
    },
});
</script>
