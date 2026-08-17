<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AssetIcon from '@/components/AssetIcon.vue';
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
            class="max-h-[calc(100dvh-1rem)] overflow-y-auto rounded-[20px] border-0 bg-[#1a1a1a] p-0 text-white shadow-2xl ring-1 ring-white/10 sm:max-w-[480px] sm:rounded-[25px]"
            :show-close-button="false"
        >
            <form class="flex flex-col" @submit.prevent="submit">
                <div class="px-6 pt-10 pb-5 sm:px-10 sm:pt-12">
                    <DialogHeader class="mb-6 space-y-2 text-start">
                        <DialogTitle
                            class="text-[20px] leading-normal font-medium text-white"
                        >
                            {{ t('finance.investments.sell') }}
                        </DialogTitle>
                    </DialogHeader>

                    <p
                        v-if="sellable.length === 0"
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

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label
                                    class="finance-dialog-label"
                                    for="sell-total"
                                >
                                    {{ t('finance.investments.sell_total') }}
                                </Label>
                                <Input
                                    id="sell-total"
                                    v-model="form.total_sale"
                                    inputmode="decimal"
                                    :class="fieldClass"
                                    placeholder="0"
                                />
                                <InputError :message="form.errors.total_sale" />
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    class="finance-dialog-label"
                                    for="sell-currency"
                                >
                                    {{ t('finance.fields.currency') }}
                                </Label>
                                <Select v-model="form.sale_price_currency">
                                    <SelectTrigger
                                        id="sell-currency"
                                        :class="fieldClass"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent
                                        class="finance-dialog-select-content"
                                    >
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

                        <div class="grid gap-2">
                            <Label class="finance-dialog-label" for="sell-date">
                                {{ t('finance.fields.date') }}
                            </Label>
                            <Input
                                id="sell-date"
                                v-model="form.occurred_at"
                                type="date"
                                :class="fieldClass"
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
                    class="flex items-center gap-3 border-t border-white/10 px-6 py-4 sm:px-10"
                >
                    <Button
                        type="button"
                        class="h-11 flex-1 rounded-xl bg-white/5 text-white/70 shadow-none ring-1 ring-white/10 hover:bg-white/10 hover:text-white"
                        @click="close"
                    >
                        {{ t('common.cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        :disabled="!canSubmit || form.processing || sealing"
                        class="h-11 flex-1 rounded-xl bg-[#02CD86] text-[#101010] shadow-none hover:bg-[#08dd93] disabled:opacity-40"
                    >
                        <Spinner v-if="form.processing || sealing" />
                        {{ t('finance.investments.sell') }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
