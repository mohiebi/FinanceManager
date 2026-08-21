<template>
    <Head :title="t('finance.investments.title')" />

    <!-- `shrink-0` with no `h-full`/`flex-1` is load-bearing. Pinned to the
         viewport, this flex column squeezed its own sections below their content
         height and the `overflow-hidden` that rounds their corners clipped them in
         half. Letting it grow to its content instead also keeps SidebarInset the
         single scroll container — `overflow-x-hidden` computes `overflow-y: auto`,
         so a root that overflows quietly becomes a second scrollbar. -->
    <div
        class="flex min-h-[calc(100vh-92px)] shrink-0 flex-col overflow-x-hidden bg-[#111111]"
    >
        <Deferred
            :data="[
                'assets',
                'summary',
                'prices',
                'marketPriceRows',
                'pricesAvailable',
            ]"
        >
            <template #fallback>
                <div
                    class="mx-[18px] my-[18px] flex flex-col items-center justify-center rounded-[16px] bg-[#1a1a1a] px-8 py-20 ring-1 ring-white/10"
                >
                    <Spinner class="size-8 text-[#02CD86]" />
                    <p class="mt-4 text-sm text-[#989898]">
                        {{ t('finance.calculating') }}
                    </p>
                </div>
            </template>

            <section
                v-if="visibleMarketPriceRows.length > 0"
                class="mx-[18px] mt-[18px] overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div
                    class="mb-3.5 flex flex-wrap items-baseline justify-between gap-3"
                >
                    <div class="flex items-center gap-2.5">
                        <span
                            class="size-1.5 shrink-0 rounded-full bg-[#02CD86]"
                        />
                        <span class="text-[13.5px] font-medium text-white">{{
                            t('finance.investments.market_prices_title')
                        }}</span>
                        <span
                            v-if="lastSyncedLabel"
                            class="text-[11.5px] text-[#686868]"
                        >
                            {{
                                t('finance.last_synced', {
                                    time: lastSyncedLabel,
                                })
                            }}
                        </span>
                    </div>
                    <span class="text-[11.5px] text-[#686868]">
                        {{
                            t('finance.investments.market_prices_unit', {
                                currency: selectedCurrencyLabel,
                            })
                        }}
                    </span>
                </div>

                <div class="space-y-5">
                    <div v-for="row in visibleMarketPriceRows" :key="row.key">
                        <div class="mb-3 flex items-center gap-3">
                            <h3 class="text-sm font-semibold text-white">
                                {{ row.title }}
                            </h3>
                            <span class="h-px flex-1 bg-white/10" />
                        </div>
                        <div
                            class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-white/[0.06] bg-white/[0.06] sm:grid-cols-3 lg:grid-cols-5"
                        >
                            <article
                                v-for="asset in row.assets"
                                :key="asset.key"
                                class="min-w-0 bg-[#1a1a1a] p-[13px]"
                            >
                                <div class="mb-2 flex items-center gap-2">
                                    <span
                                        class="size-1.5 shrink-0 rounded-full"
                                        :style="{
                                            backgroundColor: asset.color,
                                        }"
                                    />
                                    <p
                                        class="min-w-0 flex-1 truncate text-[12.5px] text-[#989898]"
                                    >
                                        {{ asset.label }}
                                    </p>
                                    <span
                                        v-if="heldAssetKeys.has(asset.key)"
                                        class="shrink-0 rounded-full bg-[#6C4EE9]/15 px-1.5 py-0.5 text-[9.5px] font-medium text-[#a89bf3]"
                                    >
                                        {{ t('finance.investments.yours') }}
                                    </span>
                                </div>
                                <p class="text-[15px] font-semibold text-white">
                                    <template v-if="asset.price_available">
                                        <span :class="maskClass">{{
                                            formatCurrencyNumber(
                                                asset.price_formatted,
                                                selectedCurrency as CurrencyCode,
                                            )
                                        }}</span>
                                    </template>
                                    <span
                                        v-else
                                        class="text-xs font-medium text-[#989898]"
                                        >{{
                                            t('finance.price_unavailable')
                                        }}</span
                                    >
                                </p>
                            </article>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── Empty state when no entries yet ──────────────────── -->
            <div
                v-if="(props.assets ?? []).length === 0"
                class="mx-[18px] my-[18px] flex flex-col items-center justify-center rounded-[16px] bg-[#1a1a1a] px-8 py-20 ring-1 ring-white/10"
            >
                <span
                    class="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#24212f]"
                >
                    <TrendingUp class="size-8 text-[#6C4EE9]" />
                </span>
                <h2 class="mt-4 text-xl font-semibold text-white">
                    {{ t('finance.investments.no_entries_title') }}
                </h2>
                <p class="mt-2 max-w-sm text-center text-sm text-[#989898]">
                    {{ t('finance.investments.no_entries_description') }}
                </p>
                <Button
                    class="mt-6 h-11 rounded-full bg-[linear-gradient(90deg,#947BFF_0%,#6C4EE9_100%)] px-6 text-white shadow-[0_8px_20px_rgba(108,78,233,0.25)] hover:brightness-105"
                    @click="openCreateDialog()"
                >
                    <Plus class="size-4" />
                    {{ t('finance.actions.add_first_entry') }}
                </Button>
            </div>
        </Deferred>

        <!-- ── Entries, grouped by asset ──────────────────────────── -->
        <div
            v-if="props.entries.length > 0"
            class="mx-[18px] mt-[18px] mb-[38px] overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 pt-5 pb-2.5 ring-1 ring-white/10 sm:overflow-x-auto"
        >
            <div
                class="mb-3 flex flex-wrap items-center justify-between gap-4 sm:min-w-[640px]"
            >
                <div>
                    <p class="text-[14.5px] font-medium text-white">
                        {{ t('finance.investments.investment_entries') }}
                    </p>
                    <p class="mt-0.5 text-[12.5px] text-[#989898]">
                        {{
                            t('finance.investments.entries_grouped_by_asset', {
                                count: props.entries.length,
                            })
                        }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a
                        :href="`/investments/export?currency=${props.selectedCurrency}`"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-[10px] bg-white/5 px-3 py-2 text-xs whitespace-nowrap text-[#989898] ring-1 ring-white/10 transition-colors hover:bg-white/10 hover:text-white"
                    >
                        <Download class="size-3.5" />
                        {{ t('finance.actions.export_transactions') }}
                    </a>
                    <!-- Selling never deletes the purchase — it writes a disposal
                         row, so history and cost basis both survive. -->
                    <button
                        v-if="sellableAssets.length > 0"
                        type="button"
                        class="cursor-pointer rounded-[10px] bg-[#6C4EE9] px-3.5 py-2 text-[13px] font-medium text-white transition hover:brightness-110"
                        @click="isSellDialogOpen = true"
                    >
                        {{ t('finance.investments.sell') }} −
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer rounded-[10px] bg-[#02CD86] px-3.5 py-2 text-[13px] font-medium text-[#101010] transition hover:brightness-110"
                        @click="openCreateDialog()"
                    >
                        {{ t('finance.actions.add_entry') }} +
                    </button>
                </div>
            </div>

            <!-- Entries arrive eagerly, but the asset totals grouping them
                 are deferred — wait for both rather than showing groups
                 with an empty total that fills in a beat later. -->
            <div
                v-if="props.assets === undefined"
                class="flex items-center justify-center gap-2 py-10 sm:min-w-[640px]"
            >
                <Spinner class="size-4 text-[#989898]" />
                <span class="text-sm text-[#989898]">{{
                    t('finance.calculating')
                }}</span>
            </div>

            <div
                v-for="group in entryGroups"
                v-else
                :key="group.key"
                class="border-t border-white/[0.06] sm:min-w-[640px]"
            >
                <button
                    type="button"
                    class="grid w-full cursor-pointer grid-cols-[20px_minmax(0,1fr)_max-content] items-center gap-2.5 py-3.5 text-start sm:grid-cols-[20px_minmax(140px,1fr)_100px_130px_150px] sm:gap-3.5"
                    @click="toggleGroup(group.key)"
                >
                    <span class="text-[11px] text-[#686868]">{{
                        expandedGroups.has(group.key) ? '⌄' : '›'
                    }}</span>
                    <span class="flex min-w-0 items-center gap-2.5">
                        <span
                            class="size-1.5 shrink-0 rounded-full"
                            :style="{ backgroundColor: group.color }"
                        />
                        <span class="min-w-0 truncate text-sm text-white">{{
                            group.label
                        }}</span>
                    </span>
                    <span
                        class="hidden text-xs text-[#686868] sm:block"
                        dir="ltr"
                    >
                        {{ group.entries.length }}
                    </span>
                    <span
                        class="hidden text-end text-[13px] text-[#989898] tabular-nums sm:block"
                        dir="ltr"
                    >
                        {{ group.qtyDisplay }}
                    </span>
                    <span
                        class="text-end text-[14.5px] text-white tabular-nums"
                        :class="maskClass"
                        dir="ltr"
                    >
                        {{ group.totalFormatted }}
                    </span>
                </button>

                <div v-if="expandedGroups.has(group.key)">
                    <div
                        v-for="entry in group.entries"
                        :key="entry.id"
                        class="group grid grid-cols-[minmax(0,1fr)_max-content] items-center gap-2.5 border-t border-white/[0.04] py-2.5 transition-colors hover:bg-white/[0.02] sm:grid-cols-[20px_minmax(140px,1fr)_100px_130px_150px] sm:gap-3.5"
                    >
                        <span class="hidden sm:block"></span>
                        <span class="min-w-0 text-[12.5px] text-[#686868]">
                            {{
                                entry.kind === 'sell'
                                    ? t('finance.investments.kind_sell')
                                    : t('finance.investments.kind_buy')
                            }}
                            <span
                                class="mt-0.5 block truncate text-[11px] text-[#686868] sm:hidden"
                            >
                                {{ displayDate(entry.occurred_at) }} ·
                                <Ciphered
                                    :value="entry.quantity"
                                    table="investments"
                                    type="decimal"
                                />
                                {{ entry.asset_unit }}
                            </span>
                        </span>
                        <span
                            class="hidden text-xs text-[#686868] sm:block"
                            dir="ltr"
                        >
                            {{ displayDate(entry.occurred_at) }}
                        </span>
                        <span
                            class="hidden text-end text-[12.5px] text-[#989898] tabular-nums sm:block"
                            dir="ltr"
                        >
                            <Ciphered
                                :value="entry.quantity"
                                table="investments"
                                type="decimal"
                            />
                            {{ entry.asset_unit }}
                        </span>
                        <div class="flex items-center justify-end gap-2">
                            <span
                                class="text-[13px] tabular-nums"
                                :class="maskClass"
                                dir="ltr"
                            >
                                <template
                                    v-if="
                                        props.pricesAvailable &&
                                        !isCiphertext(entry.quantity)
                                    "
                                >
                                    {{
                                        formatEntryValue(
                                            Number(entry.quantity),
                                            entry.asset_type,
                                        )
                                    }}
                                </template>
                                <span
                                    v-else-if="pricesResolved"
                                    class="text-xs text-[#686868]"
                                    >{{ t('finance.price_unavailable') }}</span
                                >
                                <span v-else class="text-xs text-[#686868]">{{
                                    t('finance.calculating')
                                }}</span>
                            </span>
                            <!-- Always visible: hover-only actions are invisible
                                 on touch, and undiscoverable everywhere else. -->
                            <div
                                class="flex shrink-0 items-center gap-1 opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100"
                            >
                                <!-- Purchases only. This dialog speaks cost, not
                                     proceeds, so it cannot express a sale — which
                                     is deleted and re-recorded instead. -->
                                <button
                                    v-if="entry.kind !== 'sell'"
                                    type="button"
                                    :aria-label="t('common.edit')"
                                    class="cursor-pointer rounded-md p-1 text-[#6C4EE9] hover:bg-[#6C4EE9]/10"
                                    @click="openEditDialog(entry)"
                                >
                                    <Pencil class="size-3.5" />
                                </button>
                                <button
                                    type="button"
                                    :aria-label="t('common.delete')"
                                    class="cursor-pointer rounded-md p-1 text-[#E94E50] hover:bg-[#E94E50]/10"
                                    @click="requestDeleteEntry(entry.id)"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <InvestmentSellDialog
            v-model:open="isSellDialogOpen"
            :assets="sellableAssets"
            :currencies="props.currencies"
            :selected-currency="props.selectedCurrency"
        />

        <!-- ── Add / Edit dialog ─────────────────────────────────── -->
        <InvestmentEntryDialog
            v-model:open="isDialogOpen"
            :entry="editingEntry"
            :default-asset-key="dialogDefaultAssetKey"
            :asset-types="props.assetTypes"
            :currencies="props.currencies"
            :selected-currency="props.selectedCurrency"
        />
        <ConfirmDeleteModal
            :open="deleteTargetId !== null"
            :title="t('finance.delete.investment_title')"
            :description="t('finance.delete.investment_description')"
            @update:open="deleteTargetId = null"
            @confirm="confirmDeleteEntry"
        />
    </div>
</template>

<script setup lang="ts">
import { Deferred, Head, router, usePage } from '@inertiajs/vue3';
import { Download, Pencil, Plus, Trash2, TrendingUp } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Ciphered from '@/components/Ciphered.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import InvestmentEntryDialog from '@/components/investments/InvestmentEntryDialog.vue';
import InvestmentSellDialog from '@/components/investments/InvestmentSellDialog.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useAmountMask } from '@/composables/useAmountMask';
import { useRelativeTime } from '@/composables/useRelativeTime';
import { formatAppDate } from '@/lib/date';
import { formatCurrencyDisplay, formatCurrencyNumber } from '@/lib/money';
import type { CurrencyCode } from '@/lib/money';
import { dashboard } from '@/routes';
import { index as investmentsIndex } from '@/routes/investments';
import type { Encrypted } from '@/types/vault';
import { isCiphertext } from '@/types/vault';

type AssetKey = string;

type AssetSummary = {
    id: number;
    key: AssetKey;
    label: string;
    icon: string | null;
    icon_svg: string | null;
    color: string;
    unit: string;
    quantity: number;
    quantity_display: string;
    price: number;
    price_available: boolean;
    price_formatted: string;
    value: number;
    value_formatted: string;
    allocation: number;
    /** Average cost per unit, in toman. Null when nothing was ever priced. */
    avg_cost_basis: number | null;
};

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
    kind: 'buy' | 'sell';
    quantity: Encrypted<string | number>;
    cost_basis: Encrypted<string | number> | null;
    cost_basis_currency: string | null;
    note: string | null;
    occurred_at: string;
};

type CurrencyOption = {
    label: string;
    value: string;
};

type MarketPriceAsset = {
    id: number;
    key: AssetKey;
    label: string;
    icon: string | null;
    icon_svg: string | null;
    color: string;
    unit: string;
    price: number;
    price_available: boolean;
    price_formatted: string;
};

type MarketPriceRow = {
    key: string;
    title: string;
    assets: MarketPriceAsset[];
};

const props = defineProps<{
    assets?: AssetSummary[];
    summary?: {
        total_value: number;
        total_value_formatted: string;
        asset_count: number;
        entry_count: number;
    };
    assetTypes: AssetTypeOption[];
    entries: Entry[];
    entryCount: number;
    assetTypeCount: number;
    prices?: Record<AssetKey, number>;
    marketPriceRows?: MarketPriceRow[];
    currencies: CurrencyOption[];
    selectedCurrency: string;
    pricesAvailable?: boolean;
    pricesSyncedAt?: string | null;
}>();

const { formatRelativeTime } = useRelativeTime();
const lastSyncedLabel = computed(() =>
    formatRelativeTime(props.pricesSyncedAt),
);
const { masked } = useAmountMask();
const maskClass = computed(() =>
    masked.value
        ? 'blur-[6px] transition-[filter] duration-150 select-none'
        : 'transition-[filter] duration-150',
);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Investments', href: investmentsIndex() },
        ],
    },
});

const selectedCurrency = ref(props.selectedCurrency);
const page = usePage();
const { t } = useI18n();
const displayCalendar = computed(
    () => (page.props.calendar as string | undefined) ?? 'gregorian',
);
const selectedCurrencyLabel = computed(
    () =>
        props.currencies.find(
            (currency) => currency.value === selectedCurrency.value,
        )?.label ?? t(`finance.currencies.${selectedCurrency.value}`),
);

const pricesResolved = computed(() => props.pricesAvailable !== undefined);
const visibleMarketPriceRows = computed<MarketPriceRow[]>(() =>
    (props.marketPriceRows ?? []).filter((row) => row.assets.length > 0),
);

/** Which market-price tiles the account actually holds, for the "yours" tag —
 *  a held asset reads differently from every other price on the board. */
const heldAssetKeys = computed(
    () =>
        new Set(
            (props.assets ?? [])
                .filter((asset) => asset.quantity > 0)
                .map((asset) => asset.key),
        ),
);

watch(
    () => props.selectedCurrency,
    (value) => {
        selectedCurrency.value = value;
    },
);

const isDialogOpen = ref(false);
const isSellDialogOpen = ref(false);

type EntryGroup = {
    key: AssetKey;
    label: string;
    color: string;
    qtyDisplay: string;
    totalFormatted: string;
    entries: Entry[];
};

/**
 * Entries by asset, driven by `assets` rather than the entries themselves —
 * `assets` already carries the right net quantity and current value (buys
 * minus sells, correctly converted), so this reuses that instead of trying
 * to re-derive a total from raw entries and risking a wrong figure. Assets
 * with no entries at all (nothing bought or sold) are naturally excluded.
 */
const entryGroups = computed<EntryGroup[]>(() => {
    if (props.assets === undefined) {
        return [];
    }

    return props.assets
        .map((asset) => ({
            key: asset.key,
            label: asset.label,
            color: asset.color,
            qtyDisplay: `${asset.quantity_display} ${asset.unit}`,
            totalFormatted: formatCurrencyDisplay(
                asset.value_formatted,
                selectedCurrency.value as CurrencyCode,
            ),
            entries: props.entries.filter(
                (entry) => entry.asset_type === asset.key,
            ),
        }))
        .filter((group) => group.entries.length > 0);
});

const expandedGroups = ref<Set<AssetKey>>(new Set());

function toggleGroup(key: AssetKey): void {
    const next = new Set(expandedGroups.value);

    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }

    expandedGroups.value = next;
}

/**
 * What can actually be sold, with the average cost each unit carries.
 *
 * The basis travels so the disposal can freeze it at sale time — under the vault
 * the server cannot read a single holding, so it has no way to work it out.
 */
const sellableAssets = computed(() =>
    (props.assets ?? [])
        .filter((asset) => asset.quantity > 0)
        .map((asset) => ({
            id: asset.id,
            label: asset.label,
            unit: asset.unit,
            icon: asset.icon,
            icon_svg: asset.icon_svg,
            color: asset.color,
            quantity: asset.quantity,
            avgCostBasis: asset.avg_cost_basis ?? null,
        })),
);
const editingEntry = ref<Entry | null>(null);
const dialogDefaultAssetKey = ref<AssetKey | undefined>();

function openCreateDialog(defaultType?: AssetKey) {
    editingEntry.value = null;
    dialogDefaultAssetKey.value = defaultType;
    isDialogOpen.value = true;
}

function openEditDialog(entry: Entry) {
    editingEntry.value = entry;
    dialogDefaultAssetKey.value = entry.asset_type;
    isDialogOpen.value = true;
}

const deleteTargetId = ref<number | null>(null);

function requestDeleteEntry(investmentId: number) {
    deleteTargetId.value = investmentId;
}

function confirmDeleteEntry() {
    if (!deleteTargetId.value) {
        return;
    }

    router.delete(`/investments/${deleteTargetId.value}`, {
        preserveScroll: true,
    });
    deleteTargetId.value = null;
}

function convertFromToman(amount: number, currency: string): number {
    switch (currency) {
        case 'usd':
            return amount / 150000;
        case 'eur':
            return amount / 150000 / 1.17;
        default:
            return amount;
    }
}

function formatEntryValue(quantity: number, assetType: AssetKey): string {
    const price = (props.prices ?? {})[assetType] ?? 0;
    const valueInToman = quantity * price;
    const converted = convertFromToman(valueInToman, selectedCurrency.value);

    return formatCurrencyDisplay(
        converted,
        selectedCurrency.value as CurrencyCode,
    );
}

function displayDate(value: string): string {
    return formatAppDate(value, displayCalendar.value);
}
</script>
