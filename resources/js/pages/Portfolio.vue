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
                                    <span :class="maskClass">{{
                                        summary.total_current_value_formatted
                                    }}</span>
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
                                    <span :class="maskClass">{{
                                        summary.total_cost_basis_formatted
                                    }}</span>
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
                                    <span :class="maskClass">{{
                                        summary.total_pnl_formatted
                                    }}</span>
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
                                <span :class="maskClass">{{
                                    summary.total_realised_pnl_formatted
                                }}</span>
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

        <Deferred :data="['assets', 'summary', 'chartData', 'pricesAvailable']">
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
                                    :class="maskClass"
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
                                    <span :class="maskClass">{{
                                        summary.total_cost_basis_formatted
                                    }}</span>
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
                                    <span :class="maskClass">
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

            <!-- ── Charts row ────────────────────────────────────────── -->
            <div
                v-if="assets.length > 0"
                class="grid items-stretch gap-[18px] px-[18px] py-[18px] xl:grid-cols-[320px_1fr]"
            >
                <!-- Donut / allocation chart -->
                <section
                    class="flex flex-col overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h2
                            class="text-[18px] leading-none font-normal text-white"
                        >
                            {{ t('finance.portfolio.allocation') }}
                        </h2>
                        <span class="text-xs text-[#989898]">{{
                            t('finance.portfolio.by_current_value')
                        }}</span>
                    </div>
                    <div class="flex flex-1 items-center">
                        <DonutChart
                            :series="donutSeries"
                            :labels="donutLabels"
                            :colors="donutColors"
                            :center-label="t('finance.portfolio.current_value')"
                            :center-value="
                                props.pricesAvailable
                                    ? summary.total_current_value_formatted +
                                      ' ' +
                                      currencySymbol
                                    : t('finance.price_unavailable')
                            "
                            @slice-click="onSliceClick"
                        />
                    </div>
                </section>

                <!-- Line chart — value over time -->
                <section
                    class="flex flex-col overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                >
                    <div
                        class="mb-4 flex flex-wrap items-center justify-between gap-3"
                    >
                        <h2
                            class="text-[18px] leading-none font-normal text-white"
                        >
                            {{ t('finance.portfolio.value_over_time') }}
                        </h2>
                        <!-- Range buttons — currency is switched from the global
                             header selector, not duplicated here. -->
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="rangeOption in ranges"
                                :key="rangeOption.value"
                                type="button"
                                :class="[
                                    'cursor-pointer rounded-full px-3 py-1 text-xs font-medium transition',
                                    selectedRange === rangeOption.value
                                        ? 'bg-[#02cd86] text-[#101010]'
                                        : 'text-[#686868] ring-1 ring-white/10 hover:bg-white/10 hover:text-white',
                                ]"
                                @click="changeRange(rangeOption.value)"
                            >
                                {{ rangeOption.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Series toggle chips -->
                    <div class="mb-3 flex flex-wrap gap-2">
                        <button
                            v-for="seriesItem in availableSeries"
                            :key="seriesItem.key"
                            type="button"
                            :class="[
                                'flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium transition',
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

                    <LineChart
                        :series="filteredChartSeries"
                        :categories="props.chartData?.categories ?? []"
                        :calendar="displayCalendar"
                        :height="340"
                    />
                </section>
            </div>

            <!-- ── Asset summary cards ───────────────────────────────── -->
            <div
                v-if="assets.length > 0"
                class="grid grid-cols-2 gap-[18px] px-[18px] sm:grid-cols-3 xl:grid-cols-6"
            >
                <div
                    v-for="asset in assets"
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
                                >{{ assetAllocation(asset) }}%</template
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
                        {{ asset.quantity }} {{ asset.unit }}
                    </p>
                    <p class="mt-2 text-sm font-bold text-white">
                        <template v-if="props.pricesAvailable">
                            <span :class="maskClass">{{
                                asset.current_value_formatted
                            }}</span>
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
                                    class="rounded-l-2xl bg-[#252525] px-3 py-4 text-center font-normal text-[#989898] sm:px-5"
                                >
                                    {{ t('finance.fields.asset') }}
                                </th>
                                <th
                                    class="bg-[#252525] px-3 py-4 text-center font-normal text-[#989898] sm:px-5"
                                >
                                    {{ t('finance.fields.quantity_short') }}
                                </th>
                                <th
                                    class="hidden bg-[#252525] px-3 py-4 text-center font-normal text-[#989898] sm:table-cell sm:px-5"
                                >
                                    {{
                                        t('finance.fields.cost_basis_per_unit')
                                    }}
                                </th>
                                <th
                                    class="hidden bg-[#252525] px-3 py-4 text-center font-normal text-[#989898] lg:table-cell lg:px-5"
                                >
                                    {{
                                        t('finance.portfolio.total_cost_basis')
                                    }}
                                </th>
                                <th
                                    class="bg-[#252525] px-3 py-4 text-center font-normal text-[#989898] sm:px-5"
                                >
                                    {{ t('finance.portfolio.current_value') }}
                                </th>
                                <th
                                    class="bg-[#252525] px-3 py-4 text-center font-normal text-[#989898] sm:px-5"
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
                                        :class="maskClass"
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
                                        :class="maskClass"
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
                                        <span :class="maskClass">{{
                                            asset.current_value_formatted
                                        }}</span>
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
                                        <span :class="maskClass">
                                            {{
                                                asset.pnl_is_positive
                                                    ? '+'
                                                    : '−'
                                            }}
                                            {{ asset.pnl_formatted }}
                                            {{ currencySymbol }}
                                        </span>
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

        <!-- ── Savings goals ─────────────────────────────────────────── -->
        <!-- Outside <Deferred> on purpose: goals travel on their own key, and a
             goal in grams stays meaningful even when no price is available.
             Dropped entirely when the goals module is off. -->
        <section v-if="props.showsGoals" class="mx-[18px] mb-[18px]">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h2
                    class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                >
                    {{ t('gamification.goals.title') }}
                </h2>
                <button
                    type="button"
                    class="rounded-full bg-white/8 px-3 py-1 text-xs text-white/70 ring-1 ring-white/15 transition-colors hover:bg-white/15 hover:text-white"
                    @click="openGoalDialog(null)"
                >
                    {{ t('gamification.goals.new') }}
                </button>
            </div>

            <div
                v-if="goalsLoading"
                class="grid gap-[18px] md:grid-cols-2"
                aria-busy="true"
            >
                <div
                    v-for="index in 2"
                    :key="index"
                    class="h-[210px] animate-pulse rounded-[22px] bg-[#1a1a1a] ring-1 ring-white/10"
                />
            </div>

            <div
                v-else-if="goals.length > 0"
                class="grid gap-[18px] md:grid-cols-2"
            >
                <GoalCard
                    v-for="goal in goals"
                    :key="goal.id"
                    :goal="goal"
                    @edit="openGoalDialog"
                    @delete="deleteTargetGoal = $event"
                />
            </div>

            <p
                v-else
                class="rounded-[22px] bg-[#1a1a1a] px-6 py-8 text-center text-sm text-[#989898] ring-1 ring-white/10"
            >
                {{ t('gamification.goals.empty') }}
            </p>
        </section>

        <GoalDialog
            v-model:open="goalDialogOpen"
            :asset-options="props.assetOptions ?? []"
            :goal="editingGoal"
        />

        <ConfirmDeleteModal
            :open="deleteTargetGoal !== null"
            :title="t('gamification.goals.delete_title')"
            :description="t('gamification.goals.delete_description')"
            @update:open="deleteTargetGoal = null"
            @confirm="confirmDeleteGoal"
        />
    </div>
</template>

<script setup lang="ts">
import { Deferred, Head, router, usePage } from '@inertiajs/vue3';
import { Download, TrendingDown, TrendingUp, Wallet } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AssetIcon from '@/components/AssetIcon.vue';
import DonutChart from '@/components/charts/DonutChart.vue';
import LineChart from '@/components/charts/LineChart.vue';
import type { ChartSeries } from '@/components/charts/LineChart.vue';
import PulseChart from '@/components/charts/PulseChart.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import GoalCard from '@/components/gamification/GoalCard.vue';
import GoalDialog from '@/components/gamification/GoalDialog.vue';
import { Spinner } from '@/components/ui/spinner';
import { useAmountMask } from '@/composables/useAmountMask';
import { useRelativeTime } from '@/composables/useRelativeTime';
import { useVaultGoals } from '@/composables/useVaultGoals';
import type { VaultGoalsPayload } from '@/composables/useVaultGoals';
import { useVaultPortfolio } from '@/composables/useVaultPortfolio';
import type { VaultPortfolioPayload } from '@/composables/useVaultPortfolio';
import type { CurrencyCode } from '@/lib/money';
import type { PortfolioAsset, PortfolioSummary } from '@/lib/portfolio';
import { dashboard, portfolio } from '@/routes';
import { destroy as destroyGoal } from '@/routes/savings-goals';
import type {
    AssetOption,
    GoalCard as GoalCardData,
} from '@/types/gamification';

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
    /** Deferred; null under the vault, where `vaultGoals` carries them instead. */
    goals?: GoalCardData[] | null;
    /** Sent instead of `goals` when the vault is armed — targets still sealed. */
    vaultGoals?: VaultGoalsPayload | null;
    /** False when the goals module is off, and the whole section is dropped. */
    showsGoals?: boolean;
    assetOptions?: AssetOption[];
}>();

const selectedCurrency = ref(props.selectedCurrency);
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

const { breakdown, entries, decrypting } = useVaultPortfolio(
    () => props.vaultPortfolio,
    () => selectedCurrency.value as CurrencyCode,
);

// Reuses the decryption pass above rather than opening the same rows twice.
const { goals: vaultGoalCards, decrypting: goalsDecrypting } = useVaultGoals(
    () => props.vaultGoals,
    () => entries.value,
);

const goals = computed<GoalCardData[]>(
    () => vaultGoalCards.value ?? props.goals ?? [],
);

/**
 * Undefined means the deferred prop has not landed yet; null means the vault
 * sent no goals. Only the second is an empty list — showing the "no goals yet"
 * invitation to someone who has three is worse than a skeleton.
 */
const goalsLoading = computed(
    () =>
        goalsDecrypting.value ||
        (props.vaultGoals === undefined && props.goals === undefined),
);

const goalDialogOpen = ref(false);
const editingGoal = ref<GoalCardData | null>(null);
const deleteTargetGoal = ref<GoalCardData | null>(null);

/** Null opens the dialog for a new goal; a card opens it prefilled for editing. */
function openGoalDialog(goal: GoalCardData | null): void {
    editingGoal.value = goal;
    goalDialogOpen.value = true;
}

// The reload after a save hands back fresh goal objects, so the one held here
// is stale the moment it is written. Dropped on close rather than kept, or
// re-opening the editor would prefill from the values that were just replaced.
watch(goalDialogOpen, (open) => {
    if (!open) {
        editingGoal.value = null;
    }
});

function confirmDeleteGoal(): void {
    const goal = deleteTargetGoal.value;

    if (goal === null) {
        return;
    }

    router.delete(destroyGoal.url(goal.id), {
        preserveScroll: true,
        // Same reason as the dialog's: the controller redirects `back()`, so the
        // goal props have to be asked for by name to actually come back.
        onSuccess: () => router.reload({ only: ['goals', 'vaultGoals'] }),
        onFinish: () => {
            deleteTargetGoal.value = null;
        },
    });
}

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

/** Share of total current value, for the asset summary cards' badge. */
function assetAllocation(asset: PortfolioAsset): number {
    const total = summary.value.total_current_value;

    return total > 0
        ? Math.round((asset.current_value / total) * 1000) / 10
        : 0;
}

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
