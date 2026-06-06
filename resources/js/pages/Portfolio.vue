<template>
    <Head title="Portfolio" />

    <div
        class="flex h-full min-h-[calc(100vh-92px)] flex-1 flex-col overflow-x-auto bg-[#111111]"
    >
        <!-- ── Hero / Summary ────────────────────────────────────── -->
        <section class="mx-[18px] mt-5 rounded-[22px] bg-[#1a1a1a] p-5 ring-1 ring-white/10 shadow-[0_18px_45px_rgba(0,0,0,0.2)]">
            <div
                class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between"
            >
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-3">
                        <p
                            class="text-xs font-semibold tracking-[0.35em] text-[#6C4EE9] uppercase"
                        >
                            Portfolio overview
                        </p>
                        <!-- Currency pills -->
                        <div class="flex gap-1">
                            <button
                                v-for="c in props.currencies"
                                :key="c.value"
                                type="button"
                                :class="[
                                    'cursor-pointer rounded-full px-3 py-1 text-xs font-medium transition',
                                    selectedCurrency === c.value
                                        ? 'bg-[#02CD86]/10 text-[#02CD86] ring-1 ring-[#02CD86]/25'
                                        : 'text-[#686868] ring-1 ring-white/10 hover:bg-white/10 hover:text-white',
                                ]"
                                @click="changeCurrency(c.value)"
                            >
                                {{ c.label }}
                            </button>
                        </div>
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

                <div class="grid gap-3 sm:grid-cols-3 xl:min-w-2xl">
                    <!-- Current value -->
                    <div
                        class="rounded-[14px] border border-white/10 bg-[#252525] p-4"
                        style="border-top: 2.5px solid #02CD86"
                    >
                        <p
                            class="text-xs font-medium tracking-[0.2em] text-[#02CD86] uppercase"
                        >
                            Current value
                        </p>
                        <p class="mt-2 text-base font-bold text-white">
                            {{ props.summary.total_current_value_formatted }}
                            <span class="text-xs font-normal text-[#989898]">{{ currencySymbol }}</span>
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
                            Invested
                        </p>
                        <p class="mt-2 text-base font-bold text-white">
                            <template v-if="props.summary.has_cost_basis_data">
                                {{ props.summary.total_cost_basis_formatted }}
                                <span class="text-xs font-normal text-[#989898]">{{ currencySymbol }}</span>
                            </template>
                            <span
                                v-else
                                class="text-sm font-normal text-[#989898]"
                                >No cost basis yet</span
                            >
                        </p>
                    </div>

                    <!-- P&L -->
                    <div
                        class="rounded-[14px] border border-white/10 bg-[#252525] p-4"
                        :style="{ borderTop: props.summary.total_pnl_is_positive === true ? '2.5px solid #02CD86' : props.summary.total_pnl_is_positive === false ? '2.5px solid #E94E50' : '2.5px solid #989898' }"
                    >
                        <p
                            class="text-xs font-medium tracking-[0.2em] uppercase"
                            :class="
                                props.summary.total_pnl_is_positive === true
                                    ? 'text-[#02CD86]'
                                    : props.summary.total_pnl_is_positive ===
                                        false
                                      ? 'text-[#E94E50]'
                                      : 'text-[#989898]'
                            "
                        >
                            P&amp;L
                        </p>
                        <p
                            class="mt-2 text-base font-bold"
                            :class="
                                props.summary.total_pnl_is_positive === true
                                    ? 'text-[#02CD86]'
                                    : props.summary.total_pnl_is_positive ===
                                        false
                                      ? 'text-[#E94E50]'
                                      : 'text-[#989898]'
                            "
                        >
                            <template v-if="props.summary.total_pnl !== null">
                                <span>{{
                                    props.summary.total_pnl_is_positive
                                        ? '+'
                                        : '−'
                                }}</span>
                                {{ props.summary.total_pnl_formatted }} {{ currencySymbol }}
                                <span
                                    v-if="
                                        props.summary.total_pnl_percent !== null
                                    "
                                    class="text-xs font-normal"
                                >
                                    ({{
                                        props.summary.total_pnl_is_positive
                                            ? '+'
                                            : ''
                                    }}{{ props.summary.total_pnl_percent }}%)
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
            </div>
        </section>

        <!-- ── Asset breakdown table ─────────────────────────────── -->
        <div
            v-if="props.assets.length > 0"
            class="mx-[18px] mt-[18px] overflow-hidden rounded-[22px] bg-[#1a1a1a] ring-1 ring-white/10 shadow-[0_18px_45px_rgba(0,0,0,0.2)]"
        >
            <div class="px-5 py-[29px]">
                <h2 class="text-[22px] leading-none font-normal text-white">
                    Per-asset breakdown
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
                                Asset
                            </th>
                            <th
                                class="bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                            >
                                Holdings
                            </th>
                            <th
                                class="hidden bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:table-cell sm:px-5"
                            >
                                Avg cost / unit
                            </th>
                            <th
                                class="bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                            >
                                Current value
                            </th>
                            <th
                                class="rounded-r-2xl bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                            >
                                P&amp;L
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="asset in props.assets"
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
                                {{ asset.current_value_formatted }}
                                <span class="text-xs font-normal text-[#989898]">{{ currencySymbol }}</span>
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
                                <template v-if="asset.pnl !== null">
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
            v-if="props.entries.length > 0"
            class="mx-[18px] my-[18px] overflow-hidden rounded-[22px] bg-[#1a1a1a] ring-1 ring-white/10 shadow-[0_18px_45px_rgba(0,0,0,0.2)]"
        >
            <div class="px-5 py-[29px]">
                <h2 class="text-[22px] leading-none font-normal text-white">
                    Entry-level detail
                </h2>
                <p class="mt-1 text-sm text-[#989898]">
                    Every investment record with current value and cost-basis
                    P/L.
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
                                Asset
                            </th>
                            <th
                                class="bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                            >
                                Qty
                            </th>
                            <th
                                class="hidden bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:table-cell sm:px-5"
                            >
                                Cost basis / u
                            </th>
                            <th
                                class="bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                            >
                                Current value
                            </th>
                            <th
                                class="bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:px-5"
                            >
                                P&amp;L
                            </th>
                            <th
                                class="hidden rounded-r-2xl bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] sm:table-cell sm:px-5"
                            >
                                Date
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="entry in props.entries"
                            :key="entry.id"
                            class="group"
                        >
                            <td
                                class="px-3 py-[14px] text-center text-[16px] leading-none text-white sm:px-5"
                            >
                                <span class="mr-1">{{ entry.asset_icon }}</span>
                                {{ entry.asset_label }}
                            </td>
                            <td
                                class="px-3 py-[14px] text-center text-[16px] leading-none text-white sm:px-5"
                            >
                                {{ entry.quantity }}
                                <span class="text-xs text-[#989898]">{{
                                    entry.asset_unit
                                }}</span>
                            </td>
                            <td
                                class="hidden px-3 py-[14px] text-center sm:table-cell sm:px-5"
                            >
                                <span
                                    v-if="entry.cost_basis !== null"
                                    class="text-[15px] text-white"
                                >
                                    {{ formatMoney(entry.cost_basis) }}
                                    {{ (entry.cost_basis_currency ?? 'toman').toUpperCase() }}
                                </span>
                                <span v-else class="text-sm text-[#989898]">
                                    —
                                </span>
                            </td>
                            <td
                                class="px-3 py-[14px] text-center text-[16px] leading-none font-bold text-white sm:px-5"
                            >
                                {{ entry.current_value_fmt }}
                                <span class="text-xs font-normal text-[#989898]">{{ currencySymbol }}</span>
                            </td>
                            <td
                                class="px-3 py-[14px] text-center text-[16px] leading-none font-bold sm:px-5"
                                :class="
                                    entry.pnl_is_positive === true
                                        ? 'text-[#02CD86]'
                                        : entry.pnl_is_positive === false
                                          ? 'text-[#E94E50]'
                                          : 'text-[#989898]'
                                "
                            >
                                <template v-if="entry.pnl !== null">
                                    {{ entry.pnl_is_positive ? '+' : '−' }}
                                    {{ entry.pnl_formatted }} {{ currencySymbol }}
                                </template>
                                <span v-else>—</span>
                            </td>
                            <td
                                class="hidden px-3 py-[14px] text-center text-[16px] leading-none text-[#989898] sm:table-cell sm:px-5"
                            >
                                {{ entry.occurred_at }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Empty state ───────────────────────────────────────── -->
        <div
            v-if="props.assets.length === 0"
            class="mx-[18px] my-[18px] flex flex-col items-center justify-center rounded-[22px] bg-[#1a1a1a] px-8 py-20 ring-1 ring-white/10"
        >
            <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#f0ecff]">
                <Wallet class="size-8 text-[#6C4EE9]" />
            </span>
            <h2 class="mt-4 text-xl font-semibold text-white">
                Portfolio is empty
            </h2>
            <p class="mt-2 max-w-sm text-center text-sm text-[#989898]">
                Head over to the Investments page and add your first entry. Come
                back here to track your profit &amp; loss once you add cost
                basis info.
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Wallet } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
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

type PortfolioEntry = {
    id: number;
    asset_type: AssetKey;
    asset_label: string;
    asset_icon: string;
    asset_color: string;
    asset_unit: string;
    quantity: number;
    cost_basis: number | null;
    cost_basis_currency: string | null;
    current_price: number;
    current_price_fmt: string;
    current_value: number;
    current_value_fmt: string;
    pnl: number | null;
    pnl_formatted: string | null;
    pnl_is_positive: boolean | null;
    note: string | null;
    occurred_at: string;
};

const props = defineProps<{
    assets: PortfolioAsset[];
    entries: PortfolioEntry[];
    summary: {
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
}>();

const selectedCurrency = ref(props.selectedCurrency);

const currencySymbol = computed(() => {
    switch (selectedCurrency.value) {
        case 'usd': return '$';
        case 'eur': return '€';
        default: return 'T';
    }
});

function changeCurrency(currency: string) {
    if (currency === selectedCurrency.value) {
        return;
    }

    selectedCurrency.value = currency;
    router.get(
        portfolio.url({ query: { currency } }),
        {},
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

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

function formatMoney(val: number): string {
    return new Intl.NumberFormat('en-US').format(Math.round(val));
}
</script>
