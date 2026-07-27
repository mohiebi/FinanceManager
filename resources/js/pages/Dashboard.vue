<template>
    <Head :title="t('finance.dashboard.title')" />

    <div
        class="flex h-full min-h-[calc(100vh-92px)] flex-1 flex-col overflow-x-hidden bg-[#101010] text-white"
    >
        <section
            class="mx-[18px] mt-5 rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
        >
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.3em] text-[#989898] uppercase"
                    >
                        {{ t('finance.dashboard.eyebrow') }}
                    </p>
                    <h1
                        class="mt-1 text-[26px] leading-tight font-bold text-white sm:text-[30px]"
                    >
                        {{ t('finance.dashboard.heading') }}
                    </h1>
                    <p class="mt-0.5 text-sm text-[#989898]">
                        {{ t('finance.dashboard.welcome') }}
                        <span class="font-semibold text-white">{{
                            user?.name ?? t('finance.dashboard.guest')
                        }}</span>
                    </p>
                </div>
            </div>
        </section>
        <div
            class="grid gap-[18px] px-[18px] py-[18px] xl:grid-cols-[1fr_284px]"
        >
            <div class="flex flex-col gap-[18px]">
                <div class="grid gap-[18px] sm:grid-cols-3">
                    <!-- Income KPI -->
                    <article
                        class="kpi-card-income overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                    >
                        <div class="flex items-center gap-2.5">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#0d2e22]"
                            >
                                <TrendingUp
                                    class="size-[18px] text-[#02CD86]"
                                />
                            </span>
                            <p
                                class="text-xs font-medium tracking-[0.15em] text-[#989898] uppercase"
                            >
                                {{ t('finance.metrics.income') }}
                            </p>
                        </div>
                        <p
                            class="mt-3 text-[20px] leading-none font-bold text-white"
                        >
                            {{ formatAmount(summaryIncome) }}
                            <span class="text-xs font-normal text-[#989898]">{{
                                selectedCurrencyLabel
                            }}</span>
                        </p>
                        <p class="mt-1.5 text-xs text-[#989898]">
                            {{ props.transactions.incomes.length }}
                            {{ t('finance.reports.transactions') }}
                        </p>
                    </article>
                    <!-- Cost KPI -->
                    <article
                        class="kpi-card-cost overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                    >
                        <div class="flex items-center gap-2.5">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#24212f]"
                            >
                                <TrendingDown
                                    class="size-[18px] text-[#6C4EE9]"
                                />
                            </span>
                            <p
                                class="text-xs font-medium tracking-[0.15em] text-[#989898] uppercase"
                            >
                                {{ t('finance.metrics.costs') }}
                            </p>
                        </div>
                        <p
                            class="mt-3 text-[20px] leading-none font-bold text-white"
                        >
                            {{ formatAmount(summaryCost) }}
                            <span class="text-xs font-normal text-[#989898]">{{
                                selectedCurrencyLabel
                            }}</span>
                        </p>
                        <p class="mt-1.5 text-xs text-[#989898]">
                            {{ props.transactions.costs.length }}
                            {{ t('finance.reports.transactions') }}
                        </p>
                    </article>
                    <!-- Balance KPI -->
                    <article
                        class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                        :class="
                            balance >= 0 ? 'kpi-card-income' : 'kpi-card-cost'
                        "
                    >
                        <div class="flex items-center gap-2.5">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                                :class="
                                    balance >= 0
                                        ? 'bg-[#effffa]'
                                        : 'bg-[#fff0f0]'
                                "
                            >
                                <Wallet
                                    class="size-[18px]"
                                    :class="
                                        balance >= 0
                                            ? 'text-[#02CD86]'
                                            : 'text-[#E94E50]'
                                    "
                                />
                            </span>
                            <p
                                class="text-xs font-medium tracking-[0.15em] text-[#989898] uppercase"
                            >
                                {{ t('finance.metrics.balance') }}
                            </p>
                        </div>
                        <p
                            class="mt-3 text-[20px] leading-none font-bold"
                            :class="
                                balance >= 0
                                    ? 'text-[#02CD86]'
                                    : 'text-[#E94E50]'
                            "
                        >
                            {{ balance >= 0 ? '+' : '−'
                            }}{{ formatAmount(Math.abs(balance)) }}
                            <span class="text-xs font-normal text-[#989898]">{{
                                selectedCurrencyLabel
                            }}</span>
                        </p>
                        <p class="mt-1.5 text-xs text-[#989898]">
                            {{
                                balance >= 0
                                    ? t('finance.metrics.in_the_positive')
                                    : t('finance.metrics.overspent')
                            }}
                        </p>
                    </article>
                </div>
                <div class="grid items-stretch gap-[18px] xl:grid-cols-3">
                    <!-- ── Spending by category (donut) ── -->
                    <div
                        class="min-h-[244px] overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                    >
                        <h2 class="text-[17px] font-normal text-white">
                            {{ t('finance.dashboard.spending_by_category') }}
                        </h2>
                        <DonutChart
                            v-if="categoryBreakdown.series.length > 0"
                            class="mt-2"
                            :series="categoryBreakdown.series"
                            :labels="categoryBreakdown.labels"
                            :colors="categoryBreakdown.colors"
                            :center-label="t('finance.dashboard.total_spent')"
                            :center-value="
                                formatAmount(categoryBreakdown.total) +
                                ' ' +
                                currencySymbol
                            "
                            :tooltip-formatter="
                                (v: number) =>
                                    formatAmount(v) +
                                    ' ' +
                                    selectedCurrencyLabel
                            "
                        />
                        <p
                            v-else
                            class="mt-8 text-center text-sm text-[#989898]"
                        >
                            {{ t('finance.dashboard.no_costs') }}
                        </p>
                    </div>

                    <!-- ── 3-month income vs costs trend ── -->
                    <div
                        class="min-h-[244px] overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                    >
                        <h2 class="text-[17px] font-normal text-white">
                            {{ t('finance.dashboard.monthly_overview') }}
                        </h2>
                        <LineChart
                            class="mt-2"
                            :series="trendSeries"
                            :categories="trendLabels"
                            raw-labels
                            :height="220"
                        />
                    </div>

                    <!-- ── Daily spending pulse ── -->
                    <div
                        class="min-h-[244px] overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                    >
                        <h2 class="text-[17px] font-normal text-white">
                            {{ t('finance.dashboard.daily_spending') }}
                        </h2>
                        <PulseChart
                            class="mt-2"
                            :data="dailySpending.data"
                            :categories="dailySpending.categories"
                            :series-name="t('finance.metrics.costs')"
                            :height="220"
                        />
                    </div>
                </div>
                <div class="grid items-stretch gap-[18px] md:grid-cols-2">
                    <!-- ── Net worth / portfolio snapshot ── -->
                    <!-- The v-if is load-bearing: <Deferred> renders its fallback
                         forever when the prop is absent, so the whole card must go. -->
                    <div
                        v-if="features?.portfolio.enabled"
                        class="kpi-card-income overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#0d2e22]"
                                >
                                    <ChartPie
                                        class="size-[18px] text-[#02CD86]"
                                    />
                                </span>
                                <p
                                    class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                                >
                                    {{ t('finance.portfolio.net_worth') }}
                                </p>
                            </div>
                            <Link
                                :href="
                                    portfolioRoute.url({
                                        query: {
                                            currency: props.selectedCurrency,
                                        },
                                    })
                                "
                                class="text-xs text-[#02CD86] hover:underline"
                            >
                                {{ t('finance.portfolio.title') }}
                            </Link>
                        </div>

                        <Deferred data="portfolio">
                            <template #fallback>
                                <div class="mt-4 animate-pulse">
                                    <div
                                        class="h-7 w-40 rounded-lg bg-white/10"
                                    />
                                    <div
                                        class="mt-3 h-2 w-full rounded-full bg-white/10"
                                    />
                                    <div
                                        class="mt-3 h-4 w-24 rounded-lg bg-white/10"
                                    />
                                </div>
                            </template>

                            <template v-if="portfolioSnapshot">
                                <div class="mt-3 flex items-center gap-3">
                                    <p
                                        class="text-[24px] leading-none font-bold text-white"
                                    >
                                        {{
                                            formatAmount(
                                                portfolioSnapshot.net_worth_formatted,
                                            )
                                        }}
                                        <span
                                            class="text-xs font-normal text-[#989898]"
                                            >{{ selectedCurrencyLabel }}</span
                                        >
                                    </p>
                                    <span
                                        v-if="
                                            portfolioSnapshot.has_cost_basis_data &&
                                            portfolioSnapshot.pnl_percent !==
                                                null
                                        "
                                        class="rounded-md px-2 py-1 text-xs font-bold"
                                        :class="
                                            portfolioSnapshot.pnl_is_positive
                                                ? 'bg-[#0d2e22] text-[#02CD86]'
                                                : 'bg-[#2e0d0d] text-[#E94E50]'
                                        "
                                    >
                                        {{
                                            portfolioSnapshot.pnl_is_positive
                                                ? '+'
                                                : '−'
                                        }}{{
                                            Math.abs(
                                                portfolioSnapshot.pnl_percent,
                                            )
                                        }}%
                                    </span>
                                </div>

                                <div
                                    class="mt-4 flex h-2 w-full gap-0.5 overflow-hidden rounded-full bg-white/10"
                                >
                                    <div
                                        v-for="asset in portfolioSnapshot.top_assets"
                                        :key="asset.key"
                                        class="h-full rounded-full transition-all duration-700"
                                        :style="{
                                            width: asset.share + '%',
                                            backgroundColor:
                                                asset.color ?? '#02CD86',
                                        }"
                                    />
                                </div>

                                <ul class="mt-4 flex flex-col gap-2.5">
                                    <li
                                        v-for="asset in portfolioSnapshot.top_assets"
                                        :key="asset.key"
                                        class="flex items-center gap-2.5"
                                    >
                                        <AssetIcon
                                            :icon="asset.icon"
                                            :icon-svg="asset.icon_svg"
                                            :label="asset.label"
                                            :color="asset.color"
                                            size="sm"
                                        />
                                        <span
                                            class="min-w-0 flex-1 truncate text-sm text-white"
                                            >{{ asset.label }}</span
                                        >
                                        <span
                                            class="text-sm font-semibold text-white"
                                            >{{
                                                formatAmount(
                                                    asset.value_formatted,
                                                )
                                            }}</span
                                        >
                                        <span
                                            class="w-10 text-end text-xs text-[#989898]"
                                            >{{ asset.share }}%</span
                                        >
                                    </li>
                                </ul>
                            </template>

                            <!-- Still decrypting: an empty state here would claim
                                 the user holds nothing, which is a worse lie than
                                 a skeleton. -->
                            <div
                                v-else-if="props.vaultPortfolio"
                                class="mt-4 animate-pulse"
                            >
                                <div class="h-7 w-40 rounded-lg bg-white/10" />
                                <div
                                    class="mt-3 h-2 w-full rounded-full bg-white/10"
                                />
                                <div
                                    class="mt-3 h-4 w-24 rounded-lg bg-white/10"
                                />
                            </div>

                            <div v-else class="mt-4">
                                <p class="text-sm text-[#989898]">
                                    {{ t('finance.dashboard.portfolio_empty') }}
                                </p>
                                <Link
                                    :href="investmentsIndex()"
                                    class="mt-2 inline-block text-sm text-[#02CD86] hover:underline"
                                >
                                    {{
                                        t(
                                            'finance.dashboard.portfolio_empty_cta',
                                        )
                                    }}
                                </Link>
                            </div>
                        </Deferred>
                    </div>

                    <!-- ── Live asset prices ── -->
                    <div
                        v-if="features?.investments.enabled"
                        class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#24212f]"
                                >
                                    <TrendingUp
                                        class="size-[18px] text-[#6C4EE9]"
                                    />
                                </span>
                                <p
                                    class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                                >
                                    {{ t('finance.dashboard.live_prices') }}
                                </p>
                            </div>
                            <span
                                v-if="syncedAgo"
                                class="text-xs text-[#989898]"
                            >
                                {{
                                    t('finance.last_synced', {
                                        time: syncedAgo,
                                    })
                                }}
                            </span>
                        </div>

                        <Deferred data="assetPrices">
                            <template #fallback>
                                <div
                                    class="mt-4 flex animate-pulse flex-col gap-3"
                                >
                                    <div
                                        v-for="i in 4"
                                        :key="i"
                                        class="h-6 w-full rounded-lg bg-white/10"
                                    />
                                </div>
                            </template>

                            <ul
                                v-if="availablePrices.length > 0"
                                class="mt-4 flex flex-col gap-2.5"
                            >
                                <li
                                    v-for="price in availablePrices"
                                    :key="price.key"
                                    class="flex items-center gap-2.5"
                                >
                                    <AssetIcon
                                        :icon="price.icon"
                                        :icon-svg="price.icon_svg"
                                        :label="price.label"
                                        :color="price.color"
                                        size="sm"
                                    />
                                    <span
                                        class="min-w-0 flex-1 truncate text-sm text-white"
                                    >
                                        {{ price.label }}
                                        <span
                                            v-if="price.unit"
                                            class="text-xs text-[#989898]"
                                            >/ {{ price.unit }}</span
                                        >
                                    </span>
                                    <span
                                        class="text-sm font-semibold text-white"
                                        >{{
                                            formatAmount(price.price_formatted)
                                        }}</span
                                    >
                                    <span class="text-[10px] text-[#989898]">{{
                                        selectedCurrencyLabel
                                    }}</span>
                                </li>
                            </ul>

                            <p v-else class="mt-4 text-sm text-[#989898]">
                                {{ t('finance.price_unavailable') }}
                            </p>
                        </Deferred>
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-[18px]">
                <div
                    class="kpi-card-period overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                >
                    <p
                        class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                    >
                        {{ t('finance.calendar.current_period') }}
                    </p>
                    <p
                        class="mt-2 text-[26px] leading-none font-bold text-white"
                    >
                        {{ period.month }}
                    </p>
                    <p class="mt-1 text-xs text-[#989898]">
                        {{ period.year }} &middot;
                        {{
                            t('finance.calendar.day_of_month', {
                                day: period.dayOfMonth,
                                days: period.daysInMonth,
                            })
                        }}
                    </p>
                    <div
                        class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-white/10"
                    >
                        <div
                            class="h-full rounded-full bg-[#6C4EE9] transition-all duration-700"
                            :style="{ width: period.progress + '%' }"
                        />
                    </div>
                    <p class="mt-1.5 text-right text-xs text-[#989898]">
                        {{
                            t('finance.calendar.elapsed', {
                                progress: period.progress,
                            })
                        }}
                    </p>
                </div>
                <div
                    v-if="features?.bills.enabled"
                    class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#24212f]"
                            >
                                <CalendarClock
                                    class="size-[18px] text-[#6C4EE9]"
                                />
                            </span>
                            <p
                                class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                            >
                                {{ t('finance.bills.upcoming') }}
                            </p>
                        </div>
                        <Link
                            :href="
                                billsIndex.url({
                                    query: { currency: props.selectedCurrency },
                                })
                            "
                            class="text-xs text-[#6C4EE9] hover:underline"
                        >
                            {{ t('finance.bills.title') }}
                        </Link>
                    </div>

                    <ul
                        v-if="(props.upcomingBills ?? []).length > 0"
                        class="mt-4 flex flex-col gap-1"
                    >
                        <li
                            v-for="bill in props.upcomingBills ?? []"
                            :key="bill.occurrence_id"
                            class="flex items-center gap-3 rounded-xl px-2 py-2 transition hover:bg-white/5"
                        >
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-sm font-semibold text-white"
                                >
                                    <Ciphered
                                        :value="bill.title"
                                        table="bills"
                                    />
                                </p>
                                <p
                                    class="mt-0.5 truncate text-xs"
                                    :class="dueToneClass(bill)"
                                >
                                    {{ dueLabel(bill) }}
                                </p>
                            </div>
                            <div class="shrink-0 text-end">
                                <p class="text-sm font-bold text-white">
                                    <CipheredMoney
                                        :amount="bill.amount"
                                        :display-amount="bill.display_amount"
                                        :currency="bill.currency"
                                        :display-currency="
                                            bill.display_currency
                                        "
                                        :rates="props.rates"
                                        table="bills"
                                    />
                                </p>
                                <p class="text-[10px] text-[#989898]">
                                    {{ currencyLabel(bill.display_currency) }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#0d2e22] text-[#02CD86] transition hover:cursor-pointer hover:bg-[#02CD86] hover:text-[#0a0a0a] disabled:opacity-50"
                                :title="t('finance.bills.mark_paid')"
                                :aria-label="t('finance.bills.mark_paid')"
                                :disabled="payingOccurrenceId !== null"
                                @click="void markBillPaid(bill)"
                            >
                                <LoaderCircle
                                    v-if="
                                        payingOccurrenceId ===
                                        bill.occurrence_id
                                    "
                                    class="size-4 animate-spin"
                                />
                                <Check v-else class="size-4" />
                            </button>
                        </li>
                    </ul>

                    <p v-else class="mt-4 text-sm text-[#989898]">
                        {{ t('finance.bills.no_upcoming') }}
                    </p>
                </div>
                <div
                    class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                >
                    <p
                        class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                    >
                        {{ t('finance.dashboard.cost_optimize') }}
                    </p>
                    <p
                        class="mt-2 text-[26px] leading-none font-bold"
                        :class="
                            costOptimize >= 0
                                ? 'text-[#02CD86]'
                                : 'text-[#E94E50]'
                        "
                    >
                        {{ costOptimize >= 0 ? '+' : '' }}{{ costOptimize }}%
                    </p>
                    <p class="mt-1 text-xs text-[#989898]">
                        {{ t('finance.dashboard.savings_rate') }}
                    </p>
                    <div
                        class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-white/10"
                    >
                        <div
                            class="h-full rounded-full transition-all duration-700"
                            :class="
                                costOptimize >= 0
                                    ? 'bg-[#02CD86]'
                                    : 'bg-[#E94E50]'
                            "
                            :style="{
                                width:
                                    Math.min(100, Math.abs(costOptimize)) + '%',
                            }"
                        />
                    </div>
                </div>
                <div class="flex flex-col gap-3">
                    <button
                        type="button"
                        class="flex h-[60px] w-full items-center justify-between rounded-[16px] bg-[linear-gradient(90deg,#947BFF_0%,#6C4EE9_100%)] px-4 text-white shadow-[0_10px_24px_rgba(108,78,233,0.28)] transition hover:cursor-pointer hover:brightness-105 active:scale-[0.98]"
                        @click="openCreateForm('cost')"
                    >
                        <span class="text-[19px] font-bold">{{
                            t('finance.actions.add_cost')
                        }}</span>
                        <span
                            class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/20 bg-white/10 shadow-inner"
                        >
                            <Plus class="size-5" />
                        </span>
                    </button>

                    <button
                        type="button"
                        class="flex h-[60px] w-full items-center justify-between rounded-[16px] bg-[linear-gradient(90deg,#02CD86_0%,#00A96F_100%)] px-4 text-white shadow-[0_10px_24px_rgba(2,205,134,0.25)] transition hover:cursor-pointer hover:brightness-105 active:scale-[0.98]"
                        @click="openCreateForm('income')"
                    >
                        <span class="text-[19px] font-bold">{{
                            t('finance.actions.add_income')
                        }}</span>
                        <span
                            class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/20 bg-white/10 shadow-inner"
                        >
                            <Plus class="size-5" />
                        </span>
                    </button>
                </div>
            </div>
        </div>
        <div class="grid gap-[18px] px-[18px] pb-[38px] xl:grid-cols-2">
            <!-- ── Recently Costs ── -->
            <section
                class="kpi-card-cost overflow-hidden rounded-[22px] bg-[#1a1a1a] text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div class="flex items-center justify-between px-5 py-[24px]">
                    <h2 class="text-[20px] leading-none font-normal text-white">
                        {{ t('finance.dashboard.recent_costs') }}
                    </h2>
                    <Link
                        :href="transactionsIndex()"
                        class="text-xs text-[#6C4EE9] hover:underline"
                    >
                        {{ t('finance.actions.see_all') }}
                    </Link>
                </div>

                <div class="overflow-x-auto px-3 pb-4">
                    <table
                        class="w-full border-separate border-spacing-y-0 text-sm"
                    >
                        <thead>
                            <tr>
                                <th
                                    class="rounded-l-2xl bg-[#24212f] px-3 py-3.5 text-center text-sm font-normal text-[#d9d6ea] sm:px-5"
                                >
                                    {{ t('finance.fields.subject') }}
                                </th>
                                <th
                                    class="bg-[#24212f] px-3 py-3.5 text-center text-sm font-normal text-[#d9d6ea] sm:px-5"
                                >
                                    {{ t('finance.fields.category') }}
                                </th>
                                <th
                                    class="bg-[#24212f] px-3 py-3.5 text-center text-sm font-normal text-[#d9d6ea] sm:px-5"
                                >
                                    {{ t('finance.fields.amount') }}
                                </th>
                                <th
                                    class="rounded-r-2xl bg-[#24212f] px-3 py-3.5 text-center text-sm font-normal text-[#d9d6ea] sm:px-5"
                                ></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="transaction in recentCosts"
                                :key="transaction.id"
                                class="group"
                            >
                                <td
                                    class="px-3 py-3.5 text-center text-[16px] leading-none text-white sm:px-5"
                                >
                                    <button
                                        type="button"
                                        class="transition hover:text-[#6C4EE9]"
                                        @click="openEditForm(transaction)"
                                    >
                                        <Ciphered
                                            :value="transaction.title"
                                            table="transactions"
                                        />
                                    </button>
                                    <div
                                        v-if="transaction.description"
                                        class="mt-0.5 line-clamp-1 text-xs text-[#989898]"
                                    >
                                        <Ciphered
                                            :value="transaction.description"
                                            table="transactions"
                                        />
                                    </div>
                                </td>
                                <td class="px-3 py-3.5 text-center sm:px-5">
                                    <span
                                        class="inline-flex min-w-[90px] justify-center rounded-md bg-white/10 px-3 py-1.5 text-[15px] font-normal text-white"
                                    >
                                        {{ categoryName(transaction) }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-3.5 text-center text-[16px] leading-none font-semibold text-[#6C4EE9] sm:px-5"
                                >
                                    <CipheredMoney
                                        :amount="transaction.amount"
                                        :display-amount="
                                            transaction.display_amount
                                        "
                                        :currency="transaction.currency"
                                        :display-currency="
                                            transaction.display_currency
                                        "
                                        :rates="props.rates"
                                    />
                                </td>
                                <td class="px-3 py-3.5 text-center sm:px-5">
                                    <div
                                        class="flex items-center justify-center gap-1"
                                    >
                                        <button
                                            type="button"
                                            class="rounded-md p-1.5 hover:bg-white/10"
                                            @click="openEditForm(transaction)"
                                        >
                                            <Pencil
                                                class="size-3.5 text-[#6C4EE9]"
                                            />
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md p-1.5 hover:bg-[#fff0f0]"
                                            @click="requestDelete(transaction)"
                                        >
                                            <Trash2
                                                class="size-3.5 text-[#E94E50]"
                                            />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="recentCosts.length === 0">
                                <td
                                    colspan="4"
                                    class="px-5 py-10 text-center text-[#989898]"
                                >
                                    {{ t('finance.dashboard.no_costs') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <!-- ── Recently Incomes ── -->
            <section
                class="kpi-card-income overflow-hidden rounded-[22px] bg-[#1a1a1a] text-white shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div class="flex items-center justify-between px-5 py-[24px]">
                    <h2 class="text-[20px] leading-none font-normal text-white">
                        {{ t('finance.dashboard.recent_incomes') }}
                    </h2>
                    <Link
                        :href="transactionsIndex()"
                        class="text-xs text-[#02CD86] hover:underline"
                    >
                        {{ t('finance.actions.see_all') }}
                    </Link>
                </div>

                <div class="overflow-x-auto px-3 pb-4">
                    <table
                        class="w-full border-separate border-spacing-y-0 text-sm"
                    >
                        <thead>
                            <tr>
                                <th
                                    class="rounded-l-2xl bg-[#0d2620] px-3 py-3.5 text-center text-sm font-normal text-[#7ee8c4] sm:px-5"
                                >
                                    {{ t('finance.fields.subject') }}
                                </th>
                                <th
                                    class="bg-[#0d2620] px-3 py-3.5 text-center text-sm font-normal text-[#7ee8c4] sm:px-5"
                                >
                                    {{ t('finance.fields.category') }}
                                </th>
                                <th
                                    class="bg-[#0d2620] px-3 py-3.5 text-center text-sm font-normal text-[#7ee8c4] sm:px-5"
                                >
                                    {{ t('finance.fields.amount') }}
                                </th>
                                <th
                                    class="rounded-r-2xl bg-[#0d2620] px-3 py-3.5 text-center text-sm font-normal text-[#7ee8c4] sm:px-5"
                                ></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="transaction in recentIncomes"
                                :key="transaction.id"
                                class="group"
                            >
                                <td
                                    class="px-3 py-3.5 text-center text-[16px] leading-none text-white sm:px-5"
                                >
                                    <button
                                        type="button"
                                        class="transition hover:text-[#02CD86]"
                                        @click="openEditForm(transaction)"
                                    >
                                        <Ciphered
                                            :value="transaction.title"
                                            table="transactions"
                                        />
                                    </button>
                                    <div
                                        v-if="transaction.description"
                                        class="mt-0.5 line-clamp-1 text-xs text-[#989898]"
                                    >
                                        <Ciphered
                                            :value="transaction.description"
                                            table="transactions"
                                        />
                                    </div>
                                </td>
                                <td class="px-3 py-3.5 text-center sm:px-5">
                                    <span
                                        class="inline-flex min-w-[90px] justify-center rounded-md bg-white/10 px-3 py-1.5 text-[15px] font-normal text-white"
                                    >
                                        {{ categoryName(transaction) }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-3.5 text-center text-[16px] leading-none font-semibold text-[#02CD86] sm:px-5"
                                >
                                    <CipheredMoney
                                        :amount="transaction.amount"
                                        :display-amount="
                                            transaction.display_amount
                                        "
                                        :currency="transaction.currency"
                                        :display-currency="
                                            transaction.display_currency
                                        "
                                        :rates="props.rates"
                                    />
                                </td>
                                <td class="px-3 py-3.5 text-center sm:px-5">
                                    <div
                                        class="flex items-center justify-center gap-1"
                                    >
                                        <button
                                            type="button"
                                            class="rounded-md p-1.5 hover:bg-white/10"
                                            @click="openEditForm(transaction)"
                                        >
                                            <Pencil
                                                class="size-3.5 text-[#6C4EE9]"
                                            />
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md p-1.5 hover:bg-[#fff0f0]"
                                            @click="requestDelete(transaction)"
                                        >
                                            <Trash2
                                                class="size-3.5 text-[#E94E50]"
                                            />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="recentIncomes.length === 0">
                                <td
                                    colspan="4"
                                    class="px-5 py-10 text-center text-[#989898]"
                                >
                                    {{ t('finance.dashboard.no_incomes') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <TransactionDialog
            v-model:open="isDialogOpen"
            :type="dialogTransactionType"
            :transaction="editingTransaction"
            :categories="props.categories"
            :currencies="props.currencies"
        />

        <ConfirmDeleteModal
            :open="deleteTarget !== null"
            :title="
                t('finance.delete.transaction_title', {
                    title: deleteTarget?.title ?? '',
                })
            "
            :description="t('finance.delete.transaction_description')"
            @update:open="deleteTarget = null"
            @confirm="confirmDelete"
        />
    </div>
</template>

<script setup lang="ts">
import {
    Deferred,
    Head,
    Link,
    router,
    useForm,
    usePage,
} from '@inertiajs/vue3';
import { toJalaali } from 'jalaali-js';
import {
    CalendarClock,
    ChartPie,
    Check,
    LoaderCircle,
    Pencil,
    Plus,
    Trash2,
    TrendingDown,
    TrendingUp,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AssetIcon from '@/components/AssetIcon.vue';
import DonutChart from '@/components/charts/DonutChart.vue';
import LineChart from '@/components/charts/LineChart.vue';
import PulseChart from '@/components/charts/PulseChart.vue';
import Ciphered from '@/components/Ciphered.vue';
import CipheredMoney from '@/components/CipheredMoney.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import TransactionDialog from '@/components/transactions/TransactionDialog.vue';
import { useRelativeTime } from '@/composables/useRelativeTime';
import { useVault } from '@/composables/useVault';
import { useVaultPortfolio } from '@/composables/useVaultPortfolio';
import type { VaultPortfolioPayload } from '@/composables/useVaultPortfolio';
import { formatAppDate } from '@/lib/date';
import type { CurrencyCode, Rates } from '@/lib/money';
import { dashboard, portfolio as portfolioRoute } from '@/routes';
import { index as billsIndex } from '@/routes/bills';
import { pay as payBill } from '@/routes/bills/occurrences';
import { index as investmentsIndex } from '@/routes/investments';
import { index as transactionsIndex } from '@/routes/transactions';
import type { Encrypted } from '@/types/vault';

type TransactionType = 'cost' | 'income';
type Currency = 'toman' | 'usd' | 'eur';

type Category = {
    id: number;
    name: string;
    slug: string;
    type: TransactionType;
    is_default: boolean;
};

type Transaction = {
    id: number;
    type: TransactionType;
    amount: Encrypted<string>;
    currency: Currency;
    display_amount: string | null;
    display_currency: Currency;
    title: Encrypted<string>;
    description: Encrypted<string> | null;
    occurred_at: string;
    category: Category | null;
    category_id: number;
};

type CurrencyOption = { label: string; value: Currency };
type Period = {
    month: string;
    year: number;
    dayOfMonth: number;
    daysInMonth: number;
    progress: number;
};
type UpcomingBill = {
    occurrence_id: number;
    bill_id: number;
    title: Encrypted<string>;
    amount: Encrypted<string | number>;
    currency: Currency;
    display_amount: string | null;
    display_currency: Currency;
    category_name: string | null;
    due_date: string;
    is_overdue: boolean;
    is_due_today: boolean;
};

type PortfolioTopAsset = {
    key: string;
    label: string;
    color: string | null;
    icon: string | null;
    icon_svg: string | null;
    value_formatted: string;
    share: number;
};

type PortfolioSnapshot = {
    net_worth_formatted: string;
    pnl_percent: number | null;
    pnl_is_positive: boolean | null;
    pnl_formatted: string | null;
    has_cost_basis_data: boolean;
    asset_count: number;
    top_assets: PortfolioTopAsset[];
};

type HeadlinePrice = {
    key: string;
    label: string;
    icon: string | null;
    icon_svg: string | null;
    color: string | null;
    unit: string | null;
    price_formatted: string;
    available: boolean;
};

const props = defineProps<{
    transactions: { costs: Transaction[]; incomes: Transaction[] };
    categories: Record<TransactionType, Category[]>;
    currencies: CurrencyOption[];
    selectedCurrency: Currency;
    rates: Rates | null;
    summary: { cost: string; income: string } | null;
    period: Period;
    monthlyTrend: { label: string; income: number; cost: number }[];
    // Module props — absent entirely when the owning module is switched off.
    upcomingBills?: UpcomingBill[];
    pricesSyncedAt?: string | null;
    // Deferred props — undefined until the follow-up request lands, and never
    // sent at all when their module is off.
    portfolio?: PortfolioSnapshot | null;
    assetPrices?: HeadlinePrice[];
    /** Sent instead of `portfolio` when the vault is armed — see Portfolio.vue. */
    vaultPortfolio?: VaultPortfolioPayload | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const page = usePage();
const features = computed(() => page.props.features);
const { t } = useI18n();
const { isArmed, revealAsync, sealForSubmit } = useVault();

// Built here from decrypted holdings when the vault is armed, and handed straight
// through from the server otherwise.
const { snapshot: vaultSnapshot } = useVaultPortfolio(
    () => props.vaultPortfolio,
    () => props.selectedCurrency as CurrencyCode,
);

const portfolioSnapshot = computed(
    () => vaultSnapshot.value ?? props.portfolio ?? null,
);
const user = computed(
    () => (page.props.auth as { user?: { name: string } } | undefined)?.user,
);
const displayCalendar = computed(
    () => (page.props.calendar as string | undefined) ?? 'gregorian',
);
const period = computed(() => props.period);
const selectedCurrencyLabel = computed(
    () =>
        props.currencies.find(
            (currency) => currency.value === props.selectedCurrency,
        )?.label ?? t(`finance.currencies.${props.selectedCurrency}`),
);

const currencySymbol = computed(() => {
    switch (props.selectedCurrency) {
        case 'usd':
            return '$';
        case 'eur':
            return '€';
        default:
            return 'T';
    }
});

function currencyLabel(currencyValue: Currency): string {
    return (
        props.currencies.find((currency) => currency.value === currencyValue)
            ?.label ?? t(`finance.currencies.${currencyValue}`)
    );
}

function displayDate(value: string): string {
    return formatAppDate(value, displayCalendar.value);
}

function dueLabel(bill: UpcomingBill): string {
    if (bill.is_overdue) {
        return `${t('finance.bills.overdue')} · ${displayDate(bill.due_date)}`;
    }

    if (bill.is_due_today) {
        return t('finance.bills.due_today');
    }

    return displayDate(bill.due_date);
}

function dueToneClass(bill: UpcomingBill): string {
    if (bill.is_overdue) {
        return 'text-[#E94E50]';
    }

    if (bill.is_due_today) {
        return 'text-[#F59E0B]';
    }

    return 'text-[#989898]';
}

const availablePrices = computed(() =>
    (props.assetPrices ?? []).filter((price) => price.available),
);

const summaryIncome = computed(() => props.summary?.income ?? '0');
const summaryCost = computed(() => props.summary?.cost ?? '0');

function parseNum(value: string | number): number {
    return parseFloat(String(value).replace(/,/g, '')) || 0;
}

const incomeNum = computed(() => parseNum(summaryIncome.value));
const costNum = computed(() => parseNum(summaryCost.value));
const balance = computed(() => incomeNum.value - costNum.value);

const costOptimize = computed(() => {
    if (incomeNum.value <= 0) {
        return 0;
    }

    return Math.round(
        ((incomeNum.value - costNum.value) / incomeNum.value) * 100,
    );
});

const chartPalette = [
    '#02CD86',
    '#6C4EE9',
    '#F59E0B',
    '#3B82F6',
    '#E94E50',
    '#52525b',
];

// This month's costs grouped by category — top 5 plus an "other" bucket.
const categoryBreakdown = computed(() => {
    const totals = new Map<string, number>();

    for (const transaction of props.transactions.costs) {
        const name = categoryName(transaction);
        totals.set(
            name,
            (totals.get(name) ?? 0) + parseNum(transaction.display_amount ?? 0),
        );
    }

    const sorted = [...totals.entries()].sort(
        (left, right) => right[1] - left[1],
    );
    const top = sorted.slice(0, 5);
    const restTotal = sorted
        .slice(5)
        .reduce((acc, [, value]) => acc + value, 0);

    if (restTotal > 0) {
        top.push([t('finance.categories.cost.other'), restTotal]);
    }

    return {
        labels: top.map(([label]) => label),
        series: top.map(([, value]) => Math.round(value * 100) / 100),
        colors: top.map(
            (_, index) => chartPalette[index % chartPalette.length],
        ),
        total: top.reduce((acc, [, value]) => acc + value, 0),
    };
});

const trendSeries = computed(() => [
    {
        name: t('finance.metrics.income'),
        key: 'income',
        color: '#02CD86',
        data: props.monthlyTrend.map((month) => month.income),
    },
    {
        name: t('finance.metrics.costs'),
        key: 'cost',
        color: '#6C4EE9',
        data: props.monthlyTrend.map((month) => month.cost),
    },
]);

const trendLabels = computed(() =>
    props.monthlyTrend.map((month) => month.label),
);

function dayOfMonth(isoDate: string): number {
    const date = new Date(`${isoDate.slice(0, 10)}T00:00:00`);

    if (displayCalendar.value === 'jalali') {
        return toJalaali(
            date.getFullYear(),
            date.getMonth() + 1,
            date.getDate(),
        ).jd;
    }

    return date.getDate();
}

// Cost totals per day of the current (calendar-aware) month.
const dailySpending = computed(() => {
    const perDay = Array.from({ length: props.period.daysInMonth }, () => 0);

    for (const transaction of props.transactions.costs) {
        const day = dayOfMonth(transaction.occurred_at);

        if (day >= 1 && day <= perDay.length) {
            perDay[day - 1] += parseNum(transaction.display_amount ?? 0);
        }
    }

    return {
        data: perDay.map((value) => Math.round(value * 100) / 100),
        categories: perDay.map((_, index) => String(index + 1)),
    };
});

const recentCosts = computed(() => props.transactions.costs.slice(0, 5));
const recentIncomes = computed(() => props.transactions.incomes.slice(0, 5));

function formatAmount(amount: string | number): string {
    const numericAmount = Number(String(amount).replace(/,/g, ''));

    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 2,
        minimumFractionDigits: numericAmount % 1 === 0 ? 0 : 2,
    }).format(numericAmount);
}

const isDialogOpen = ref(false);
const dialogTransactionType = ref<TransactionType>('cost');
const editingTransaction = ref<Transaction | null>(null);

function categoryName(transaction: Transaction): string {
    if (transaction.category?.name) {
        return transaction.category.name;
    }

    return (
        (props.categories[transaction.type] ?? []).find(
            (category) => category.id === transaction.category_id,
        )?.name ?? t('finance.categories.uncategorized')
    );
}

const openCreateForm = (type: TransactionType) => {
    dialogTransactionType.value = type;
    editingTransaction.value = null;
    isDialogOpen.value = true;
};

const openEditForm = (transaction: Transaction) => {
    dialogTransactionType.value = transaction.type;
    editingTransaction.value = transaction;
    isDialogOpen.value = true;
};

const { formatRelativeTime } = useRelativeTime();

const syncedAgo = computed(() =>
    props.pricesSyncedAt ? formatRelativeTime(props.pricesSyncedAt) : null,
);

const payingOccurrenceId = ref<number | null>(null);

/**
 * Under the vault the browser has to seal the Cost transaction this generates:
 * the bill's ciphertext is bound to the `bills` table by its AAD, so it cannot be
 * copied across, and the server holds no key to re-seal it.
 */
async function markBillPaid(bill: UpcomingBill): Promise<void> {
    if (payingOccurrenceId.value !== null) {
        return;
    }

    payingOccurrenceId.value = bill.occurrence_id;

    const payload: { title?: string; amount?: string } = {};

    if (isArmed()) {
        const title = await revealAsync<string>(bill.title, 'bills');
        const amount = await revealAsync<string | number>(
            bill.amount,
            'bills',
            'decimal',
        );

        if (title === undefined || amount === undefined) {
            payingOccurrenceId.value = null;

            return;
        }

        const sealed = await sealForSubmit(
            { title, amount: String(amount) },
            'transactions',
            { title: 'string', amount: 'decimal' },
        );

        payload.title = sealed.title;
        payload.amount = sealed.amount;
    }

    const payForm = useForm(payload);
    payForm.post(
        payBill.url({ bill: bill.bill_id, occurrence: bill.occurrence_id }),
        {
            preserveScroll: true,
            onFinish: () => {
                payingOccurrenceId.value = null;
            },
        },
    );
}

const deleteTarget = ref<Transaction | null>(null);

const requestDelete = (transaction: Transaction) => {
    deleteTarget.value = transaction;
};

const confirmDelete = () => {
    if (!deleteTarget.value) {
        return;
    }

    router.delete(`/transactions/${deleteTarget.value.id}`, {
        preserveScroll: true,
    });
    deleteTarget.value = null;
};
</script>
