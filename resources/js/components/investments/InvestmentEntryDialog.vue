<template>
    <Dialog :open="open" @update:open="handleDialogOpenChange">
        <DialogContent
            class="max-h-[calc(100dvh-1rem)] overflow-hidden rounded-[20px] border-0 bg-[#1a1a1a] p-0 text-white shadow-2xl ring-1 ring-white/10 sm:max-h-[calc(100vh-2rem)] sm:min-h-[560px] sm:max-w-[560px] sm:rounded-[25px]"
            :show-close-button="false"
        >
            <form
                class="flex max-h-[calc(100dvh-1rem)] flex-col sm:max-h-[calc(100vh-2rem)]"
                @submit.prevent="submitEntry"
            >
                <div
                    class="flex-1 overflow-y-auto px-4 pt-10 pb-5 sm:px-[80px] sm:pt-[68px] sm:pb-6"
                >
                    <DialogHeader class="mb-6 space-y-2 text-left">
                        <DialogTitle
                            class="text-[20px] leading-normal font-medium text-white"
                        >
                            {{
                                isEditing
                                    ? t('finance.form.edit_investment')
                                    : t('finance.form.add_investment')
                            }}
                        </DialogTitle>
                        <DialogDescription
                            class="text-[15px] leading-[18px] font-light text-[#989898]"
                        >
                            {{ t('finance.form.investment_description') }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-5">
                        <div class="grid gap-4 sm:grid-cols-[1fr_140px]">
                            <div class="grid gap-2">
                                <Label class="finance-dialog-label">
                                    {{ t('finance.fields.asset') }}
                                </Label>
                                <div class="relative">
                                    <button
                                        type="button"
                                        :class="[
                                            fieldClass,
                                            'flex h-9 w-full cursor-pointer items-center gap-2 px-3 text-left',
                                        ]"
                                        @click="
                                            assetDropdownOpen =
                                                !assetDropdownOpen
                                        "
                                    >
                                        <AssetIcon
                                            v-if="selectedAsset"
                                            :icon="selectedAsset.icon"
                                            :icon-svg="selectedAsset.icon_svg"
                                            :label="selectedAsset.label"
                                            :color="selectedAsset.color"
                                            size="sm"
                                        />
                                        <span
                                            class="flex-1 truncate"
                                            :class="
                                                selectedAsset
                                                    ? 'text-white'
                                                    : 'text-[#686868]'
                                            "
                                        >
                                            {{
                                                selectedAsset
                                                    ? `${selectedAsset.label} (${selectedAsset.unit})`
                                                    : t(
                                                          'finance.filters.select_asset',
                                                      )
                                            }}
                                        </span>
                                        <ChevronDown
                                            class="size-4 shrink-0 text-[#686868] transition-transform"
                                            :class="
                                                assetDropdownOpen
                                                    ? 'rotate-180'
                                                    : ''
                                            "
                                        />
                                    </button>

                                    <div
                                        v-if="assetDropdownOpen"
                                        class="absolute top-full left-0 z-50 mt-1 w-full overflow-hidden rounded-xl border border-white/10 bg-[#252525] py-1 shadow-2xl"
                                    >
                                        <button
                                            v-for="assetType in assetTypes"
                                            :key="assetType.id"
                                            type="button"
                                            class="flex w-full cursor-pointer items-center gap-2.5 px-3 py-2 text-left transition-colors hover:bg-white/5"
                                            :class="
                                                String(assetType.id) ===
                                                String(form.investment_asset_id)
                                                    ? 'bg-[#02CD86]/8 text-[#02CD86]'
                                                    : 'text-white'
                                            "
                                            @click="selectAsset(assetType)"
                                        >
                                            <AssetIcon
                                                :icon="assetType.icon"
                                                :icon-svg="assetType.icon_svg"
                                                :label="assetType.label"
                                                :color="assetType.color"
                                                size="sm"
                                            />
                                            <span class="text-sm"
                                                >{{ assetType.label }} ({{
                                                    assetType.unit
                                                }})</span
                                            >
                                        </button>
                                    </div>
                                </div>
                                <InputError
                                    :message="
                                        form.errors.investment_asset_id ||
                                        form.errors.asset_type
                                    "
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label
                                    class="finance-dialog-label"
                                    for="quantity"
                                >
                                    {{ t('finance.fields.quantity') }}
                                </Label>
                                <Input
                                    id="quantity"
                                    v-model="form.quantity"
                                    :class="fieldClass"
                                    required
                                    type="number"
                                    min="0.00000001"
                                    step="any"
                                    placeholder="0.00"
                                />
                                <InputError :message="form.errors.quantity" />
                            </div>
                        </div>

                        <InvestmentAssetCreator
                            :field-class="fieldClass"
                            @created="form.investment_asset_id = $event"
                        />

                        <div class="grid gap-4 sm:grid-cols-[1fr_140px]">
                            <div class="grid gap-2">
                                <Label
                                    class="finance-dialog-label"
                                    for="total_cost"
                                >
                                    {{ t('finance.fields.total_cost') }}
                                    <span class="font-light text-[#989898]"
                                        >({{
                                            t('finance.fields.optional')
                                        }})</span
                                    >
                                </Label>
                                <div
                                    v-if="isTomanCurrency"
                                    :class="moneyFieldClass"
                                >
                                    <Input
                                        id="total_cost"
                                        v-model="displayTotalCost"
                                        class="h-full min-w-0 flex-1 border-0 bg-transparent px-[17px] py-0 text-[16px] leading-[18px] font-normal text-white shadow-none ring-0 outline-none placeholder:text-[#686868] focus-visible:border-0 focus-visible:ring-0"
                                        inputmode="numeric"
                                        placeholder="0"
                                    />
                                    <button
                                        type="button"
                                        class="finance-dialog-money-button"
                                        title="x 1,000"
                                        @click="multiplyTomanTotalCost"
                                    >
                                        000
                                    </button>
                                </div>
                                <Input
                                    v-else
                                    id="total_cost"
                                    v-model="displayTotalCost"
                                    :class="fieldClass"
                                    inputmode="decimal"
                                    placeholder="0.00"
                                />
                                <InputError :message="form.errors.total_cost" />
                            </div>

                            <div class="grid gap-2">
                                <Label
                                    class="finance-dialog-label"
                                    for="cost_basis_currency"
                                >
                                    {{ t('finance.fields.currency') }}
                                </Label>
                                <select
                                    id="cost_basis_currency"
                                    v-model="form.cost_basis_currency"
                                    class="finance-dialog-field"
                                    :class="fieldClass"
                                >
                                    <option
                                        v-for="currency in currencies"
                                        :key="currency.value"
                                        :value="currency.value"
                                    >
                                        {{ currency.label }}
                                    </option>
                                </select>
                                <InputError
                                    :message="form.errors.cost_basis_currency"
                                />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label class="finance-dialog-label">
                                {{ t('finance.fields.date') }}
                            </Label>
                            <BirthdatePicker
                                v-model="form.occurred_at"
                                name="occurred_at"
                                :trigger-class="fieldClass"
                                :years-back="16"
                                :years-forward="1"
                            />
                            <InputError :message="form.errors.occurred_at" />
                        </div>

                        <div class="grid gap-2">
                            <Label class="finance-dialog-label" for="note">
                                {{ t('finance.fields.note') }}
                                <span class="font-light text-[#989898]"
                                    >({{ t('finance.fields.optional') }})</span
                                >
                            </Label>
                            <textarea
                                id="note"
                                v-model="form.note"
                                rows="2"
                                class="finance-dialog-field min-h-9 resize-none"
                                :class="fieldClass"
                                :placeholder="
                                    t(
                                        'finance.form.investment_note_placeholder',
                                    )
                                "
                            />
                            <InputError :message="form.errors.note" />
                        </div>
                    </div>
                </div>

                <div
                    class="flex shrink-0 justify-end gap-2 border-t border-white/10 bg-[#1a1a1a]/95 px-4 py-4 backdrop-blur sm:border-t-0 sm:bg-transparent sm:px-[80px] sm:pt-1 sm:pb-10"
                >
                    <Button
                        type="button"
                        class="h-11 flex-1 rounded-[8px] bg-white/5 px-[10px] text-base font-normal text-[#989898] shadow-none ring-1 ring-white/10 hover:bg-white/10 hover:text-white sm:h-9 sm:w-[90px] sm:flex-none sm:text-[16px]"
                        @click="closeDialog"
                    >
                        {{ t('common.cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        class="h-11 flex-1 rounded-[8px] bg-[#02CD86] px-[10px] text-base font-semibold text-[#101010] shadow-none hover:bg-[#08dd93] sm:h-9 sm:w-[120px] sm:flex-none sm:text-[16px]"
                        :disabled="form.processing"
                    >
                        <Spinner v-if="form.processing" />
                        {{ t('common.confirm') }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AssetIcon from '@/components/AssetIcon.vue';
import BirthdatePicker from '@/components/BirthdatePicker.vue';
import InputError from '@/components/InputError.vue';
import InvestmentAssetCreator from '@/components/InvestmentAssetCreator.vue';
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

type AssetKey = string;
type Currency = 'toman' | 'usd' | 'eur' | string;

type AssetTypeOption = {
    id: number;
    value: AssetKey;
    key: AssetKey;
    label: string;
    unit: string;
    icon: string | null;
    icon_svg: string | null;
    color: string;
    price_source_type: string;
};

type Entry = {
    id: number;
    investment_asset_id: number | null;
    asset_type: AssetKey;
    asset_label: string;
    asset_icon: string | null;
    asset_icon_svg: string | null;
    asset_color: string;
    asset_unit: string;
    quantity: number;
    cost_basis: number | null;
    cost_basis_currency: string | null;
    note: string | null;
    occurred_at: string;
};

type CurrencyOption = {
    label: string;
    value: string;
};

const props = defineProps<{
    open: boolean;
    entry: Entry | null;
    defaultAssetKey?: AssetKey;
    assetTypes: AssetTypeOption[];
    currencies: CurrencyOption[];
    selectedCurrency: string;
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
}>();

const { t } = useI18n();
const assetDropdownOpen = ref(false);
const today = () => new Date().toISOString().slice(0, 10);
const fieldClass =
    'finance-dialog-field finance-dialog-field-income focus-visible:ring-[#02CD86]/25';
const moneyFieldClass =
    'finance-dialog-money-field finance-dialog-field-income focus-within:border-[#02CD86] focus-within:ring-2 focus-within:ring-[#02CD86]/25';

const form = useForm({
    investment_asset_id: '',
    asset_type: '',
    quantity: '',
    note: '',
    occurred_at: today(),
    total_cost: '',
    cost_basis_currency: '',
});

const isEditing = computed(() => props.entry !== null);
const selectedAsset = computed(
    () =>
        props.assetTypes.find(
            (asset) => String(asset.id) === String(form.investment_asset_id),
        ) ?? null,
);
const isTomanCurrency = computed(() => form.cost_basis_currency === 'toman');
const displayTotalCost = computed({
    get: () => formatMoneyInput(form.total_cost),
    set: (value: string) => {
        form.total_cost = normalizeMoneyInput(value, form.cost_basis_currency);
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
        .replace(/\u066B/g, '.')
        .replace(/[\u066C\u060C]/g, '');
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

    const normalized = normalizeMoneyInput(value, form.cost_basis_currency);
    const [integerPart, decimalPart] = normalized.split('.');
    const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    if (normalized.includes('.')) {
        return `${formattedInteger}.${decimalPart ?? ''}`;
    }

    return formattedInteger;
}

function multiplyTomanTotalCost(): void {
    const amount = Number(normalizeMoneyInput(form.total_cost, 'toman'));

    if (!Number.isFinite(amount) || amount <= 0) {
        return;
    }

    form.total_cost = String(Math.trunc(amount * 1000));
}

function formatFormNumber(value: number): string {
    return String(Number(value.toFixed(8)));
}

function resetForm(defaultType?: AssetKey): void {
    form.clearErrors();
    form.reset();

    const defaultAsset =
        props.assetTypes.find((assetType) => assetType.key === defaultType) ??
        props.assetTypes[0];

    form.investment_asset_id = defaultAsset ? String(defaultAsset.id) : '';
    form.asset_type = defaultAsset?.key ?? '';
    form.quantity = '';
    form.note = '';
    form.occurred_at = today();
    form.total_cost = '';
    form.cost_basis_currency =
        props.selectedCurrency || props.currencies[0]?.value || '';
    assetDropdownOpen.value = false;
}

function fillForm(entry: Entry): void {
    form.clearErrors();
    form.investment_asset_id =
        entry.investment_asset_id !== null
            ? String(entry.investment_asset_id)
            : '';
    form.asset_type = entry.asset_type;
    form.quantity = String(entry.quantity);
    form.note = entry.note ?? '';
    form.occurred_at = entry.occurred_at;
    form.cost_basis_currency =
        entry.cost_basis_currency ??
        (props.selectedCurrency || props.currencies[0]?.value || '');
    form.total_cost =
        entry.cost_basis !== null
            ? normalizeMoneyInput(
                  formatFormNumber(entry.cost_basis * entry.quantity),
                  form.cost_basis_currency,
              )
            : '';
    assetDropdownOpen.value = false;
}

function initializeForm(): void {
    if (props.entry) {
        fillForm(props.entry);

        return;
    }

    resetForm(props.defaultAssetKey);
}

function closeDialog(): void {
    emit('update:open', false);
    resetForm(props.defaultAssetKey);
}

function handleDialogOpenChange(open: boolean): void {
    if (open) {
        emit('update:open', true);

        return;
    }

    closeDialog();
}

function selectAsset(assetType: AssetTypeOption): void {
    form.investment_asset_id = String(assetType.id);
    form.asset_type = assetType.key;
    assetDropdownOpen.value = false;
}

function submitEntry(): void {
    form.transform((data) => ({
        ...data,
        asset_type:
            props.assetTypes.find(
                (assetType) =>
                    String(assetType.id) === data.investment_asset_id,
            )?.key ?? data.asset_type,
        total_cost: data.total_cost === '' ? null : data.total_cost,
    }));

    const options = {
        preserveScroll: true,
        onSuccess: closeDialog,
    };

    if (props.entry !== null) {
        form.patch(`/investments/${props.entry.id}`, options);

        return;
    }

    form.post('/investments', options);
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
    () => props.entry,
    () => {
        if (props.open) {
            initializeForm();
        }
    },
);

watch(
    () => form.cost_basis_currency,
    (currency) => {
        form.total_cost = normalizeMoneyInput(form.total_cost, currency);
    },
);
</script>
