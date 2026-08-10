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
        <!-- ── Page actions ──────────────────────────────────────── -->
        <div
            class="flex flex-wrap items-center gap-x-3 gap-y-2 px-[18px] pt-[18px]"
        >
            <span
                v-if="lastSyncedLabel"
                class="max-w-full min-w-0 text-xs break-words text-[#989898]"
            >
                {{ t('finance.last_synced', { time: lastSyncedLabel }) }}
            </span>
        </div>

        <!-- ── Summary stat cards ────────────────────────────────── -->
        <div class="grid gap-[18px] px-[18px] pt-3 md:grid-cols-3">
            <article
                class="kpi-card-income overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <p
                    class="text-xs font-medium tracking-[0.2em] text-[#02CD86] uppercase"
                >
                    {{ t('finance.investments.total_portfolio_value') }}
                </p>
                <p class="mt-3 text-2xl font-bold text-white">
                    <template v-if="props.pricesAvailable">
                        {{ props.summary?.total_value_formatted }}
                        <span class="text-sm font-normal text-[#989898]">{{
                            currencySymbol
                        }}</span>
                    </template>
                    <span
                        v-else-if="pricesResolved"
                        class="text-base font-medium text-[#989898]"
                        >{{ t('finance.price_unavailable') }}</span
                    >
                    <span v-else class="text-base font-medium text-[#989898]">{{
                        t('finance.calculating')
                    }}</span>
                </p>
            </article>

            <article
                class="kpi-card-cost overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <p
                    class="text-xs font-medium tracking-[0.2em] text-[#6C4EE9] uppercase"
                >
                    {{ t('finance.investments.asset_types') }}
                </p>
                <p class="mt-3 text-2xl font-bold text-white">
                    {{ props.assetTypeCount }}
                    <span class="text-sm font-normal text-[#989898]">{{
                        t('finance.investments.held')
                    }}</span>
                </p>
            </article>

            <article
                class="kpi-card-neutral overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <p
                    class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                >
                    {{ t('finance.investments.total_entries') }}
                </p>
                <p class="mt-3 text-2xl font-bold text-white">
                    {{ props.entryCount }}
                    <span class="text-sm font-normal text-[#989898]">{{
                        t('finance.investments.records')
                    }}</span>
                </p>
            </article>
        </div>

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
                    class="mx-[18px] my-[18px] flex flex-col items-center justify-center rounded-[22px] bg-[#1a1a1a] px-8 py-20 ring-1 ring-white/10"
                >
                    <Spinner class="size-8 text-[#02CD86]" />
                    <p class="mt-4 text-sm text-[#989898]">
                        {{ t('finance.calculating') }}
                    </p>
                </div>
            </template>

            <section
                v-if="visibleMarketPriceRows.length > 0"
                class="mx-[18px] mt-[18px] overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div
                    class="mb-5 flex flex-wrap items-end justify-between gap-3"
                >
                    <div>
                        <p
                            class="text-xs font-medium tracking-[0.2em] text-[#02CD86] uppercase"
                        >
                            {{ t('finance.investments.market_prices_kicker') }}
                        </p>
                        <h2 class="mt-2 text-[22px] leading-none text-white">
                            {{ t('finance.investments.market_prices_title') }}
                        </h2>
                    </div>
                    <span class="text-xs text-[#989898]">
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
                            class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5"
                        >
                            <article
                                v-for="asset in row.assets"
                                :key="asset.key"
                                class="min-w-0 rounded-2xl bg-[#111111] p-4 ring-1 ring-white/10"
                                :style="{
                                    borderTop: `2.5px solid ${asset.color}`,
                                }"
                            >
                                <div class="flex items-center gap-3">
                                    <AssetIcon
                                        :icon="asset.icon"
                                        :icon-svg="asset.icon_svg"
                                        :label="asset.label"
                                        :color="asset.color"
                                        size="sm"
                                    />
                                    <div class="min-w-0">
                                        <p
                                            class="truncate text-sm font-semibold text-white"
                                        >
                                            {{ asset.label }}
                                        </p>
                                        <p class="text-xs text-[#989898]">
                                            {{
                                                t(
                                                    'finance.investments.per_unit',
                                                    { unit: asset.unit },
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                                <p class="mt-4 text-lg font-bold text-white">
                                    <template v-if="asset.price_available">
                                        {{ asset.price_formatted }}
                                        <span
                                            class="text-xs font-normal text-[#989898]"
                                            >{{ currencySymbol }}</span
                                        >
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
                class="mx-[18px] my-[18px] flex flex-col items-center justify-center rounded-[22px] bg-[#1a1a1a] px-8 py-20 ring-1 ring-white/10"
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

            <!-- ── Asset summary cards ───────────────────────────────── -->
            <div
                v-if="(props.assets ?? []).length > 0"
                class="grid grid-cols-2 gap-[18px] px-[18px] sm:grid-cols-3 xl:grid-cols-6 pt-4"
            >
                <div
                    v-for="asset in props.assets ?? []"
                    :key="asset.key"
                    class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-4 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                    :style="{ borderTop: `2.5px solid ${asset.color}` }"
                >
                    <div class="mb-2 flex items-center justify-between">
                        <AssetIcon
                            :icon="asset.icon"
                            :icon-svg="asset.icon_svg"
                            :label="asset.label"
                            :color="asset.color"
                            size="lg"
                        />
                        <span
                            class="rounded-md px-2 py-0.5 text-xs font-semibold text-white"
                            :style="{ backgroundColor: asset.color }"
                        >
                            <template v-if="props.pricesAvailable"
                                >{{ asset.allocation }}%</template
                            >
                            <template v-else>{{
                                t('finance.price_unavailable')
                            }}</template>
                        </span>
                    </div>
                    <p class="text-sm font-semibold text-white">
                        {{ asset.label }}
                    </p>
                    <p class="mt-0.5 text-xs text-[#989898]">
                        {{ asset.quantity_display }} {{ asset.unit }}
                    </p>
                    <p class="mt-2 text-sm font-bold text-white">
                        <template v-if="props.pricesAvailable">
                            {{ asset.value_formatted }}
                            <span class="text-xs font-normal text-[#989898]">{{
                                currencySymbol
                            }}</span>
                        </template>
                        <span
                            v-else
                            class="text-xs font-medium text-[#989898]"
                            >{{ t('finance.price_unavailable') }}</span
                        >
                    </p>
                </div>
            </div>
        </Deferred>

        <!-- ── Recent entries table ──────────────────────────────── -->
        <div
            v-if="props.entries.length > 0"
            class="mx-[18px] mt-[18px] mb-[38px] overflow-hidden rounded-[22px] bg-[#1a1a1a] shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-4 px-5 py-[29px]"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-[22px] leading-none font-normal text-white">
                        {{ t('finance.investments.investment_entries') }}
                    </h2>
                    <a
                        :href="`/investments/export?currency=${props.selectedCurrency}`"
                        class="inline-flex shrink-0 items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm whitespace-nowrap text-white ring-1 ring-white/20 transition-colors hover:bg-white/15"
                    >
                        <Download class="size-4" />
                        Export
                    </a>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Selling never deletes the purchase — it writes a disposal
                         row, so history and cost basis both survive.

                         Sized like Add Entry beside it, but in the cost gradient
                         Add Cost uses on the dashboard and transactions pages —
                         money leaving is the same kind of action either way. -->
                    <Button
                        v-if="sellableAssets.length > 0"
                        class="h-12 w-max justify-between rounded-md bg-[linear-gradient(90deg,#947BFF_0%,#6C4EE9_100%)] px-3.5 text-lg font-bold text-white shadow-[0_10px_20px_rgba(108,78,233,0.22)] transition hover:brightness-105"
                        @click="isSellDialogOpen = true"
                    >
                        <span>{{ t('finance.investments.sell') }}</span>
                        <span
                            class="ml-2 grid h-[1.55em] w-[1.55em] shrink-0 place-items-center rounded-md border border-white/25 bg-[linear-gradient(135deg,rgba(255,255,255,0.24)_0%,rgba(45,45,45,0.72)_100%)] shadow-[inset_0_1px_0_rgba(255,255,255,0.22)]"
                        >
                            <Minus class="size-4" />
                        </span>
                    </Button>
                    <Button
                        class="h-12 w-max justify-between rounded-md bg-[linear-gradient(90deg,#02CD86_0%,#00a36e_100%)] px-3.5 text-lg font-bold text-[#101010] shadow-[0_10px_20px_rgba(2,205,134,0.22)] hover:brightness-105"
                        @click="openCreateDialog()"
                    >
                        <span>{{ t('finance.actions.add_entry') }}</span>
                        <span
                            class="ml-2 grid h-[1.55em] w-[1.55em] shrink-0 place-items-center rounded-md border border-white/25 bg-[linear-gradient(135deg,rgba(255,255,255,0.24)_0%,rgba(45,45,45,0.72)_100%)] shadow-[inset_0_1px_0_rgba(255,255,255,0.22)]"
                        >
                            <Plus class="size-4" />
                        </span>
                    </Button>
                </div>
            </div>

            <div class="overflow-x-auto px-3 pb-5">
                <table
                    class="w-full border-separate border-spacing-y-0 text-sm"
                >
                    <thead>
                        <tr class="text-base">
                            <th
                                class="rounded-l-2xl bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                            >
                                {{ t('finance.fields.asset') }}
                            </th>
                            <th
                                class="bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                            >
                                {{ t('finance.fields.quantity') }}
                            </th>
                            <th
                                class="bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                            >
                                {{ t('finance.fields.value') }}
                            </th>
                            <th
                                class="hidden bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:table-cell sm:px-5"
                            >
                                {{ t('finance.fields.date') }}
                            </th>
                            <th
                                class="rounded-r-2xl bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                            ></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="entry in props.entries"
                            :key="entry.id"
                            class="group"
                        >
                            <td
                                class="px-3 py-[14px] text-center text-[16px] leading-none font-normal text-white sm:px-5"
                            >
                                <AssetIcon
                                    :icon="entry.asset_icon"
                                    :icon-svg="entry.asset_icon_svg"
                                    :label="entry.asset_label"
                                    :color="entry.asset_color"
                                    size="sm"
                                />
                                {{ entry.asset_label }}
                            </td>
                            <td
                                class="px-3 py-[14px] text-center text-[16px] leading-none font-normal text-white sm:px-5"
                            >
                                <Ciphered
                                    :value="entry.quantity"
                                    table="investments"
                                    type="decimal"
                                />
                                {{ entry.asset_unit }}
                            </td>
                            <td
                                class="px-3 py-[14px] text-center text-[16px] leading-none font-normal text-white sm:px-5"
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
                                    <span class="text-xs text-[#989898]">{{
                                        currencySymbol
                                    }}</span>
                                </template>
                                <span
                                    v-else-if="pricesResolved"
                                    class="text-xs text-[#989898]"
                                    >{{ t('finance.price_unavailable') }}</span
                                >
                                <span v-else class="text-xs text-[#989898]">{{
                                    t('finance.calculating')
                                }}</span>
                            </td>
                            <td
                                class="hidden px-3 py-[14px] text-center text-[16px] leading-none font-normal text-[#989898] sm:table-cell sm:px-5"
                            >
                                {{ displayDate(entry.occurred_at) }}
                            </td>
                            <td class="px-3 py-[14px] text-center sm:px-5">
                                <!-- Always visible: hover-only actions are invisible
                                     on touch, and undiscoverable everywhere else. -->
                                <div
                                    class="flex items-center justify-center gap-2"
                                >
                                    <button
                                        type="button"
                                        class="rounded-md bg-white/5 px-2 py-1 text-xs text-[#6C4EE9] ring-1 ring-white/10 hover:bg-white/10"
                                        @click="openEditDialog(entry)"
                                    >
                                        {{ t('common.edit') }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md p-1.5 hover:bg-[#fff0f0]"
                                        @click="requestDeleteEntry(entry.id)"
                                    >
                                        <Trash2
                                            class="size-3.5 text-[#E94E50]"
                                        />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
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
import { Download, Minus, Plus, Trash2, TrendingUp } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AssetIcon from '@/components/AssetIcon.vue';
import Ciphered from '@/components/Ciphered.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import InvestmentEntryDialog from '@/components/investments/InvestmentEntryDialog.vue';
import InvestmentSellDialog from '@/components/investments/InvestmentSellDialog.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useRelativeTime } from '@/composables/useRelativeTime';
import { formatAppDate } from '@/lib/date';
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

const currencySymbol = computed(() => {
    switch (selectedCurrency.value) {
        case 'usd':
            return '$';
        case 'eur':
            return '€';
        default:
            return 'T';
    }
});

const pricesResolved = computed(() => props.pricesAvailable !== undefined);
const visibleMarketPriceRows = computed<MarketPriceRow[]>(() =>
    (props.marketPriceRows ?? []).filter((row) => row.assets.length > 0),
);

watch(
    () => props.selectedCurrency,
    (value) => {
        selectedCurrency.value = value;
    },
);

const isDialogOpen = ref(false);
const isSellDialogOpen = ref(false);

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
    const decimals = selectedCurrency.value === 'toman' ? 0 : 2;

    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(converted);
}

function displayDate(value: string): string {
    return formatAppDate(value, displayCalendar.value);
}
</script>
