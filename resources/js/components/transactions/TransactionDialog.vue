<template>
    <Dialog :open="open" @update:open="handleDialogOpenChange">
        <DialogContent
            class="max-h-[calc(100dvh-1rem)] overflow-hidden rounded-[20px] border-0 bg-[#1a1a1a] p-0 shadow-2xl ring-1 ring-white/10 sm:max-h-[calc(100vh-2rem)] sm:min-h-[654px] sm:max-w-[618px] sm:rounded-[25px]"
            :show-close-button="false"
        >
            <form
                class="flex max-h-[calc(100dvh-1rem)] flex-col sm:max-h-[calc(100vh-2rem)]"
                @submit.prevent="submitTransaction"
            >
                <div
                    class="flex-1 overflow-y-auto px-4 pt-10 pb-5 sm:px-[100px] sm:pt-[83px] sm:pb-6"
                >
                    <DialogHeader class="mb-7 space-y-2 text-start">
                        <DialogTitle
                            class="text-[20px] leading-normal font-medium text-white"
                        >
                            {{ dialogTitle }}
                        </DialogTitle>
                        <DialogDescription
                            class="max-w-[418px] text-[16px] leading-[18px] font-light text-[#989898]"
                        >
                            {{ t('finance.form.transaction_description') }}
                        </DialogDescription>
                    </DialogHeader>

                    <input type="hidden" name="type" :value="form.type" />

                    <div class="space-y-5">
                        <div class="grid gap-4 sm:grid-cols-[276px_134px]">
                            <div class="grid gap-2">
                                <Label
                                    class="finance-dialog-label"
                                    for="transaction-title"
                                >
                                    {{ t('finance.fields.subject') }}
                                </Label>
                                <Input
                                    id="transaction-title"
                                    v-model="form.title"
                                    :class="fieldControlClass"
                                    required
                                    :placeholder="
                                        t('finance.form.subject_placeholder')
                                    "
                                />
                                <InputError :message="form.errors.title" />
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    class="finance-dialog-label"
                                    for="transaction-category"
                                >
                                    {{ t('finance.fields.category') }}
                                </Label>
                                <select
                                    id="transaction-category"
                                    v-model="form.category_id"
                                    required
                                    class="finance-dialog-field"
                                    :class="fieldControlClass"
                                >
                                    <option value="" disabled>
                                        {{ t('common.select') }}
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

                        <CategoryCreator
                            :type="form.type"
                            :field-class="fieldControlClass"
                            @created="form.category_id = $event"
                        />

                        <div class="grid gap-2">
                            <Label
                                class="finance-dialog-label"
                                for="transaction-occurred-at"
                            >
                                {{ t('finance.fields.date') }}
                            </Label>
                            <input
                                id="transaction-occurred-at"
                                type="hidden"
                                :value="form.occurred_at"
                            />
                            <BirthdatePicker
                                v-model="form.occurred_at"
                                name="occurred_at"
                                :trigger-class="fieldControlClass"
                                :years-back="16"
                                :years-forward="1"
                            />
                            <InputError :message="form.errors.occurred_at" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-[276px_134px]">
                            <div class="grid gap-2">
                                <Label
                                    class="finance-dialog-label"
                                    for="transaction-amount"
                                >
                                    {{ t('finance.fields.amount') }}
                                </Label>
                                <div
                                    v-if="isTomanCurrency"
                                    :class="moneyFieldClass"
                                >
                                    <Input
                                        id="transaction-amount"
                                        v-model="displayAmount"
                                        class="h-full min-w-0 flex-1 border-0 bg-transparent px-[17px] py-0 text-[16px] leading-[18px] font-normal text-white shadow-none ring-0 outline-none placeholder:text-[#686868] focus-visible:border-0 focus-visible:ring-0"
                                        required
                                        inputmode="numeric"
                                        placeholder="0"
                                    />
                                    <button
                                        type="button"
                                        class="finance-dialog-money-button"
                                        title="x 1,000"
                                        @click="multiplyTomanAmount"
                                    >
                                        000
                                    </button>
                                </div>
                                <Input
                                    v-else
                                    id="transaction-amount"
                                    v-model="displayAmount"
                                    :class="fieldControlClass"
                                    required
                                    inputmode="decimal"
                                    placeholder="0.00"
                                />
                                <InputError :message="form.errors.amount" />
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    class="finance-dialog-label"
                                    for="transaction-currency"
                                >
                                    {{ t('finance.fields.currency') }}
                                </Label>
                                <select
                                    id="transaction-currency"
                                    v-model="form.currency"
                                    class="finance-dialog-field"
                                    :class="fieldControlClass"
                                >
                                    <option
                                        v-for="currency in currencies"
                                        :key="currency.value"
                                        :value="currency.value"
                                    >
                                        {{ currency.label }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.currency" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label
                                class="finance-dialog-label"
                                for="transaction-description"
                            >
                                {{ t('finance.fields.description') }}
                            </Label>
                            <textarea
                                id="transaction-description"
                                v-model="form.description"
                                rows="1"
                                class="finance-dialog-field min-h-9 resize-none"
                                :class="fieldControlClass"
                                :placeholder="
                                    t('finance.form.note_placeholder')
                                "
                            />
                            <InputError :message="form.errors.description" />
                        </div>
                    </div>
                </div>

                <div
                    class="flex shrink-0 justify-end gap-2 border-t border-white/10 bg-[#1a1a1a]/95 px-4 py-4 backdrop-blur sm:border-t-0 sm:bg-transparent sm:px-[100px] sm:pt-1 sm:pb-12"
                >
                    <Button
                        type="button"
                        class="h-11 flex-1 cursor-pointer rounded-[8px] bg-white/5 px-[10px] py-[3px] text-base font-normal text-[#989898] shadow-none ring-1 ring-white/10 hover:bg-white/10 hover:text-white sm:h-9 sm:w-[99px] sm:flex-none sm:text-[20px]"
                        @click="closeDialog"
                    >
                        {{ t('common.cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        :class="confirmButtonClass"
                        :disabled="
                            form.processing ||
                            sealing ||
                            selectedCategories.length === 0
                        "
                    >
                        <Spinner v-if="form.processing || sealing" />
                        {{ t('common.confirm') }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BirthdatePicker from '@/components/BirthdatePicker.vue';
import CategoryCreator from '@/components/CategoryCreator.vue';
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
import { Spinner } from '@/components/ui/spinner';
import { useVault } from '@/composables/useVault';

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
    category_id: number | null;
};

type CurrencyOption = { label: string; value: Currency };

const props = defineProps<{
    open: boolean;
    type: TransactionType;
    transaction: Transaction | null;
    categories: Record<TransactionType, Category[]>;
    currencies: CurrencyOption[];
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
}>();

const { t } = useI18n();

const today = () => new Date().toISOString().slice(0, 10);

const { sealForSubmit } = useVault();

/** True while the browser is wrapping the payload, so the button stays disabled. */
const sealing = ref(false);

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
const isEditing = computed(() => props.transaction !== null);
const dialogTitle = computed(() =>
    isEditing.value
        ? t(
              form.type === 'cost'
                  ? 'finance.form.edit_cost'
                  : 'finance.form.edit_income',
          )
        : t(
              form.type === 'cost'
                  ? 'finance.form.add_cost'
                  : 'finance.form.add_income',
          ),
);
const fieldControlClass = computed(() =>
    form.type === 'cost'
        ? 'finance-dialog-field finance-dialog-field-cost focus-visible:ring-[#947BFF]/30'
        : 'finance-dialog-field finance-dialog-field-income focus-visible:ring-[#02CD86]/25',
);
const moneyFieldClass = computed(() =>
    form.type === 'cost'
        ? 'finance-dialog-money-field finance-dialog-field-cost focus-within:border-[#947BFF] focus-within:ring-2 focus-within:ring-[#947BFF]/30'
        : 'finance-dialog-money-field finance-dialog-field-income focus-within:border-[#02CD86] focus-within:ring-2 focus-within:ring-[#02CD86]/25',
);
const confirmButtonClass = computed(() =>
    form.type === 'cost'
        ? 'h-11 flex-1 rounded-[8px] bg-[#6C4EE9] px-[10px] py-[3px] text-base font-semibold text-white shadow-none hover:bg-[#7D61F0] disabled:opacity-50 sm:h-9 sm:w-[135px] sm:flex-none sm:text-[20px]'
        : 'h-11 flex-1 rounded-[8px] bg-[#02CD86] px-[10px] py-[3px] text-base font-semibold text-[#101010] shadow-none hover:bg-[#08dd93] disabled:opacity-50 sm:h-9 sm:w-[135px] sm:flex-none sm:text-[20px]',
);
const isTomanCurrency = computed(() => form.currency === 'toman');
const displayAmount = computed({
    get: () => formatMoneyInput(form.amount),
    set: (value: string) => {
        form.amount = normalizeMoneyInput(value, form.currency);
    },
});

function normalizeMoneyInput(value: string, currency: Currency): string {
    const normalizedDigits = value
        .replace(/[\u06F0-\u06F9]/g, (digit) =>
            String(digit.charCodeAt(0) - 0x06f0),
        )
        .replace(/[\u0660-\u0669]/g, (digit) =>
            String(digit.charCodeAt(0) - 0x0660),
        )
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

function formatMoneyInput(value: string): string {
    if (value === '') {
        return '';
    }

    const normalized = normalizeMoneyInput(value, form.currency);
    const [integerPart, decimalPart] = normalized.split('.');
    const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    if (normalized.includes('.')) {
        return `${formattedInteger}.${decimalPart ?? ''}`;
    }

    return formattedInteger;
}

function multiplyTomanAmount(): void {
    const amount = Number(normalizeMoneyInput(form.amount, 'toman'));

    if (!Number.isFinite(amount) || amount <= 0) {
        return;
    }

    form.amount = String(Math.trunc(amount * 1000));
}

function resetForm(type: TransactionType): void {
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
}

function fillForm(transaction: Transaction): void {
    form.clearErrors();
    form.type = transaction.type;
    form.category_id = transaction.category_id?.toString() ?? '';
    form.currency = transaction.currency;
    form.amount = normalizeMoneyInput(transaction.amount, transaction.currency);
    form.title = transaction.title;
    form.description = transaction.description ?? '';
    form.occurred_at = transaction.occurred_at;
}

function initializeForm(): void {
    if (props.transaction) {
        fillForm(props.transaction);

        return;
    }

    resetForm(props.type);
}

function closeDialog(): void {
    const type = form.type;

    emit('update:open', false);
    resetForm(type);
}

function handleDialogOpenChange(open: boolean): void {
    if (open) {
        emit('update:open', true);

        return;
    }

    closeDialog();
}

/**
 * Encrypts the money and free-text fields before they leave the browser when the
 * vault is armed, and is a no-op otherwise.
 *
 * `transform` rather than mutating the form: the inputs stay bound to plaintext,
 * so the dialog still shows what the user typed if the request comes back with
 * validation errors.
 */
async function submitTransaction(): Promise<void> {
    const options = {
        preserveScroll: true,
        onSuccess: closeDialog,
    };

    sealing.value = true;

    try {
        const payload = await sealForSubmit(
            { ...form.data() },
            'transactions',
            {
                amount: 'decimal',
                title: 'string',
                description: 'string',
            },
        );

        form.transform(() => payload);
    } catch {
        sealing.value = false;

        return;
    }

    sealing.value = false;

    if (props.transaction) {
        form.patch(`/transactions/${props.transaction.id}`, options);

        return;
    }

    form.post('/transactions', options);
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            initializeForm();
        }
    },
);

watch(
    () => props.transaction,
    () => {
        if (props.open) {
            initializeForm();
        }
    },
);

watch(
    () => form.currency,
    (currency) => {
        form.amount = normalizeMoneyInput(form.amount, currency);
    },
);
</script>
