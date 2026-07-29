<template>
    <Head :title="t('finance.portfolio.title')" />

    <div
        class="flex min-h-[calc(100vh-92px)] shrink-0 flex-col overflow-x-hidden bg-[#111111]"
    >
        <!-- ── Hero / Summary ────────────────────────────────────── -->
        <section
            class="mx-[18px] mt-5 rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
        >
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <p
                    class="w-full text-xs font-semibold tracking-[0.35em] text-[#6C4EE9] uppercase sm:w-auto"
                >
                    {{ t('finance.portfolio.overview') }}
                </p>
                <!-- Built server-side from plaintext, so it has nowhere to go while
                     the vault is armed. -->
                <a
                    v-if="!props.vaultPortfolio"
                    :href="`/portfolio/export?currency=${selectedCurrency}`"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-white/8 px-3 py-1 text-xs whitespace-nowrap text-white/70 ring-1 ring-white/15 transition-colors hover:bg-white/15 hover:text-white"
                >
                    <Download class="size-3" />
                    {{ t('finance.portfolio.export_profit_loss') }}
                </a>
                <span
                    v-if="lastSyncedLabel"
                    class="max-w-full min-w-0 text-xs break-words text-[#989898]"
                >
                    {{ t('finance.last_synced', { time: lastSyncedLabel }) }}
                </span>
            </div>
            <div
                class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between"
            >
                <div class="min-w-0 flex-1 space-y-2">
                    <h1
                        class="max-w-3xl text-3xl leading-tight font-semibold tracking-tight text-white sm:text-4xl"
                    >
                        {{ t('finance.portfolio.hero_title') }}
                    </h1>
                    <p class="text-sm text-[#989898]">
                        {{ t('finance.portfolio.hero_description') }}
                    </p>
                </div>

                <Deferred :data="['assets', 'summary', 'pricesAvailable']">
                    <template #fallback>
                        <div
                            class="grid w-full min-w-0 gap-3 sm:grid-cols-3 xl:w-[42rem] xl:max-w-[48vw]"
                        >
                            <div
                                v-for="i in 3"
                                :key="i"
                                class="flex items-center justify-center rounded-[14px] border border-white/10 bg-[#252525] p-4"
                            >
                                <Spinner class="size-5 text-[#989898]" />
                                <span class="ml-2 text-sm text-[#989898]">{{
                                    t('finance.calculating')
                                }}</span>
                            </div>
                        </div>
                    </template>

                    <!-- The same tiles again while the browser unwraps the
                         holdings: a zeroed net worth reads as a real figure. -->
                    <div
                        v-if="decrypting"
                        class="grid w-full min-w-0 gap-3 sm:grid-cols-3 xl:w-[42rem] xl:max-w-[48vw]"
                    >
                        <div
                            v-for="i in 3"
                            :key="i"
                            class="flex items-center justify-center rounded-[14px] border border-white/10 bg-[#252525] p-4"
                        >
                            <Spinner class="size-5 text-[#989898]" />
                            <span class="ml-2 text-sm text-[#989898]">{{
                                t('finance.calculating')
                            }}</span>
                        </div>
                    </div>

                    <div
                        v-else
                        class="grid w-full min-w-0 gap-3 sm:grid-cols-3 xl:w-[42rem] xl:max-w-[48vw]"
                    >
                        <!-- Current value -->
                        <div
                            class="rounded-[14px] border border-white/10 bg-[#252525] p-4"
                            style="border-top: 2.5px solid #02cd86"
                        >
                            <p
                                class="text-xs font-medium tracking-[0.2em] text-[#02CD86] uppercase"
                            >
                                {{ t('finance.portfolio.current_value') }}
                            </p>
                            <p class="mt-2 text-base font-bold text-white">
                                <template v-if="props.pricesAvailable">
                                    {{ summary.total_current_value_formatted }}
                                    <span
                                        class="text-xs font-normal text-[#989898]"
                                        >{{ currencySymbol }}</span
                                    >
                                </template>
                                <span
                                    v-else
                                    class="text-sm font-normal text-[#989898]"
                                    >{{ t('finance.price_unavailable') }}</span
                                >
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
                                    <span
                                        class="text-xs font-normal text-[#989898]"
                                        >{{ currencySymbol }}</span
                                    >
                                </template>
                                <span
                                    v-else
                                    class="text-sm font-normal text-[#989898]"
                                    >{{
                                        t('finance.portfolio.no_cost_basis')
                                    }}</span
                                >
                            </p>
                        </div>

                        <!-- P&L -->
                        <div
                            class="rounded-[14px] border border-white/10 bg-[#252525] p-4"
                            :style="{
                                borderTop:
                                    summary.total_pnl_is_positive === true
                                        ? '2.5px solid #02CD86'
                                        : summary.total_pnl_is_positive ===
                                            false
                                          ? '2.5px solid #E94E50'
                                          : '2.5px solid #989898',
                            }"
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
                                <template
                                    v-else-if="summary.total_pnl !== null"
                                >
                                    <span>{{
                                        summary.total_pnl_is_positive
                                            ? '+'
                                            : '−'
                                    }}</span>
                                    {{ summary.total_pnl_formatted }}
                                    {{ currencySymbol }}
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

                        <!-- Realised — money already banked by selling. Shown only
                             once something has actually been sold, and never added
                             to the P&L above: one is settled, the other moves with
                             the market, and a sum of the two means nothing. -->
                        <div
                            v-if="summary.has_realised_data"
                            class="rounded-[14px] border border-white/10 bg-[#252525] p-4 sm:col-span-3"
                            :style="{
                                borderTop:
                                    summary.total_realised_pnl_is_positive
                                        ? '2.5px solid #02CD86'
                                        : '2.5px solid #E94E50',
                            }"
                        >
                            <p
                                class="text-xs font-medium tracking-[0.2em] uppercase"
                                :class="
                                    summary.total_realised_pnl_is_positive
                                        ? 'text-[#02CD86]'
                                        : 'text-[#E94E50]'
                                "
                            >
                                {{ t('finance.investments.realised') }}
                            </p>
                            <p
                                class="mt-2 text-base font-bold"
                                :class="
                                    summary.total_realised_pnl_is_positive
                                        ? 'text-[#02CD86]'
                                        : 'text-[#E94E50]'
                                "
                            >
                                <span>{{
                                    summary.total_realised_pnl_is_positive
                                        ? '+'
                                        : '−'
                                }}</span>
                                {{ summary.total_realised_pnl_formatted }}
                                <span
                                    class="text-xs font-normal text-[#989898]"
                                >
                                    {{ currencySymbol }}
                                </span>
                            </p>
                            <p class="mt-1 text-xs text-[#6b6b6b]">
                                {{ t('finance.investments.realised_hint') }}
                            </p>
                        </div>
                    </div>
                </Deferred>
            </div>
        </section>

        <Deferred :data="['assets', 'summary', 'pricesAvailable']">
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

            <!-- ── Net worth ─────────────────────────────────────────── -->
            <div
                v-if="assets.length > 0"
                class="relative mx-[18px] mt-[18px] overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10 sm:p-7"
            >
                <div
                    class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(2,205,134,0.10),transparent_55%)]"
                ></div>

                <div
                    class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="relative flex size-2">
                                <span
                                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#02CD86] opacity-60"
                                ></span>
                                <span
                                    class="relative inline-flex size-2 rounded-full bg-[#02CD86]"
                                ></span>
                            </span>
                            <p
                                class="text-xs font-semibold tracking-[0.35em] text-[#02CD86] uppercase"
                            >
                                {{ t('finance.portfolio.net_worth') }}
                            </p>
                            <span
                                class="rounded-full bg-white/8 px-2.5 py-0.5 text-[10px] font-medium tracking-wide text-[#989898] uppercase"
                            >
                                {{ t('finance.portfolio.today') }}
                            </span>
                        </div>

                        <p class="mt-3 flex items-baseline gap-2">
                            <template v-if="props.pricesAvailable">
                                <span
                                    class="text-[40px] leading-none font-bold tracking-tight text-white tabular-nums sm:text-[52px]"
                                >
                                    {{ summary.total_current_value_formatted }}
                                </span>
                                <span
                                    class="text-base font-medium text-[#989898]"
                                    >{{ currencySymbol }}</span
                                >
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
                                    {{ summary.total_cost_basis_formatted }}
                                    <span
                                        class="text-xs font-normal text-[#989898]"
                                        >{{ currencySymbol }}</span
                                    >
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
                                class="mt-1.5 flex items-center gap-1 text-xl font-semibold"
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
                                    <TrendingUp
                                        v-if="summary.total_pnl_is_positive"
                                        class="size-4"
                                    />
                                    <TrendingDown
                                        v-else-if="
                                            summary.total_pnl_is_positive ===
                                            false
                                        "
                                        class="size-4"
                                    />
                                    <span>
                                        {{
                                            summary.total_pnl_is_positive
                                                ? '+'
                                                : '−'
                                        }}{{
                                            summary.total_pnl_percent !== null
                                                ? Math.abs(
                                                      summary.total_pnl_percent,
                                                  ) + '%'
                                                : summary.total_pnl_formatted
                                        }}
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
            </div>

            <!-- ── Holdings detail ───────────────────────────────── -->
            <div
                v-if="assets.length > 0"
                class="mx-[18px] my-[18px] overflow-hidden rounded-[22px] bg-[#1a1a1a] shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
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
                                    {{
                                        t('finance.fields.cost_basis_per_unit')
                                    }}
                                </th>
                                <th
                                    class="hidden bg-[#0d2620] px-3 py-4 text-center font-normal text-[#7ee8c4] lg:table-cell lg:px-5"
                                >
                                    {{
                                        t('finance.portfolio.total_cost_basis')
                                    }}
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
                                    <AssetIcon
                                        :icon="asset.icon"
                                        :icon-svg="asset.icon_svg"
                                        :label="asset.label"
                                        :color="asset.color"
                                        size="sm"
                                    />
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
                                        <span
                                            class="text-xs font-normal text-[#989898]"
                                            >{{ currencySymbol }}</span
                                        >
                                    </span>
                                    <span v-else class="text-sm text-[#989898]"
                                        >—</span
                                    >
                                </td>
                                <td
                                    class="hidden px-3 py-[14px] text-center lg:table-cell lg:px-5"
                                >
                                    <span
                                        v-if="asset.total_cost_formatted"
                                        class="text-[15px] text-white"
                                    >
                                        {{ asset.total_cost_formatted }}
                                        <span class="text-xs text-[#989898]">{{
                                            currencySymbol
                                        }}</span>
                                    </span>
                                    <span v-else class="text-sm text-[#989898]"
                                        >—</span
                                    >
                                </td>
                                <td
                                    class="px-3 py-[14px] text-center text-[16px] leading-none font-bold text-white sm:px-5"
                                >
                                    <template v-if="props.pricesAvailable">
                                        {{ asset.current_value_formatted }}
                                        <span
                                            class="text-xs font-normal text-[#989898]"
                                            >{{ currencySymbol }}</span
                                        >
                                    </template>
                                    <span
                                        v-else
                                        class="text-sm font-normal text-[#989898]"
                                        >{{
                                            t('finance.price_unavailable')
                                        }}</span
                                    >
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
                                        >{{
                                            t('finance.price_unavailable')
                                        }}</span
                                    >
                                    <template v-else-if="asset.pnl !== null">
                                        {{ asset.pnl_is_positive ? '+' : '−' }}
                                        {{ asset.pnl_formatted }}
                                        {{ currencySymbol }}
                                    </template>
                                    <span v-else>—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Profit / loss by asset ────────────────────────────── -->
            <div
                v-if="assets.length > 0"
                class="mx-[18px] my-[18px] overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-[18px] leading-none font-normal text-white">
                        {{ t('finance.portfolio.pnl_by_asset') }}
                    </h2>
                    <span class="text-xs text-[#989898]">{{
                        currencySymbol
                    }}</span>
                </div>
                <PulseChart
                    v-if="props.pricesAvailable && pnlByAsset.hasData"
                    :data="pnlByAsset.data"
                    :categories="pnlByAsset.categories"
                    :series-name="t('finance.portfolio.profit_loss')"
                    color="#02CD86"
                    highlight-color="#E94E50"
                    color-mode="sign"
                    :height="280"
                />
                <p v-else class="py-12 text-center text-sm text-[#989898]">
                    {{ t('finance.portfolio.hero_description') }}
                </p>
            </div>

            <!-- Still decrypting: "you hold nothing" is a worse answer than a
                 spinner, so the empty state waits for the real one. -->
            <div
                v-if="decrypting"
                class="mx-[18px] my-[18px] flex flex-col items-center justify-center rounded-[22px] bg-[#1a1a1a] px-8 py-20 ring-1 ring-white/10"
            >
                <Spinner class="size-8 text-[#02CD86]" />
                <p class="mt-4 text-sm text-[#989898]">
                    {{ t('finance.calculating') }}
                </p>
            </div>

            <!-- ── Empty state ───────────────────────────────────────── -->
            <div
                v-else-if="assets.length === 0"
                class="mx-[18px] my-[18px] flex flex-col items-center justify-center rounded-[22px] bg-[#1a1a1a] px-8 py-20 ring-1 ring-white/10"
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
import { Deferred, Head } from '@inertiajs/vue3';
import { Download, TrendingDown, TrendingUp, Wallet } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AssetIcon from '@/components/AssetIcon.vue';
import PulseChart from '@/components/charts/PulseChart.vue';
import { Spinner } from '@/components/ui/spinner';
import { useRelativeTime } from '@/composables/useRelativeTime';
import { useVaultPortfolio } from '@/composables/useVaultPortfolio';
import type { VaultPortfolioPayload } from '@/composables/useVaultPortfolio';
import type { CurrencyCode } from '@/lib/money';
import type { PortfolioAsset, PortfolioSummary } from '@/lib/portfolio';
import { dashboard, portfolio } from '@/routes';

type CurrencyOption = {
    label: string;
    value: string;
};

const props = defineProps<{
    assets?: PortfolioAsset[];
    summary?: PortfolioSummary | null;
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
const { t } = useI18n();

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

// Profit / loss per asset — only assets with a recorded cost basis have a P&L.
const pnlByAsset = computed(() => {
    const withPnl = assets.value.filter((asset) => asset.pnl !== null);

    return {
        hasData: withPnl.length > 0,
        data: withPnl.map((asset) => Math.round(asset.pnl ?? 0)),
        categories: withPnl.map((asset) => asset.label),
    };
});

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
