<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AssetIcon from '@/components/AssetIcon.vue';
import BirthdatePicker from '@/components/BirthdatePicker.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { useMoneyInput } from '@/composables/useMoneyInput';
import { useVault } from '@/composables/useVault';
import { signedQuantityFor } from '@/lib/portfolio';

/**
 * Records a disposal.
 *
 * Never edits or deletes the purchase it came from: a sale is its own row with a
 * negative quantity, which is the only shape that can express selling part of a
 * holding and the only one that leaves the history intact.
 */
type SellableAsset = {
    id: number;
    label: string;
    unit: string;
    icon: string | null;
    icon_svg: string | null;
    color: string;
    /** Net units held right now — null while the vault is still decrypting. */
    quantity: number | null;
    /** Average cost per unit, in toman. Null when nothing was ever priced. */
    avgCostBasis: number | null;
};

const props = defineProps<{
    open: boolean;
    /** Holdings are reloading, e.g. after a refused sale redirects back. */
    loading?: boolean;
    assets: SellableAsset[];
    currencies: { label: string; value: string }[];
    selectedCurrency: string;
}>();

const emit = defineEmits<{ 'update:open': [boolean] }>();

const { t } = useI18n();
const { isArmed, sealForSubmit } = useVault();

const today = () => new Date().toISOString().slice(0, 10);
const fieldClass =
    'finance-dialog-field finance-dialog-field-income focus-visible:ring-[#02CD86]/25';
const moneyFieldClass =
    'finance-dialog-money-field finance-dialog-field-income focus-within:border-[#02CD86] focus-within:ring-2 focus-within:ring-[#02CD86]/25';

const sealing = ref(false);

const form = useForm({
    investment_asset_id: '',
    quantity: '',
    total_sale: '',
    sale_price_currency: 'toman',
    note: '',
    occurred_at: today(),
    record_transaction: false,
});

/**
 * The proceeds field, grouped in threes as it is typed and carrying the ×1000
 * button for toman — the same treatment the purchase and transaction dialogs
 * give their amounts. `form.total_sale` still holds the plain digits.
 */
const {
    display: displayTotalSale,
    isToman: isTomanCurrency,
    multiplyByThousand: multiplyTomanTotalSale,
    renormalize: renormalizeTotalSale,
} = useMoneyInput({
    get: () => form.total_sale,
    set: (value) => {
        form.total_sale = value;
    },
    currency: () => form.sale_price_currency,
});

/** Only assets actually held can be sold. */
const sellable = computed(() =>
    props.assets.filter((asset) => (asset.quantity ?? 0) > 0),
);

const selected = computed(
    () =>
        sellable.value.find(
            (asset) => String(asset.id) === form.investment_asset_id,
        ) ?? null,
);

const held = computed(() => selected.value?.quantity ?? 0);

const exceedsHolding = computed(
    () => form.quantity !== '' && Number(form.quantity) > held.value,
);

const canSubmit = computed(
    () =>
        selected.value !== null &&
        Number(form.quantity) > 0 &&
        !exceedsHolding.value &&
        form.total_sale !== '',
);

/** What the user actually received — the figure they typed. */
const proceeds = computed(() => {
    const total = Number(form.total_sale);

    return Number.isFinite(total) && total > 0 ? total : null;
});

/**
 * Per unit, derived from the total.
 *
 * Only sent when the vault is armed; otherwise the server does this division and
 * ignores whatever the client claims.
 */
const salePricePerUnit = computed(() => {
    const units = Number(form.quantity);

    return proceeds.value !== null && units > 0 ? proceeds.value / units : null;
});

function close(): void {
    emit('update:open', false);
}

function saleTitle(): string {
    return t('finance.investments.sold_title', {
        quantity: form.quantity,
        unit: selected.value?.unit ?? '',
        asset: selected.value?.label ?? '',
    });
}

async function submit(): Promise<void> {
    if (!canSubmit.value) {
        return;
    }

    const data = {
        ...form.data(),
        // Both derived values are only authoritative here because an armed vault
        // leaves the server no way to compute them; it recomputes both otherwise.
        sale_price: salePricePerUnit.value,
        cost_basis: selected.value?.avgCostBasis ?? null,
        cost_basis_currency: 'toman',
    };

    sealing.value = true;

    try {
        if (isArmed()) {
            const sealed = await sealForSubmit(
                {
                    ...data,
                    // Negated before it is sealed, because after that nothing can
                    // reach it: holdings are a plain sum, so a sale only subtracts
                    // if its stored quantity is negative, and the server has no key
                    // to apply that sign itself. Unarmed, the server negates and
                    // this value is ignored.
                    quantity: String(
                        signedQuantityFor('sell', Number(form.quantity)),
                    ),
                },
                'investments',
                {
                    quantity: 'decimal',
                    sale_price: 'decimal',
                    cost_basis: 'decimal',
                    note: 'string',
                },
            );

            let mirrored: Record<string, string | null> = {};

            if (data.record_transaction && proceeds.value !== null) {
                // Sealed under the destination column names: the AAD binds a
                // ciphertext to its table *and* column, so these cannot be reused
                // from the investment row.
                const transaction = await sealForSubmit(
                    {
                        title: saleTitle(),
                        amount: String(proceeds.value),
                        description: form.note,
                    },
                    'transactions',
                    {
                        title: 'string',
                        amount: 'decimal',
                        description: 'string',
                    },
                );

                mirrored = {
                    transaction_title: transaction.title,
                    transaction_amount: transaction.amount,
                    transaction_description: transaction.description || null,
                };
            }

            form.transform(() => ({
                ...sealed,
                ...mirrored,
                total_sale: null,
            }));
        } else {
            form.transform(() => data);
        }
    } finally {
        sealing.value = false;
    }

    form.post('/investments/sell', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            close();
        },
    });
}

// The currency decides whether a decimal point is allowed, so a switch has to
// re-run the digits already typed through it.
watch(() => form.sale_price_currency, renormalizeTotalSale);

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        form.clearErrors();
        form.reset();
        form.occurred_at = today();
        form.sale_price_currency = props.selectedCurrency;
        form.investment_asset_id =
            sellable.value.length === 1 ? String(sellable.value[0]!.id) : '';
    },
);
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="max-h-[calc(100dvh-1rem)] overflow-hidden rounded-[20px] border-0 bg-[#1a1a1a] p-0 text-white shadow-2xl ring-1 ring-white/10 sm:max-h-[calc(100vh-2rem)] sm:max-w-[560px] sm:rounded-[25px]"
            :show-close-button="false"
        >
            <form
                class="flex max-h-[calc(100dvh-1rem)] flex-col sm:max-h-[calc(100vh-2rem)]"
                @submit.prevent="submit"
            >
                <div
                    class="app-scroll-thin flex-1 overflow-y-auto px-4 pt-10 pb-5 sm:px-[80px] sm:pt-[68px] sm:pb-6"
                >
                    <DialogHeader class="mb-6 space-y-2 text-start">
                        <DialogTitle
                            class="text-[20px] leading-normal font-medium text-white"
                        >
                            {{ t('finance.investments.sell') }}
                        </DialogTitle>
                        <DialogDescription
                            class="text-[15px] leading-[18px] font-light text-[#989898]"
                        >
                            {{ t('finance.investments.sell_description') }}
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        v-if="props.loading"
                        class="flex items-center gap-2 text-sm text-[#989898]"
                    >
                        <Spinner class="size-4" />
                        {{ t('finance.calculating') }}
                    </div>

                    <p
                        v-else-if="sellable.length === 0"
                        class="text-sm text-[#989898]"
                    >
                        {{ t('finance.investments.nothing_to_sell') }}
                    </p>

                    <div v-else class="space-y-5">
                        <div class="grid gap-2">
                            <Label
                                class="finance-dialog-label"
                                for="sell-asset"
                            >
                                {{ t('finance.fields.asset') }}
                            </Label>
                            <Select v-model="form.investment_asset_id">
                                <SelectTrigger
                                    id="sell-asset"
                                    :class="fieldClass"
                                >
                                    <SelectValue
                                        :placeholder="
                                            t('finance.filters.select_asset')
                                        "
                                    />
                                </SelectTrigger>
                                <SelectContent
                                    class="finance-dialog-select-content"
                                >
                                    <SelectItem
                                        v-for="asset in sellable"
                                        :key="asset.id"
                                        :value="String(asset.id)"
                                    >
                                        <span class="flex items-center gap-2">
                                            <AssetIcon
                                                :icon="asset.icon"
                                                :icon-svg="asset.icon_svg"
                                                :label="asset.label"
                                                :color="asset.color"
                                                size="sm"
                                            />
                                            {{ asset.label }}
                                        </span>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.investment_asset_id"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label
                                class="finance-dialog-label"
                                for="sell-quantity"
                            >
                                {{ t('finance.investments.sell_quantity') }}
                            </Label>
                            <Input
                                id="sell-quantity"
                                v-model="form.quantity"
                                inputmode="decimal"
                                :class="fieldClass"
                                placeholder="0"
                            />
                            <p
                                v-if="selected"
                                class="text-xs"
                                :class="
                                    exceedsHolding
                                        ? 'text-[#E94E50]'
                                        : 'text-[#6b6b6b]'
                                "
                            >
                                {{
                                    exceedsHolding
                                        ? t(
                                              'finance.investments.sell_exceeds_holding',
                                          )
                                        : t('finance.investments.holding_now', {
                                              quantity: held,
                                              unit: selected.unit,
                                          })
                                }}
                            </p>
                            <InputError :message="form.errors.quantity" />
                        </div>

                        <!-- Subgrid rather than two independent stacks: the
                             proceeds label is long enough to wrap in some
                             locales, and without a shared row track that wrap
                             pushed its input below the currency beside it. -->
                        <div
                            class="grid gap-4 sm:grid-cols-[1fr_140px] sm:grid-rows-[auto_auto_auto]"
                        >
                            <div
                                class="grid gap-2 sm:row-span-3 sm:grid-rows-subgrid"
                            >
                                <Label
                                    class="finance-dialog-label"
                                    for="sell-total"
                                >
                                    {{ t('finance.investments.sell_total') }}
                                </Label>
                                <div
                                    v-if="isTomanCurrency"
                                    :class="moneyFieldClass"
                                >
                                    <Input
                                        id="sell-total"
                                        v-model="displayTotalSale"
                                        class="h-full min-w-0 flex-1 border-0 bg-transparent px-[17px] py-0 text-[16px] leading-[18px] font-normal text-white shadow-none ring-0 outline-none placeholder:text-[#686868] focus-visible:border-0 focus-visible:ring-0"
                                        inputmode="numeric"
                                        placeholder="0"
                                    />
                                    <button
                                        type="button"
                                        class="finance-dialog-money-button"
                                        title="x 1,000"
                                        @click="multiplyTomanTotalSale"
                                    >
                                        000
                                    </button>
                                </div>
                                <Input
                                    v-else
                                    id="sell-total"
                                    v-model="displayTotalSale"
                                    inputmode="decimal"
                                    :class="fieldClass"
                                    placeholder="0.00"
                                />
                                <InputError :message="form.errors.total_sale" />
                            </div>
                            <div
                                class="grid gap-2 sm:row-span-3 sm:grid-rows-subgrid"
                            >
                                <Label
                                    class="finance-dialog-label"
                                    for="sell-currency"
                                >
                                    {{ t('finance.fields.currency') }}
                                </Label>
                                <select
                                    id="sell-currency"
                                    v-model="form.sale_price_currency"
                                    class="finance-dialog-field"
                                    :class="fieldClass"
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
                                    :message="form.errors.sale_price_currency"
                                />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label class="finance-dialog-label" for="sell-date">
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

                        <!-- Off by default: a sale is a portfolio event, and only
                             the user knows whether the money actually reached the
                             account CashPilot is tracking. -->
                        <label
                            v-if="proceeds !== null"
                            class="flex cursor-pointer items-start gap-2.5"
                        >
                            <Checkbox
                                :checked="form.record_transaction"
                                class="mt-0.5"
                                @update:checked="
                                    form.record_transaction = $event === true
                                "
                            />
                            <span class="min-w-0">
                                <span class="block text-sm text-white/85">
                                    {{ t('finance.investments.record_income') }}
                                </span>
                                <span class="block text-xs text-[#6b6b6b]">
                                    {{
                                        t(
                                            'finance.investments.record_income_hint',
                                        )
                                    }}
                                </span>
                            </span>
                        </label>
                    </div>
                </div>

                <div
                    class="flex shrink-0 justify-end gap-2 border-t border-white/10 bg-[#1a1a1a]/95 px-4 py-4 backdrop-blur sm:border-t-0 sm:bg-transparent sm:px-[80px] sm:pt-1 sm:pb-10"
                >
                    <Button
                        type="button"
                        class="h-11 flex-1 rounded-[8px] bg-white/5 px-[10px] text-base font-normal text-[#989898] shadow-none ring-1 ring-white/10 hover:bg-white/10 hover:text-white sm:h-9 sm:w-[90px] sm:flex-none sm:text-[16px]"
                        @click="close"
                    >
                        {{ t('common.cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        :disabled="!canSubmit || form.processing || sealing"
                        class="h-11 flex-1 rounded-[8px] bg-[#02CD86] px-[10px] text-base font-semibold text-[#101010] shadow-none hover:bg-[#08dd93] disabled:opacity-40 sm:h-9 sm:w-[120px] sm:flex-none sm:text-[16px]"
                    >
                        <Spinner v-if="form.processing || sealing" />
                        {{ t('finance.investments.sell') }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
