<template>
    <Head :title="t('finance.portfolio.title')" />

    <div
        class="flex h-full min-h-[calc(100vh-92px)] flex-1 flex-col overflow-x-auto bg-[#111111]"
    >
        <!-- ── Hero / Summary ────────────────────────────────────── -->
        <section class="mx-[18px] mt-5 rounded-[22px] bg-[#1a1a1a] p-5 ring-1 ring-white/10 shadow-[0_18px_45px_rgba(0,0,0,0.2)]">
            <div
                class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between"
            >
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <p
                            class="text-xs font-semibold tracking-[0.35em] text-[#6C4EE9] uppercase"
                        >
                            {{ t('finance.portfolio.overview') }}
                        </p>
                        <a
                            :href="`/portfolio/export?currency=${selectedCurrency}`"
                            class="inline-flex items-center gap-1.5 rounded-full bg-white/8 px-3 py-1 text-xs text-white/70 ring-1 ring-white/15 transition-colors hover:bg-white/15 hover:text-white"
                        >
                            <Download class="size-3" />
                            Export P&amp;L
                        </a>
                    </div>
                    <h1
                        class="text-3xl font-semibold tracking-tight text-white sm:text-4xl"
                    >
                        Your holdings, cost basis &amp; P/L at a glance.
                    </h1>
                    <p class="text-sm text-[#989898]">
                        Add a cost basis when logging investment entries to
                        unlock profit/loss tracking here.
                    </p>
                </div>

                <Deferred :data="['assets', 'summary', 'pricesAvailable']">
                    <template #fallback>
                        <div class="grid gap-3 sm:grid-cols-3 xl:min-w-2xl">
                            <div
                                v-for="i in 3"
                                :key="i"
                                class="flex items-center justify-center rounded-[14px] border border-white/10 bg-[#252525] p-4"
                            >
                                <Spinner class="size-5 text-[#989898]" />
                                <span class="ml-2 text-sm text-[#989898]">{{ t('finance.calculating') }}</span>
                            </div>
                        </div>
                    </template>

                <div class="grid gap-3 sm:grid-cols-3 xl:min-w-2xl">
                    <!-- Current value -->
                    <div
                        class="rounded-[14px] border border-white/10 bg-[#252525] p-4"
                        style="border-top: 2.5px solid #02CD86"
                    >
                        <p
                            class="text-xs font-medium tracking-[0.2em] text-[#02CD86] uppercase"
                        >
                            {{ t('finance.portfolio.current_value') }}
                        </p>
                        <p class="mt-2 text-base font-bold text-white">
                            <template v-if="props.pricesAvailable">
                                {{ summary.total_current_value_formatted }}
                                <span class="text-xs font-normal text-[#989898]">{{ currencySymbol }}</span>
                            </template>
                            <span v-else class="text-sm font-normal text-[#989898]">{{ t('finance.price_unavailable') }}</span>
                        </p>
                    </div>

                    <!-- Cost basis -->
                    <div
                        class="rounded-[14px] border border-white/10 bg-[#252525] p-4"
                        style="border-top: 2.5px solid #989898"
                    >
                        <p
                            class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                        >
                            {{ t('finance.portfolio.invested') }}
                        </p>
                        <p class="mt-2 text-base font-bold text-white">
                            <template v-if="summary.has_cost_basis_data">
                                {{ summary.total_cost_basis_formatted }}
                                <span class="text-xs font-normal text-[#989898]">{{ currencySymbol }}</span>
                            </template>
                            <span
                                v-else
                                class="text-sm font-normal text-[#989898]"
                                >{{ t('finance.portfolio.no_cost_basis') }}</span
                            >
                        </p>
                    </div>

                    <!-- P&L -->
                    <div
                        class="rounded-[14px] border border-white/10 bg-[#252525] p-4"
                        :style="{ borderTop: summary.total_pnl_is_positive === true ? '2.5px solid #02CD86' : summary.total_pnl_is_positive === false ? '2.5px solid #E94E50' : '2.5px solid #989898' }"
                    >
                        <p
                            class="text-xs font-medium tracking-[0.2em] uppercase"
                            :class="
                                summary.total_pnl_is_positive === true
                                    ? 'text-[#02CD86]'
                                    : summary.total_pnl_is_positive ===
                                        false
                                      ? 'text-[#E94E50]'
                                      : 'text-[#989898]'
                            "
                        >
                            {{ t('finance.portfolio.profit_loss') }}
                        </p>
                        <p
                            class="mt-2 text-base font-bold"
                            :class="
                                summary.total_pnl_is_positive === true
                                    ? 'text-[#02CD86]'
                                    : summary.total_pnl_is_positive ===
                                        false
                                      ? 'text-[#E94E50]'
                                      : 'text-[#989898]'
                            "
                        >
                            <span
                                v-if="!props.pricesAvailable"
                                class="text-sm font-normal text-[#989898]"
                                >{{ t('finance.price_unavailable') }}</span
                            >
                            <template v-else-if="summary.total_pnl !== null">
                                <span>{{
                                    summary.total_pnl_is_positive
                                        ? '+'
                                        : '−'
                                }}</span>
                                {{ summary.total_pnl_formatted }} {{ currencySymbol }}
                                <span
                                    v-if="
                                        summary.total_pnl_percent !== null
                                    "
                                    class="text-xs font-normal"
                                >
                                    ({{
                                        summary.total_pnl_is_positive
                                            ? '+'
                                            : ''
                                    }}{{ summary.total_pnl_percent }}%)
                                </span>
                            </template>
                            <span
                                v-else
                                class="text-sm font-normal text-[#989898]"
                                >—</span
                            >
                        </p>
                    </div>
                </div>
                </Deferred>
            </div>
        </section>

        <Deferred :data="['assets', 'summary', 'pricesAvailable']">
            <template #fallback>
                <div class="mx-[18px] my-[18px] flex flex-col items-center justify-center rounded-[22px] bg-[#1a1a1a] px-8 py-20 ring-1 ring-white/10">
                    <Spinner class="size-8 text-[#02CD86]" />
                    <p class="mt-4 text-sm text-[#989898]">{{ t('finance.calculating') }}</p>
                </div>
            </template>

        <!-- ── Allocation chart ──────────────────────────────────── -->
        <div
            v-if="assets.length > 0"
            class="mx-[18px] mt-[18px] overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 ring-1 ring-white/10 shadow-[0_18px_45px_rgba(0,0,0,0.2)] xl:max-w-[420px]"
        >
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-[18px] leading-none font-normal text-white">
                    {{ t('finance.investments.allocation') }}
                </h2>
                <span class="text-xs text-[#989898]">{{
                    t('finance.investments.by_current_value')
                }}</span>
            </div>
            <DonutChart
                :series="allocationDonut.series"
                :labels="allocationDonut.labels"
                :colors="allocationDonut.colors"
                :center-label="t('finance.portfolio.current_value')"
                :center-value="
                    props.pricesAvailable
                        ? summary.total_current_value_formatted + ' ' + currencySymbol
                        : t('finance.price_unavailable')
                "
            />
        </div>

        <!-- ── Asset breakdown table ─────────────────────────────── -->
        <div
            v-if="assets.length > 0"
            class="mx-[18px] mt-[18px] overflow-hidden rounded-[22px] bg-[#1a1a1a] ring-1 ring-white/10 shadow-[0_18px_45px_rgba(0,0,0,0.2)]"
        >
            <div class="px-5 py-[29px]">
                <h2 class="text-[22px] leading-none font-normal text-white">
                    {{ t('finance.portfolio.per_asset_breakdown') }}
                </h2>
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
                                {{ t('finance.fields.holdings') }}
                            </th>
                            <th
                                class="hidden bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:table-cell sm:px-5"
                            >
                                {{ t('finance.fields.avg_cost_per_unit') }}
                            </th>
                            <th
                                class="bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                            >
                                {{ t('finance.portfolio.current_value') }}
                            </th>
                            <th
                                class="rounded-r-2xl bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                            >
                                {{ t('finance.portfolio.profit_loss') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="asset in assets"
                            :key="asset.key"
                            class="group"
                        >
                            <td
                                class="px-3 py-[17px] text-center text-[17px] leading-none font-normal text-white sm:px-5"
                            >
                                <span class="mr-1">{{ asset.icon }}</span>
                                {{ asset.label }}
                            </td>
                            <td
                                class="px-3 py-[17px] text-center text-[17px] leading-none font-normal text-white sm:px-5"
                            >
                                {{ asset.quantity }}
                                <span class="text-xs text-[#989898]">{{
                                    asset.unit
                                }}</span>
                            </td>
                            <td
                                class="hidden px-3 py-[17px] text-center sm:table-cell sm:px-5"
                            >
                                <span
                                    v-if="asset.avg_cost_basis_formatted"
                                    class="inline-flex min-w-[118px] justify-center rounded-md bg-white/10 px-4 py-2 text-[15px] leading-none font-normal text-white"
                                >
                                    {{ asset.avg_cost_basis_formatted }} {{ currencySymbol }}
                                </span>
                                <span v-else class="text-sm text-[#989898]">
                                    —
                                </span>
                            </td>
                            <td
                                class="px-3 py-[17px] text-center text-[17px] leading-none font-bold text-white sm:px-5"
                            >
                                <template v-if="props.pricesAvailable">
                                    {{ asset.current_value_formatted }}
                                    <span class="text-xs font-normal text-[#989898]">{{ currencySymbol }}</span>
                                </template>
                                <span v-else class="text-sm font-normal text-[#989898]">{{ t('finance.price_unavailable') }}</span>
                            </td>
                            <td
                                class="px-3 py-[17px] text-center text-[17px] leading-none font-bold sm:px-5"
                                :class="
                                    asset.pnl_is_positive === true
                                        ? 'text-[#02CD86]'
                                        : asset.pnl_is_positive === false
                                          ? 'text-[#E94E50]'
                                          : 'text-[#989898]'
                                "
                            >
                                <span
                                    v-if="!props.pricesAvailable"
                                    class="text-sm font-normal text-[#989898]"
                                    >{{ t('finance.price_unavailable') }}</span
                                >
                                <template v-else-if="asset.pnl !== null">
                                    {{ asset.pnl_is_positive ? '+' : '−' }}
                                    {{ asset.pnl_formatted }} {{ currencySymbol }}
                                    <span
                                        v-if="asset.pnl_percent !== null"
                                        class="block text-xs font-normal"
                                    >
                                        ({{ asset.pnl_is_positive ? '+' : ''
                                        }}{{ asset.pnl_percent }}%)
                                    </span>
                                </template>
                                <span v-else>—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Per-entry breakdown ───────────────────────────────── -->
        <div
            v-if="assets.length > 0"
            class="mx-[18px] my-[18px] overflow-hidden rounded-[22px] bg-[#1a1a1a] ring-1 ring-white/10 shadow-[0_18px_45px_rgba(0,0,0,0.2)]"
        >
            <div class="px-5 py-[29px]">
                <h2 class="text-[22px] leading-none font-normal text-white">
                    {{ t('finance.portfolio.entry_level_detail') }}
                </h2>
                <p class="mt-1 text-sm text-[#989898]">
                    {{ t('finance.portfolio.entry_history_description') }}
                </p>
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
                                {{ t('finance.fields.quantity_short') }}
                            </th>
                            <th
                                class="hidden bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:table-cell sm:px-5"
                            >
                                {{ t('finance.fields.cost_basis_per_unit') }}
                            </th>
                            <th
                                class="hidden bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] lg:table-cell lg:px-5"
                            >
                                Total cost basis
                            </th>
                            <th
                                class="bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                            >
                                {{ t('finance.portfolio.current_value') }}
                            </th>
                            <th
                                class="bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                            >
                                {{ t('finance.portfolio.profit_loss') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="asset in assets"
                            :key="asset.key"
                            class="group"
                        >
                            <td
                                class="px-3 py-[14px] text-center text-[16px] leading-none text-white sm:px-5"
                            >
                                <span class="mr-1">{{ asset.icon }}</span>
                                {{ asset.label }}
                            </td>
                            <td
                                class="px-3 py-[14px] text-center text-[16px] leading-none text-white sm:px-5"
                            >
                                {{ asset.quantity }}
                                <span class="text-xs text-[#989898]">{{
                                    asset.unit
                                }}</span>
                            </td>
                            <td
                                class="hidden px-3 py-[14px] text-center sm:table-cell sm:px-5"
                            >
                                <span
                                    v-if="asset.avg_cost_basis_formatted"
                                    class="text-[15px] text-white"
                                >
                                    {{ asset.avg_cost_basis_formatted }}
                                    <span class="text-xs font-normal text-[#989898]">{{ currencySymbol }}</span>
                                </span>
                                <span v-else class="text-sm text-[#989898]">—</span>
                            </td>
                            <td
                                class="hidden px-3 py-[14px] text-center lg:table-cell lg:px-5"
                            >
                                <span
                                    v-if="asset.total_cost_formatted"
                                    class="text-[15px] text-white"
                                >
                                    {{ asset.total_cost_formatted }}
                                    <span class="text-xs text-[#989898]">{{ currencySymbol }}</span>
                                </span>
                                <span v-else class="text-sm text-[#989898]">—</span>
                            </td>
                            <td
                                class="px-3 py-[14px] text-center text-[16px] leading-none font-bold text-white sm:px-5"
                            >
                                <template v-if="props.pricesAvailable">
                                    {{ asset.current_value_formatted }}
                                    <span class="text-xs font-normal text-[#989898]">{{ currencySymbol }}</span>
                                </template>
                                <span v-else class="text-sm font-normal text-[#989898]">{{ t('finance.price_unavailable') }}</span>
                            </td>
                            <td
                                class="px-3 py-[14px] text-center text-[16px] leading-none font-bold sm:px-5"
                                :class="
                                    asset.pnl_is_positive === true
                                        ? 'text-[#02CD86]'
                                        : asset.pnl_is_positive === false
                                          ? 'text-[#E94E50]'
                                          : 'text-[#989898]'
                                "
                            >
                                <span
                                    v-if="!props.pricesAvailable"
                                    class="text-sm font-normal text-[#989898]"
                                    >{{ t('finance.price_unavailable') }}</span
                                >
                                <template v-else-if="asset.pnl !== null">
                                    {{ asset.pnl_is_positive ? '+' : '−' }}
                                    {{ asset.pnl_formatted }} {{ currencySymbol }}
                                </template>
                                <span v-else>—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Empty state ───────────────────────────────────────── -->
        <div
            v-if="assets.length === 0"
            class="mx-[18px] my-[18px] flex flex-col items-center justify-center rounded-[22px] bg-[#1a1a1a] px-8 py-20 ring-1 ring-white/10"
        >
            <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#24212f]">
                <Wallet class="size-8 text-[#6C4EE9]" />
            </span>
            <h2 class="mt-4 text-xl font-semibold text-white">
                {{ t('finance.portfolio.empty') }}
            </h2>
            <p class="mt-2 max-w-sm text-center text-sm text-[#989898]">
                Head over to the Investments page and add your first entry. Come
                back here to track your profit &amp; loss once you add cost
                basis info.
            </p>
        </div>
        </Deferred>
    </div>
</template>

<script setup lang="ts">
import { Deferred, Head } from '@inertiajs/vue3';
import { Download, Wallet } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import DonutChart from '@/components/charts/DonutChart.vue';
import { Spinner } from '@/components/ui/spinner';
import { dashboard, portfolio } from '@/routes';

type AssetKey = 'gold' | 'silver' | 'usd' | 'eur' | 'coin' | 'bitcoin';

type CurrencyOption = {
    label: string;
    value: string;
};

type PortfolioAsset = {
    key: AssetKey;
    label: string;
    icon: string;
    color: string;
    unit: string;
    quantity: number;
    current_price: number;
    current_price_formatted: string;
    current_value: number;
    current_value_formatted: string;
    avg_cost_basis: number | null;
    avg_cost_basis_formatted: string | null;
    total_cost: number | null;
    total_cost_formatted: string | null;
    pnl: number | null;
    pnl_formatted: string | null;
    pnl_percent: number | null;
    pnl_is_positive: boolean | null;
    entries_count: number;
};

const props = defineProps<{
    assets?: PortfolioAsset[];
    summary?: {
        total_current_value: number;
        total_current_value_formatted: string;
        total_cost_basis: number;
        total_cost_basis_formatted: string;
        total_pnl: number | null;
        total_pnl_formatted: string | null;
        total_pnl_percent: number | null;
        total_pnl_is_positive: boolean | null;
        has_cost_basis_data: boolean;
        asset_count: number;
    };
    currencies: CurrencyOption[];
    selectedCurrency: string;
    pricesAvailable?: boolean;
}>();

const assets = computed(() => props.assets ?? []);
const summary = computed(
    () =>
        props.summary ?? {
            total_current_value: 0,
            total_current_value_formatted: '0',
            total_cost_basis: 0,
            total_cost_basis_formatted: '0',
            total_pnl: null,
            total_pnl_formatted: null,
            total_pnl_percent: null,
            total_pnl_is_positive: null,
            has_cost_basis_data: false,
            asset_count: 0,
        },
);

const selectedCurrency = ref(props.selectedCurrency);
const { t } = useI18n();

const currencySymbol = computed(() => {
    switch (selectedCurrency.value) {
        case 'usd': return '$';
        case 'eur': return '€';
        default: return 'T';
    }
});

const allocationDonut = computed(() => ({
    series: assets.value.map((asset) => asset.current_value),
    labels: assets.value.map((asset) => asset.label),
    colors: assets.value.map((asset) => asset.color),
}));

watch(
    () => props.selectedCurrency,
    (value) => {
        selectedCurrency.value = value;
    },
);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Portfolio', href: portfolio() },
        ],
    },
});

</script>
