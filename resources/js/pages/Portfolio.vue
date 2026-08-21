<template>
    <Head :title="t('finance.portfolio.title')" />

    <div
        class="flex min-h-[calc(100vh-92px)] shrink-0 flex-col overflow-x-hidden bg-[#111111]"
    >
        <Deferred :data="['assets', 'summary', 'chartData', 'pricesAvailable']">
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

            <!-- ── Net worth ─────────────────────────────────────────── -->
            <div
                v-if="assets.length > 0"
                class="relative mx-[18px] mt-[18px] overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10 sm:p-7"
            >
                <div
                    class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(2,205,134,0.10),transparent_55%)]"
                ></div>

                <div
                    class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p
                                class="text-[11px] font-medium tracking-[0.13em] text-[#989898] uppercase"
                            >
                                {{ t('finance.portfolio.net_worth') }}
                            </p>
                            <span
                                v-if="lastSyncedLabel"
                                class="text-xs text-[#989898]"
                            >
                                {{
                                    t('finance.last_synced', {
                                        time: lastSyncedLabel,
                                    })
                                }}
                            </span>
                        </div>

                        <p class="mt-3 flex items-baseline gap-2">
                            <template v-if="props.pricesAvailable">
                                <CompactMoney
                                    :value="
                                        summary.total_current_value_formatted
                                    "
                                    :currency="selectedCurrency as CurrencyCode"
                                    class="text-[40px] leading-none font-bold tracking-tight text-white tabular-nums sm:text-[52px]"
                                />
                            </template>
                            <span
                                v-else
                                class="text-2xl font-medium text-[#989898]"
                            >
                                {{ t('finance.price_unavailable') }}
                            </span>
                        </p>

                        <p class="mt-2 max-w-md text-sm text-[#989898]">
                            {{ t('finance.portfolio.net_worth_description') }}
                        </p>
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-x-8 gap-y-4 lg:justify-end"
                    >
                        <div>
                            <p
                                class="text-xs font-medium tracking-[0.15em] text-[#989898] uppercase"
                            >
                                {{ t('finance.portfolio.assets_held') }}
                            </p>
                            <p class="mt-1.5 text-xl font-semibold text-white">
                                {{ summary.asset_count }}
                            </p>
                        </div>

                        <div class="h-9 w-px bg-white/10"></div>

                        <div>
                            <p
                                class="text-xs font-medium tracking-[0.15em] text-[#989898] uppercase"
                            >
                                {{ t('finance.portfolio.invested') }}
                            </p>
                            <p class="mt-1.5 text-xl font-semibold text-white">
                                <template v-if="summary.has_cost_basis_data">
                                    <CompactMoney
                                        :value="
                                            summary.total_cost_basis_formatted
                                        "
                                        :currency="
                                            selectedCurrency as CurrencyCode
                                        "
                                    />
                                </template>
                                <span
                                    v-else
                                    class="text-sm font-normal text-[#989898]"
                                    >—</span
                                >
                            </p>
                        </div>

                        <div class="h-9 w-px bg-white/10"></div>

                        <div>
                            <p
                                class="text-xs font-medium tracking-[0.15em] text-[#989898] uppercase"
                            >
                                {{ t('finance.portfolio.profit_loss') }}
                            </p>
                            <p
                                class="mt-1.5 text-xl font-semibold"
                                :class="
                                    summary.total_pnl_is_positive === true
                                        ? 'text-[#02CD86]'
                                        : summary.total_pnl_is_positive ===
                                            false
                                          ? 'text-[#E94E50]'
                                          : 'text-[#989898]'
                                "
                            >
                                <template
                                    v-if="
                                        props.pricesAvailable &&
                                        summary.total_pnl !== null
                                    "
                                >
                                    <span v-if="summary.total_pnl_is_positive"
                                        >+</span
                                    >
                                    <CompactMoney
                                        :value="
                                            signedFormattedAmount(
                                                summary.total_pnl_formatted,
                                                summary.total_pnl_is_positive,
                                            )
                                        "
                                        :currency="
                                            selectedCurrency as CurrencyCode
                                        "
                                    />
                                </template>
                                <span
                                    v-else
                                    class="text-sm font-normal text-[#989898]"
                                    >—</span
                                >
                            </p>
                        </div>

                        <div
                            v-if="props.pricesAvailable"
                            class="h-9 w-px bg-white/10"
                        ></div>
                        <div v-if="props.pricesAvailable">
                            <p
                                class="text-xs font-medium tracking-[0.15em] text-[#989898] uppercase"
                            >
                                {{ t('finance.portfolio.return') }}
                            </p>
                            <p
                                class="mt-1.5 text-xl font-semibold"
                                :class="
                                    summary.total_pnl_is_positive === true
                                        ? 'text-[#02CD86]'
                                        : summary.total_pnl_is_positive ===
                                            false
                                          ? 'text-[#E94E50]'
                                          : 'text-[#989898]'
                                "
                            >
                                <template
                                    v-if="summary.total_pnl_percent !== null"
                                >
                                    <template
                                        v-if="summary.total_pnl_is_positive"
                                        >+{{
                                            Math.abs(summary.total_pnl_percent)
                                        }}%</template
                                    >
                                    <template v-else
                                        >({{
                                            Math.abs(summary.total_pnl_percent)
                                        }}%)</template
                                    >
                                </template>
                                <span
                                    v-else
                                    class="text-sm font-normal text-[#989898]"
                                    >—</span
                                >
                            </p>
                        </div>

                        <!-- Realised — money already banked by selling. Shown
                             only once something has actually been sold, and
                             never added to the P&L above: one is settled, the
                             other moves with the market. -->
                        <template v-if="summary.has_realised_data">
                            <div class="h-9 w-px bg-white/10"></div>

                            <div>
                                <p
                                    class="text-xs font-medium tracking-[0.15em] text-[#989898] uppercase"
                                >
                                    {{ t('finance.investments.realised') }}
                                </p>
                                <p
                                    class="mt-1.5 text-xl font-semibold"
                                    :class="
                                        summary.total_realised_pnl_is_positive
                                            ? 'text-[#02CD86]'
                                            : 'text-[#E94E50]'
                                    "
                                >
                                    <span
                                        v-if="
                                            summary.total_realised_pnl_is_positive
                                        "
                                        >+</span
                                    >
                                    <CompactMoney
                                        :value="
                                            signedFormattedAmount(
                                                summary.total_realised_pnl_formatted,
                                                summary.total_realised_pnl_is_positive,
                                            )
                                        "
                                        :currency="
                                            selectedCurrency as CurrencyCode
                                        "
                                    />
                                </p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- ── Charts row ────────────────────────────────────────── -->
            <div
                v-if="assets.length > 0"
                class="grid items-stretch gap-[18px] px-[18px] py-[18px] xl:grid-cols-[320px_1fr]"
            >
                <!-- Donut / allocation chart -->
                <section
                    class="flex flex-col overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                >
                    <div class="mb-4 flex items-baseline justify-between">
                        <p class="text-[14.5px] font-medium text-white">
                            {{ t('finance.portfolio.allocation') }}
                        </p>
                        <span class="text-[11.5px] text-[#686868]">{{
                            t('finance.portfolio.by_current_value')
                        }}</span>
                    </div>
                    <div class="mx-auto mb-4.5 w-full max-w-[180px]">
                        <DonutChart
                            :series="donutSeries"
                            :labels="donutLabels"
                            :colors="donutColors"
                            :center-label="t('finance.portfolio.current_value')"
                            :center-value="
                                props.pricesAvailable
                                    ? formatCurrencyDisplay(
                                          summary.total_current_value_formatted,
                                          selectedCurrency as CurrencyCode,
                                      )
                                    : t('finance.price_unavailable')
                            "
                            hide-legend
                            @slice-click="onSliceClick"
                        />
                    </div>
                    <div class="flex flex-1 flex-col justify-center gap-2.5">
                        <div
                            v-for="asset in allocationLegend"
                            :key="asset.key"
                            class="flex items-center gap-2.5"
                        >
                            <span
                                class="size-2.5 shrink-0 rounded-full"
                                :style="{ backgroundColor: asset.color }"
                            />
                            <span
                                class="min-w-0 flex-1 truncate text-[13px] text-[#e5e5e5]"
                                >{{ asset.label }}</span
                            >
                            <span
                                class="text-[12.5px] text-[#989898] tabular-nums"
                                :class="maskClass"
                                dir="ltr"
                                >{{ asset.value_formatted }}</span
                            >
                            <span
                                class="w-10 shrink-0 text-end text-xs text-[#686868]"
                                dir="ltr"
                                >{{ asset.pct }}%</span
                            >
                        </div>
                    </div>
                </section>

                <!-- Line chart — value over time -->
                <section
                    class="flex flex-col overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                >
                    <div
                        class="mb-1 flex flex-wrap items-center justify-between gap-3"
                    >
                        <p class="text-[14.5px] font-medium text-white">
                            {{ t('finance.portfolio.value_over_time') }}
                        </p>
                        <!-- Range buttons — currency is switched from the global
                             header selector, not duplicated here. -->
                        <div
                            class="flex gap-0.5 rounded-[9px] bg-[#252525] p-[3px] ring-1 ring-white/[0.08]"
                        >
                            <button
                                v-for="rangeOption in ranges"
                                :key="rangeOption.value"
                                type="button"
                                :class="[
                                    'cursor-pointer rounded-[7px] px-3 py-1 text-xs font-medium transition',
                                    selectedRange === rangeOption.value
                                        ? 'bg-[#02cd86] text-[#101010]'
                                        : 'text-[#686868] hover:text-white',
                                ]"
                                @click="changeRange(rangeOption.value)"
                            >
                                {{ rangeOption.label }}
                            </button>
                        </div>
                    </div>

                    <LineChart
                        :series="filteredChartSeries"
                        :categories="props.chartData?.categories ?? []"
                        :calendar="displayCalendar"
                        :value-prefix="chartValuePrefix"
                        :value-suffix="chartValueSuffix"
                        :height="340"
                    />

                    <div
                        class="mt-3.5 flex flex-wrap items-center gap-2.5 border-t border-white/[0.07] pt-3.5"
                    >
                        <span class="text-xs text-[#686868]">{{
                            t('finance.portfolio.add_a_series')
                        }}</span>
                        <button
                            v-for="seriesItem in availableSeries"
                            :key="seriesItem.key"
                            type="button"
                            :class="[
                                'flex cursor-pointer items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium transition',
                                activeSeries.has(seriesItem.key)
                                    ? 'text-white'
                                    : 'bg-white/5 text-[#686868] ring-1 ring-white/10 hover:text-white',
                            ]"
                            :style="
                                activeSeries.has(seriesItem.key)
                                    ? { backgroundColor: seriesItem.color }
                                    : {}
                            "
                            @click="toggleSeries(seriesItem.key)"
                        >
                            <span
                                class="size-2 shrink-0 rounded-full"
                                :style="{ backgroundColor: seriesItem.color }"
                            />
                            {{ seriesItem.name }}
                        </button>
                    </div>
                </section>
            </div>

            <!-- ── Holdings detail ───────────────────────────────── -->
            <div
                v-if="assets.length > 0"
                class="mx-[18px] my-[18px] overflow-x-auto rounded-[16px] bg-[#1a1a1a] p-5 pt-5 pb-2.5 ring-1 ring-white/10"
            >
                <div class="mb-1 min-w-[820px]">
                    <p class="text-[14.5px] font-medium text-white">
                        {{ t('finance.portfolio.entry_level_detail') }}
                    </p>
                    <p class="text-[12.5px] text-[#989898]">
                        {{ t('finance.portfolio.entry_history_description') }}
                    </p>
                </div>

                <div
                    class="grid min-w-[820px] grid-cols-[minmax(150px,1.4fr)_96px_124px_132px_132px_150px] items-center gap-3.5 py-3.5 text-[10px] font-medium tracking-[0.1em] text-[#686868] uppercase"
                >
                    <div>{{ t('finance.fields.asset') }}</div>
                    <div class="text-end">
                        {{ t('finance.fields.quantity_short') }}
                    </div>
                    <div class="text-end">
                        {{ t('finance.fields.cost_basis_per_unit') }}
                        <span dir="ltr">({{ selectedCurrencySymbol }})</span>
                    </div>
                    <div class="text-end">
                        {{ t('finance.portfolio.total_cost_basis') }}
                        <span dir="ltr">({{ selectedCurrencySymbol }})</span>
                    </div>
                    <div class="text-end">
                        {{ t('finance.portfolio.current_value') }}
                        <span dir="ltr">({{ selectedCurrencySymbol }})</span>
                    </div>
                    <div class="text-end">
                        {{ t('finance.portfolio.profit_loss') }}
                        <span dir="ltr">({{ selectedCurrencySymbol }})</span>
                    </div>
                </div>

                <div
                    v-for="asset in assets"
                    :key="asset.key"
                    class="grid min-w-[820px] grid-cols-[minmax(150px,1.4fr)_96px_124px_132px_132px_150px] items-center gap-3.5 border-t border-white/[0.06] py-3.5 transition-colors hover:bg-white/[0.02]"
                >
                    <div class="flex min-w-0 items-center gap-2.5">
                        <AssetIcon
                            :icon="asset.icon"
                            :icon-svg="asset.icon_svg"
                            :label="asset.label"
                            :color="asset.color"
                            size="sm"
                        />
                        <span class="min-w-0 truncate text-sm text-white">{{
                            asset.label
                        }}</span>
                    </div>
                    <div
                        class="text-end text-[13px] text-[#989898] tabular-nums"
                    >
                        {{ asset.quantity }}
                        <span class="text-xs text-[#686868]">{{
                            asset.unit
                        }}</span>
                    </div>
                    <div
                        class="text-end text-[12.5px] text-[#989898] tabular-nums"
                        :class="maskClass"
                    >
                        <template v-if="asset.avg_cost_basis_formatted">
                            {{
                                formatCurrencyNumber(
                                    asset.avg_cost_basis_formatted,
                                    selectedCurrency as CurrencyCode,
                                )
                            }}
                        </template>
                        <span v-else class="text-[#686868]">—</span>
                    </div>
                    <div
                        class="text-end text-[12.5px] text-[#989898] tabular-nums"
                        :class="maskClass"
                    >
                        <template v-if="asset.total_cost_formatted">
                            {{
                                formatCurrencyNumber(
                                    asset.total_cost_formatted,
                                    selectedCurrency as CurrencyCode,
                                )
                            }}
                        </template>
                        <span v-else class="text-[#686868]">—</span>
                    </div>
                    <div
                        class="text-end text-[14.5px] font-medium text-white tabular-nums"
                        :class="maskClass"
                    >
                        <template v-if="props.pricesAvailable">
                            {{
                                formatCurrencyNumber(
                                    asset.current_value_formatted,
                                    selectedCurrency as CurrencyCode,
                                )
                            }}
                        </template>
                        <span v-else class="text-sm font-normal text-[#989898]">
                            {{ t('finance.price_unavailable') }}
                        </span>
                    </div>
                    <div
                        class="text-end text-[14.5px] font-medium tabular-nums"
                        :class="[
                            maskClass,
                            asset.pnl_is_positive === true
                                ? 'text-[#02CD86]'
                                : asset.pnl_is_positive === false
                                  ? 'text-[#E94E50]'
                                  : 'text-[#989898]',
                        ]"
                    >
                        <span
                            v-if="!props.pricesAvailable"
                            class="text-sm font-normal text-[#989898]"
                            >{{ t('finance.price_unavailable') }}</span
                        >
                        <template v-else-if="asset.pnl_formatted !== null">
                            <span v-if="asset.pnl_is_positive">+</span
                            >{{
                                formatCurrencyNumber(
                                    signedFormattedAmount(
                                        asset.pnl_formatted,
                                        asset.pnl_is_positive,
                                    ),
                                    selectedCurrency as CurrencyCode,
                                )
                            }}
                        </template>
                        <span v-else>—</span>
                    </div>
                </div>
            </div>

            <!-- Built server-side from plaintext, so it has nowhere to go
                 while the vault is armed. Keep it with the completed
                 holdings detail rather than competing with net worth. -->
            <div
                v-if="assets.length > 0 && !props.vaultPortfolio"
                class="mx-[18px] mb-[38px] flex flex-wrap items-center justify-between gap-4 rounded-[16px] bg-[#1a1a1a] px-[22px] py-[18px] ring-1 ring-white/10"
            >
                <p class="text-[13.5px] text-[#989898]">
                    {{ t('finance.portfolio.export_profit_loss_hint') }}
                </p>
                <a
                    :href="`/portfolio/export?currency=${selectedCurrency}`"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-[10px] bg-[#252525] px-[15px] py-2 text-[13.5px] text-white ring-1 ring-white/[0.14] transition-colors hover:bg-[#2e2e2e]"
                >
                    <Download class="size-3.5" />
                    {{ t('finance.portfolio.export_profit_loss') }}
                </a>
            </div>

            <!-- Still decrypting: "you hold nothing" is a worse answer than a
                 spinner, so the empty state waits for the real one. -->
            <div
                v-if="decrypting"
                class="mx-[18px] my-[18px] flex flex-col items-center justify-center rounded-[16px] bg-[#1a1a1a] px-8 py-20 ring-1 ring-white/10"
            >
                <Spinner class="size-8 text-[#02CD86]" />
                <p class="mt-4 text-sm text-[#989898]">
                    {{ t('finance.calculating') }}
                </p>
            </div>

            <!-- ── Empty state ───────────────────────────────────────── -->
            <div
                v-else-if="assets.length === 0"
                class="mx-[18px] my-[18px] flex flex-col items-center justify-center rounded-[16px] bg-[#1a1a1a] px-8 py-20 ring-1 ring-white/10"
            >
                <span
                    class="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#24212f]"
                >
                    <Wallet class="size-8 text-[#6C4EE9]" />
                </span>
                <h2 class="mt-4 text-xl font-semibold text-white">
                    {{ t('finance.portfolio.empty') }}
                </h2>
                <p class="mt-2 max-w-sm text-center text-sm text-[#989898]">
                    {{ t('finance.portfolio.empty_description') }}
                </p>
            </div>
        </Deferred>
    </div>
</template>

<script setup lang="ts">
import { Deferred, Head, router, usePage } from '@inertiajs/vue3';
import { Download, Wallet } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AssetIcon from '@/components/AssetIcon.vue';
import DonutChart from '@/components/charts/DonutChart.vue';
import LineChart from '@/components/charts/LineChart.vue';
import type { ChartSeries } from '@/components/charts/LineChart.vue';
import CompactMoney from '@/components/CompactMoney.vue';
import { Spinner } from '@/components/ui/spinner';
import { useAmountMask } from '@/composables/useAmountMask';
import { useRelativeTime } from '@/composables/useRelativeTime';
import { useVaultPortfolio } from '@/composables/useVaultPortfolio';
import type { VaultPortfolioPayload } from '@/composables/useVaultPortfolio';
import {
    currencySymbol,
    currencySymbolIsPrefix,
    formatCurrencyDisplay,
    formatCurrencyNumber,
} from '@/lib/money';
import type { CurrencyCode } from '@/lib/money';
import type { PortfolioAsset, PortfolioSummary } from '@/lib/portfolio';
import { dashboard, portfolio } from '@/routes';

type CurrencyOption = {
    label: string;
    value: string;
};

type ChartData = {
    categories: string[];
    series: ChartSeries[];
};

const props = defineProps<{
    assets?: PortfolioAsset[];
    summary?: PortfolioSummary | null;
    chartData?: ChartData;
    selectedRange: string;
    currencies: CurrencyOption[];
    selectedCurrency: string;
    pricesAvailable?: boolean;
    pricesSyncedAt?: string | null;
    /**
     * Sent instead of a server-built breakdown when the vault is armed: the raw
     * holdings, still encrypted, plus the public prices needed to value them.
     */
    vaultPortfolio?: VaultPortfolioPayload | null;
}>();

const selectedCurrency = ref(props.selectedCurrency);
const selectedCurrencySymbol = computed(() =>
    currencySymbol(selectedCurrency.value as CurrencyCode),
);
const chartValuePrefix = computed(() =>
    currencySymbolIsPrefix(selectedCurrency.value as CurrencyCode)
        ? selectedCurrencySymbol.value
        : '',
);
const chartValueSuffix = computed(() =>
    currencySymbolIsPrefix(selectedCurrency.value as CurrencyCode)
        ? ''
        : `\u00a0${selectedCurrencySymbol.value}`,
);
const { t } = useI18n();
const page = usePage();
const { masked } = useAmountMask();
const maskClass = computed(() =>
    masked.value
        ? 'blur-[6px] transition-[filter] duration-150 select-none'
        : 'transition-[filter] duration-150',
);
const displayCalendar = computed(
    () => (page.props.calendar as string | undefined) ?? 'gregorian',
);

const { breakdown, decrypting } = useVaultPortfolio(
    () => props.vaultPortfolio,
    () => selectedCurrency.value as CurrencyCode,
);

const assets = computed(() => breakdown.value?.assets ?? props.assets ?? []);
const summary = computed(
    () =>
        breakdown.value?.summary ??
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
            total_realised_pnl: null,
            total_realised_pnl_formatted: null,
            total_realised_pnl_is_positive: null,
            has_realised_data: false,
            asset_count: 0,
        },
);

const { formatRelativeTime } = useRelativeTime();
const lastSyncedLabel = computed(() =>
    formatRelativeTime(props.pricesSyncedAt),
);

function signedFormattedAmount(
    formatted: string | null,
    isPositive: boolean | null,
): string {
    if (formatted === null) {
        return '0';
    }

    return isPositive === false ? `-${formatted}` : formatted;
}

// ── Allocation donut + value-over-time chart ─────────────────────────────
const ranges = [
    { label: '1W', value: '1w' },
    { label: '1M', value: '1m' },
    { label: '3M', value: '3m' },
    { label: '1Y', value: '1y' },
    { label: 'All', value: 'all' },
];

const selectedRange = ref(props.selectedRange);

const donutSeries = computed(() =>
    assets.value.map((asset) => asset.current_value),
);
const donutLabels = computed(() => assets.value.map((asset) => asset.label));
const donutColors = computed(() => assets.value.map((asset) => asset.color));

/** The mock's own dot + name + value + percent legend rows — DonutChart's
 *  built-in legend can't express this exact shape, so it stays off and this
 *  drives the list beneath the chart instead. */
const allocationLegend = computed(() => {
    const total = summary.value.total_current_value;

    return assets.value.map((asset) => ({
        key: asset.key,
        label: asset.label,
        color: asset.color,
        value_formatted: formatCurrencyDisplay(
            asset.current_value_formatted,
            selectedCurrency.value as CurrencyCode,
        ),
        pct:
            total > 0
                ? Math.round((asset.current_value / total) * 1000) / 10
                : 0,
    }));
});

const availableSeries = computed<ChartSeries[]>(
    () => props.chartData?.series ?? [],
);
const activeSeries = ref<Set<string>>(
    new Set(availableSeries.value.map((seriesItem) => seriesItem.key)),
);

const filteredChartSeries = computed<ChartSeries[]>(() =>
    availableSeries.value.filter((seriesItem) =>
        activeSeries.value.has(seriesItem.key),
    ),
);

function toggleSeries(key: string): void {
    if (activeSeries.value.has(key)) {
        if (activeSeries.value.size === 1) {
            return;
        }

        activeSeries.value.delete(key);
    } else {
        activeSeries.value.add(key);
    }

    activeSeries.value = new Set(activeSeries.value);
}

function onSliceClick(sliceIndex: number | null): void {
    if (sliceIndex === null) {
        activeSeries.value = new Set(
            availableSeries.value.map((seriesItem) => seriesItem.key),
        );

        return;
    }

    const asset = assets.value[sliceIndex];

    if (!asset) {
        return;
    }

    if (activeSeries.value.size === 1 && activeSeries.value.has(asset.key)) {
        activeSeries.value = new Set(
            availableSeries.value.map((seriesItem) => seriesItem.key),
        );
    } else {
        activeSeries.value = new Set([asset.key]);
    }
}

function changeRange(range: string): void {
    selectedRange.value = range;
    router.get(
        portfolio.url({
            query: { range, currency: selectedCurrency.value },
        }),
        {},
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

watch(
    () => props.chartData?.series,
    () => {
        activeSeries.value = new Set(
            (props.chartData?.series ?? []).map((seriesItem) => seriesItem.key),
        );
    },
);

watch(
    () => props.selectedRange,
    (value) => {
        selectedRange.value = value;
    },
);

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
