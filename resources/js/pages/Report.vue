<template>
    <Head :title="t('finance.reports.title')" />

    <div
        class="finance-dense flex min-h-[calc(100vh-92px)] shrink-0 flex-col overflow-x-hidden bg-[#111111]"
    >
        <!-- Hero / period summary -->
        <section
            class="mx-[18px] mt-5 rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
        >
            <div
                class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between"
            >
                <div class="max-w-2xl space-y-3">
                    <p
                        class="text-xs font-semibold tracking-[0.35em] text-[#6C4EE9] uppercase"
                    >
                        {{ t('finance.reports.eyebrow') }}
                    </p>
                    <div class="space-y-2">
                        <h1
                            class="text-3xl font-semibold tracking-tight text-white sm:text-4xl"
                        >
                            {{ t('finance.reports.heading') }}
                        </h1>
                        <p class="text-sm text-[#989898]">
                            {{ t('finance.reports.description') }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:min-w-xl">
                    <div
                        class="rounded-[14px] border border-white/10 bg-[#252525] p-4 shadow-xs"
                    >
                        <p
                            class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                        >
                            {{ t('finance.fields.range') }}
                        </p>
                        <p class="mt-2 text-sm font-semibold text-white">
                            {{ props.period.label }}
                        </p>
                    </div>

                    <div
                        class="rounded-[14px] border border-white/10 bg-[#252525] p-4 shadow-xs"
                    >
                        <p
                            class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                        >
                            {{ t('finance.reports.transactions') }}
                        </p>
                        <p class="mt-2 text-sm font-semibold text-white">
                            {{ props.summary.count }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Filters -->
        <section
            class="mx-[18px] mt-[18px] rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
        >
            <div class="flex flex-col gap-5">
                <div class="flex flex-col gap-4">
                    <!-- Range buttons -->
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="range in ranges"
                            :key="range.value"
                            type="button"
                            :class="[
                                'rounded-full px-4 py-1.5 text-sm font-normal transition',
                                selectedRange === range.value
                                    ? 'bg-[#02cd86] text-[#101010]'
                                    : 'bg-white/5 text-[#989898] ring-1 ring-white/10 hover:bg-white/10 hover:text-white',
                            ]"
                            @click="selectRange(range.value)"
                        >
                            {{ range.label }}
                        </button>
                    </div>
                </div>

                <!-- Search / type / category filters -->
                <div class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr_0.9fr_auto]">
                    <div class="grid gap-2">
                        <Label for="report_search">{{
                            t('finance.fields.search')
                        }}</Label>
                        <Input
                            id="report_search"
                            v-model="search"
                            :class="filterFieldClass"
                            :placeholder="t('finance.filters.title_or_note')"
                            @keyup.enter="applyFilters()"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="report_type">{{
                            t('finance.fields.type')
                        }}</Label>
                        <Select
                            v-model="selectedType"
                            @update:model-value="applyTypeFilter"
                        >
                            <SelectTrigger
                                id="report_type"
                                :class="filterFieldClass"
                            >
                                <SelectValue
                                    :placeholder="
                                        t('finance.filters.all_types')
                                    "
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{{
                                    t('finance.filters.all_types')
                                }}</SelectItem>
                                <SelectItem value="cost">{{
                                    t('finance.filters.costs')
                                }}</SelectItem>
                                <SelectItem value="income">{{
                                    t('finance.filters.incomes')
                                }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="report_category">{{
                            t('finance.fields.category')
                        }}</Label>
                        <Select
                            v-model="selectedCategory"
                            @update:model-value="applyFilters()"
                        >
                            <SelectTrigger
                                id="report_category"
                                :class="filterFieldClass"
                            >
                                <SelectValue
                                    :placeholder="
                                        t('finance.filters.all_categories')
                                    "
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{{
                                    t('finance.filters.all_categories')
                                }}</SelectItem>
                                <SelectItem
                                    v-for="category in reportCategories"
                                    :key="category.id"
                                    :value="category.id.toString()"
                                >
                                    {{ category.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex flex-wrap items-end gap-2 xl:justify-end">
                        <Button
                            class="shrink-0 rounded-full bg-white/10 px-5 text-white shadow-none ring-1 ring-white/20 hover:bg-white/15"
                            @click="applyFilters()"
                        >
                            <Search class="size-4" />
                            {{ t('finance.actions.filter') }}
                        </Button>
                        <Button
                            variant="outline"
                            class="size-9 shrink-0 rounded-full bg-white/10 p-0 text-[#989898] ring-1 ring-white/15 hover:bg-white/15"
                            @click="clearTransactionFilters"
                        >
                            <RotateCcw class="size-4" />
                            <span class="sr-only">{{
                                t('finance.actions.reset_filters')
                            }}</span>
                        </Button>
                    </div>
                </div>

                <!-- Hidden entirely when there is no investment category to
                     exclude, so the toggle is never a no-op. -->
                <label
                    v-if="props.hasInvestmentCategory"
                    class="flex w-fit cursor-pointer items-center gap-2.5"
                >
                    <Checkbox
                        :checked="excludeInvestments"
                        @update:checked="applyExcludeInvestments"
                    />
                    <span class="text-sm text-white/85">
                        {{ t('finance.filters.exclude_investments') }}
                    </span>
                    <span class="text-xs text-[#6b6b6b]">
                        {{ t('finance.filters.exclude_investments_hint') }}
                    </span>
                </label>

                <!-- Custom date range -->
                <div
                    v-if="selectedRange === 'custom'"
                    class="grid gap-4 lg:grid-cols-[1fr_1fr_auto]"
                >
                    <div class="grid gap-2">
                        <Label for="from_date">{{
                            t('finance.fields.from')
                        }}</Label>
                        <BirthdatePicker
                            v-model="fromDate"
                            name="from_date"
                            :required="false"
                            :years-back="16"
                            :years-forward="1"
                            :trigger-class="filterFieldClass"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="to_date">{{
                            t('finance.fields.to')
                        }}</Label>
                        <BirthdatePicker
                            v-model="toDate"
                            name="to_date"
                            :required="false"
                            :years-back="16"
                            :years-forward="1"
                            :trigger-class="filterFieldClass"
                        />
                    </div>

                    <div class="flex items-end">
                        <Button
                            class="w-full rounded-full bg-white/10 px-5 text-white shadow-none ring-1 ring-white/20 hover:bg-white/15 lg:w-auto"
                            @click="applyFilters()"
                        >
                            {{ t('finance.actions.apply_custom_range') }}
                        </Button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Summary cards -->
        <div
            class="grid gap-[18px] px-[18px] pt-[18px] sm:grid-cols-2 xl:grid-cols-4"
        >
            <article
                class="kpi-card-income overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <p
                    class="text-xs font-medium tracking-[0.2em] text-[#02CD86] uppercase"
                >
                    {{ t('finance.metrics.income') }}
                </p>
                <p class="mt-3 text-2xl font-semibold text-white">
                    <span v-if="incomeTotal !== null" :class="maskClass">{{
                        formatMoney(
                            incomeTotal.toFixed(2),
                            props.selectedCurrency,
                        )
                    }}</span>
                    <span
                        v-else
                        aria-hidden="true"
                        class="inline-block h-[1em] w-32 animate-pulse rounded bg-white/10 align-middle"
                    />
                </p>
            </article>

            <article
                class="kpi-card-cost overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <p
                    class="text-xs font-medium tracking-[0.2em] text-[#6C4EE9] uppercase"
                >
                    {{ costsLabel }}
                </p>
                <p class="mt-3 text-2xl font-semibold text-white">
                    <span v-if="costTotal !== null" :class="maskClass">{{
                        formatMoney(
                            costTotal.toFixed(2),
                            props.selectedCurrency,
                        )
                    }}</span>
                    <span
                        v-else
                        aria-hidden="true"
                        class="inline-block h-[1em] w-32 animate-pulse rounded bg-white/10 align-middle"
                    />
                </p>
            </article>

            <article
                class="kpi-card-neutral overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <p
                    class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                >
                    {{ t('finance.metrics.balance') }}
                </p>
                <p class="mt-3 text-2xl font-semibold text-white">
                    <span v-if="balanceLabel !== null" :class="maskClass">{{
                        balanceLabel
                    }}</span>
                    <span
                        v-else
                        aria-hidden="true"
                        class="inline-block h-[1em] w-32 animate-pulse rounded bg-white/10 align-middle"
                    />
                </p>
            </article>

            <article
                class="kpi-card-neutral overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <p
                    class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                >
                    {{ t('finance.metrics.entries') }}
                </p>
                <p class="mt-3 text-2xl font-semibold text-white">
                    {{ props.summary.count }}
                </p>
            </article>
        </div>

        <!-- The read: a narrative built from this period's real numbers,
             not a restatement of the KPI cards above it. -->
        <section
            v-if="reportReady"
            class="mx-[18px] mt-[18px] rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
        >
            <p
                class="text-xs font-semibold tracking-[0.2em] text-[#02CD86] uppercase"
            >
                {{ t('finance.reports.read_eyebrow') }}
            </p>
            <h2
                class="mt-2 text-2xl leading-tight font-semibold text-white sm:text-[26px]"
                :class="maskClass"
            >
                {{ reportHeadline }}
            </h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-[#989898]">
                {{ reportBody }}
            </p>
        </section>

        <!-- Charts row -->
        <div
            v-if="props.summary.count > 0 && chartsReady"
            class="grid items-stretch gap-[18px] px-[18px] pt-[18px] md:grid-cols-3"
        >
            <!-- Cash flow over time — income vs costs -->
            <section
                class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-[18px] leading-none font-normal text-white">
                        {{ t('finance.reports.cash_flow') }}
                    </h2>
                    <span class="text-xs text-[#989898]">{{
                        bucketMode === 'day'
                            ? t('finance.reports.by_day')
                            : t('finance.reports.by_month')
                    }}</span>
                </div>
                <LineChart
                    :series="cashFlowSeries"
                    :categories="bucketLabels"
                    raw-labels
                    :height="280"
                />
            </section>

            <!-- Top spending categories — ranked -->
            <section
                class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-[18px] leading-none font-normal text-white">
                        {{ t('finance.reports.top_spending') }}
                    </h2>
                    <span class="text-xs text-[#989898]">{{
                        t('finance.reports.by_category')
                    }}</span>
                </div>
                <RankedBarChart
                    v-if="topSpending.values.length > 0"
                    :labels="topSpending.labels"
                    :values="topSpending.values"
                    :colors="topSpending.colors"
                    :series-name="t('finance.metrics.costs')"
                    :height="280"
                />
                <p v-else class="mt-8 text-center text-sm text-[#989898]">
                    {{ t('finance.dashboard.no_costs') }}
                </p>
            </section>

            <!-- Net savings per bucket -->
            <section
                class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-[18px] leading-none font-normal text-white">
                        {{ t('finance.reports.net_savings') }}
                    </h2>
                    <span class="text-xs text-[#989898]">{{
                        bucketMode === 'day'
                            ? t('finance.reports.by_day')
                            : t('finance.reports.by_month')
                    }}</span>
                </div>
                <PulseChart
                    :data="netSavings"
                    :categories="bucketLabels"
                    :series-name="t('finance.metrics.balance')"
                    color="#02CD86"
                    highlight-color="#E94E50"
                    color-mode="sign"
                    :height="280"
                />
            </section>
        </div>

        <!-- Transaction tables -->
        <div
            class="grid items-start gap-[18px] px-[18px] py-[18px] pb-[38px] lg:grid-cols-2"
        >
            <!-- Costs table -->
            <section
                class="kpi-card-cost overflow-hidden rounded-[22px] bg-[#1a1a1a] shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div
                    class="flex items-center justify-between gap-4 px-5 py-[29px]"
                >
                    <div>
                        <p class="text-xs font-medium text-[#6C4EE9] uppercase">
                            {{ t('finance.metrics.costs') }}
                        </p>
                        <h2
                            class="text-[22px] leading-none font-normal text-white"
                        >
                            {{ t('finance.tables.money_going_out') }}
                        </h2>
                    </div>
                    <span class="text-xs text-[#989898]">
                        {{ props.transactions.meta.costs.total }}
                        {{ t('finance.metrics.entries') }}
                    </span>
                </div>

                <div class="overflow-x-auto px-3 pb-5">
                    <table
                        class="w-full border-separate border-spacing-y-0 text-sm"
                    >
                        <thead>
                            <tr class="text-left text-base">
                                <th
                                    class="rounded-l-2xl bg-[#252525] px-3 py-4 text-center font-normal text-[#989898] sm:px-5"
                                >
                                    {{ t('finance.fields.subject') }}
                                </th>
                                <th
                                    class="bg-[#252525] px-3 py-4 text-center font-normal text-[#989898] sm:px-5"
                                >
                                    {{ t('finance.fields.category') }}
                                </th>
                                <th
                                    class="bg-[#252525] px-3 py-4 text-center font-normal text-[#989898] sm:px-5"
                                >
                                    {{ t('finance.fields.amount') }}
                                </th>
                                <th
                                    class="hidden rounded-r-2xl bg-[#252525] px-3 py-4 text-center font-normal text-[#989898] sm:table-cell sm:px-5"
                                >
                                    {{ t('finance.fields.date') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="transaction in props.transactions.costs"
                                :key="transaction.id"
                                class="group"
                            >
                                <td
                                    class="px-3 py-[17px] text-center text-[17px] leading-none font-normal text-white sm:px-5"
                                >
                                    <Ciphered
                                        :value="transaction.title"
                                        table="transactions"
                                    />
                                    <div
                                        v-if="transaction.description"
                                        class="mt-1 line-clamp-1 text-xs text-[#989898]"
                                    >
                                        <Ciphered
                                            :value="transaction.description"
                                            table="transactions"
                                        />
                                    </div>
                                </td>
                                <td class="px-3 py-[17px] text-center sm:px-5">
                                    <span
                                        class="inline-flex min-w-[118px] justify-center rounded-md bg-white/10 px-4 py-2 text-[17px] leading-none font-normal text-white"
                                    >
                                        {{
                                            transaction.category?.name ??
                                            t(
                                                'finance.categories.uncategorized',
                                            )
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-[17px] text-center text-[17px] leading-none font-semibold text-[#6C4EE9] sm:px-5"
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
                                <td
                                    class="hidden px-3 py-[17px] text-center text-[17px] leading-none font-normal text-white sm:table-cell sm:px-5"
                                >
                                    {{ displayDate(transaction.occurred_at) }}
                                </td>
                            </tr>
                            <tr v-if="props.transactions.costs.length === 0">
                                <td
                                    colspan="4"
                                    class="px-5 py-12 text-center text-[#989898]"
                                >
                                    {{ t('finance.dashboard.no_costs') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="props.transactions.meta.costs.last_page > 1"
                    class="flex items-center justify-center gap-3 border-t border-white/[0.07] px-5 py-3 text-xs"
                >
                    <button
                        type="button"
                        :aria-label="t('buttons.back')"
                        :disabled="
                            props.transactions.meta.costs.current_page <= 1
                        "
                        class="rounded-full bg-white/10 px-3 py-1.5 text-white ring-1 ring-white/15 transition-colors hover:bg-white/15 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeCostPage(
                                props.transactions.meta.costs.current_page - 1,
                            )
                        "
                    >
                        ←
                    </button>
                    <span class="text-[#989898]">
                        {{ props.transactions.meta.costs.current_page }}
                        /
                        {{ props.transactions.meta.costs.last_page }}
                        <span class="ml-1 text-[#6b6b6b]">
                            ({{ props.transactions.meta.costs.total }})
                        </span>
                    </span>
                    <button
                        type="button"
                        :aria-label="t('buttons.next')"
                        :disabled="
                            props.transactions.meta.costs.current_page >=
                            props.transactions.meta.costs.last_page
                        "
                        class="rounded-full bg-white/10 px-3 py-1.5 text-white ring-1 ring-white/15 transition-colors hover:bg-white/15 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeCostPage(
                                props.transactions.meta.costs.current_page + 1,
                            )
                        "
                    >
                        →
                    </button>
                </div>
            </section>

            <!-- Incomes table -->
            <section
                class="kpi-card-income overflow-hidden rounded-[22px] bg-[#1a1a1a] shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div
                    class="flex items-center justify-between gap-4 px-5 py-[29px]"
                >
                    <div>
                        <p class="text-xs font-medium text-[#02CD86] uppercase">
                            {{ t('finance.filters.incomes') }}
                        </p>
                        <h2
                            class="text-[22px] leading-none font-normal text-white"
                        >
                            {{ t('finance.tables.money_coming_in') }}
                        </h2>
                    </div>
                    <span class="text-xs text-[#989898]">
                        {{ props.transactions.meta.incomes.total }}
                        {{ t('finance.metrics.entries') }}
                    </span>
                </div>

                <div class="overflow-x-auto px-3 pb-5">
                    <table
                        class="w-full border-separate border-spacing-y-0 text-sm"
                    >
                        <thead>
                            <tr class="text-left text-base">
                                <th
                                    class="rounded-l-2xl bg-[#252525] px-3 py-4 text-center font-normal text-[#989898] sm:px-5"
                                >
                                    {{ t('finance.fields.subject') }}
                                </th>
                                <th
                                    class="bg-[#252525] px-3 py-4 text-center font-normal text-[#989898] sm:px-5"
                                >
                                    {{ t('finance.fields.category') }}
                                </th>
                                <th
                                    class="bg-[#252525] px-3 py-4 text-center font-normal text-[#989898] sm:px-5"
                                >
                                    {{ t('finance.fields.amount') }}
                                </th>
                                <th
                                    class="hidden rounded-r-2xl bg-[#252525] px-3 py-4 text-center font-normal text-[#989898] sm:table-cell sm:px-5"
                                >
                                    {{ t('finance.fields.date') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="transaction in props.transactions
                                    .incomes"
                                :key="transaction.id"
                                class="group"
                            >
                                <td
                                    class="px-3 py-[17px] text-center text-[17px] leading-none font-normal text-white sm:px-5"
                                >
                                    <Ciphered
                                        :value="transaction.title"
                                        table="transactions"
                                    />
                                    <div
                                        v-if="transaction.description"
                                        class="mt-1 line-clamp-1 text-xs text-[#989898]"
                                    >
                                        <Ciphered
                                            :value="transaction.description"
                                            table="transactions"
                                        />
                                    </div>
                                </td>
                                <td class="px-3 py-[17px] text-center sm:px-5">
                                    <span
                                        class="inline-flex min-w-[118px] justify-center rounded-md bg-white/10 px-4 py-2 text-[17px] leading-none font-normal text-white"
                                    >
                                        {{
                                            transaction.category?.name ??
                                            t(
                                                'finance.categories.uncategorized',
                                            )
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-[17px] text-center text-[17px] leading-none font-semibold text-[#02CD86] sm:px-5"
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
                                <td
                                    class="hidden px-3 py-[17px] text-center text-[17px] leading-none font-normal text-white sm:table-cell sm:px-5"
                                >
                                    {{ displayDate(transaction.occurred_at) }}
                                </td>
                            </tr>
                            <tr v-if="props.transactions.incomes.length === 0">
                                <td
                                    colspan="4"
                                    class="px-5 py-12 text-center text-[#989898]"
                                >
                                    {{ t('finance.dashboard.no_incomes') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="props.transactions.meta.incomes.last_page > 1"
                    class="flex items-center justify-center gap-3 border-t border-white/[0.07] px-5 py-3 text-xs"
                >
                    <button
                        type="button"
                        :aria-label="t('buttons.back')"
                        :disabled="
                            props.transactions.meta.incomes.current_page <= 1
                        "
                        class="rounded-full bg-white/10 px-3 py-1.5 text-white ring-1 ring-white/15 transition-colors hover:bg-white/15 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeIncomePage(
                                props.transactions.meta.incomes.current_page -
                                    1,
                            )
                        "
                    >
                        ←
                    </button>
                    <span class="text-[#989898]">
                        {{ props.transactions.meta.incomes.current_page }}
                        /
                        {{ props.transactions.meta.incomes.last_page }}
                        <span class="ml-1 text-[#6b6b6b]">
                            ({{ props.transactions.meta.incomes.total }})
                        </span>
                    </span>
                    <button
                        type="button"
                        :aria-label="t('buttons.next')"
                        :disabled="
                            props.transactions.meta.incomes.current_page >=
                            props.transactions.meta.incomes.last_page
                        "
                        class="rounded-full bg-white/10 px-3 py-1.5 text-white ring-1 ring-white/15 transition-colors hover:bg-white/15 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeIncomePage(
                                props.transactions.meta.incomes.current_page +
                                    1,
                            )
                        "
                    >
                        →
                    </button>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { RotateCcw, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BirthdatePicker from '@/components/BirthdatePicker.vue';
import LineChart from '@/components/charts/LineChart.vue';
import PulseChart from '@/components/charts/PulseChart.vue';
import RankedBarChart from '@/components/charts/RankedBarChart.vue';
import Ciphered from '@/components/Ciphered.vue';
import CipheredMoney from '@/components/CipheredMoney.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAmountMask } from '@/composables/useAmountMask';
import { useDisplayAmounts } from '@/composables/useDisplayAmounts';
import {
    dayBucketsBetween,
    formatAppDate,
    formatChartDateLabel,
    monthBucketKeyFromIso,
    monthBucketsBetween,
} from '@/lib/date';
import type { Rates } from '@/lib/money';
import { dashboard, report } from '@/routes';
import type { Encrypted } from '@/types/vault';

type ReportRange = 'this_month' | 'this_season' | 'yearly' | 'custom';
type TransactionType = 'cost' | 'income';
type FilterType = TransactionType | 'all';
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
    /** Null under the vault — the browser converts from `amount` instead. */
    display_amount: string | null;
    display_currency: Currency;
    title: Encrypted<string>;
    description: Encrypted<string> | null;
    occurred_at: string;
    category: Category | null;
    category_id: number;
};

type CurrencyOption = {
    label: string;
    value: Currency;
};

type PaginationMeta = {
    current_page: number;
    last_page: number;
    total: number;
};

const props = defineProps<{
    filters: {
        range: ReportRange;
        from: string;
        to: string;
        search: string;
        type: FilterType;
        category: number | null;
        exclude_investments: boolean;
    };
    /** False when the account has no investment cost category to exclude. */
    hasInvestmentCategory: boolean;
    period: {
        label: string;
    };
    transactions: {
        costs: Transaction[];
        incomes: Transaction[];
        meta: {
            costs: PaginationMeta;
            incomes: PaginationMeta;
        };
    };
    analyticsTransactions: {
        costs: Transaction[];
        incomes: Transaction[];
    };
    categories: Record<TransactionType, Category[]>;
    currencies: CurrencyOption[];
    selectedCurrency: Currency;
    /** Non-null only under the vault, where the browser does the converting. */
    rates: Rates | null;
    summary: {
        /** Null under the vault — the server cannot sum what it cannot read. */
        cost: string | null;
        income: string | null;
        count: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Reports',
                href: report(),
            },
        ],
    },
});

const { t } = useI18n();
const { masked } = useAmountMask();
const maskClass = computed(() =>
    masked.value
        ? 'blur-[6px] transition-[filter] duration-150 select-none'
        : 'transition-[filter] duration-150',
);

const ranges = computed<Array<{ label: string; value: ReportRange }>>(() => [
    { label: t('finance.reports.this_month'), value: 'this_month' },
    { label: t('finance.reports.this_season'), value: 'this_season' },
    { label: t('finance.reports.yearly'), value: 'yearly' },
    { label: t('finance.reports.custom'), value: 'custom' },
]);

const filterFieldClass =
    'h-9 w-full rounded-md !border-white/10 !bg-[#252525] px-3 text-sm font-normal !text-white shadow-none [color-scheme:dark] placeholder:!text-[#686868] focus-visible:!border-[#947BFF] focus-visible:!ring-2 focus-visible:!ring-[#947BFF]/25 [&_svg]:!text-[#989898]';

const selectedRange = ref<ReportRange>(props.filters.range);
const fromDate = ref(props.filters.from);
const toDate = ref(props.filters.to);
const search = ref(props.filters.search);
const selectedType = ref<FilterType>(props.filters.type);
const selectedCategory = ref(props.filters.category?.toString() ?? 'all');
const excludeInvestments = ref(props.filters.exclude_investments);

/**
 * Names what the figure below it actually is.
 *
 * Without this the card reads "COSTS" over a number that is deliberately not all
 * of them — which is the kind of quiet mismatch someone reconciles against their
 * bank statement and cannot explain.
 */
const costsLabel = computed(() =>
    excludeInvestments.value
        ? t('finance.metrics.costs_excluding_investments')
        : t('finance.metrics.costs'),
);
const page = usePage();
const displayCalendar = computed(
    () => (page.props.calendar as string | undefined) ?? 'gregorian',
);

/**
 * Every amount in the range, converted to the selected currency.
 *
 * Handed back untouched when the server could do the conversion; decrypted and
 * converted here when the vault left it ciphertext. Every total and every chart
 * on this page reads from this one map, so there is no path where half of them
 * are computed one way and half the other.
 */
const analyticsRows = computed(() => [
    ...props.analyticsTransactions.incomes,
    ...props.analyticsTransactions.costs,
]);

const {
    amounts: displayAmounts,
    ready: chartsReady,
    totalOf,
} = useDisplayAmounts(
    () => analyticsRows.value,
    () => props.selectedCurrency,
    () => props.rates,
);

/** The converted amount for one row, or 0 while the set is still resolving. */
function amountOf(transaction: Transaction): number {
    return displayAmounts.value?.get(transaction.id) ?? 0;
}

const incomeTotal = computed(() =>
    totalOf(props.analyticsTransactions.incomes),
);
const costTotal = computed(() => totalOf(props.analyticsTransactions.costs));

const balanceLabel = computed(() => {
    if (incomeTotal.value === null || costTotal.value === null) {
        return null;
    }

    return formatMoney(
        (incomeTotal.value - costTotal.value).toFixed(2),
        props.selectedCurrency,
    );
});

const chartPalette = [
    '#02CD86',
    '#6C4EE9',
    '#947BFF',
    '#E94E50',
    '#F2B441',
    '#4EA1E9',
    '#E94EBE',
    '#7ee8c4',
];

// ── Time bucketing: days for short ranges, months for season/year ──

const rangeSpanDays = computed(() => {
    const from = new Date(`${props.filters.from}T00:00:00`).getTime();
    const to = new Date(`${props.filters.to}T00:00:00`).getTime();

    if (isNaN(from) || isNaN(to)) {
        return 0;
    }

    return Math.round((to - from) / 86_400_000) + 1;
});

const bucketMode = computed<'day' | 'month'>(() =>
    rangeSpanDays.value > 0 && rangeSpanDays.value <= 45 ? 'day' : 'month',
);

const buckets = computed(() => {
    if (bucketMode.value === 'day') {
        return dayBucketsBetween(props.filters.from, props.filters.to).map(
            (iso) => ({
                key: iso,
                label: formatChartDateLabel(iso, displayCalendar.value),
            }),
        );
    }

    return monthBucketsBetween(
        props.filters.from,
        props.filters.to,
        displayCalendar.value,
        'en-US',
    );
});

const bucketLabels = computed(() => buckets.value.map((b) => b.label));

function bucketKeyFor(isoDate: string): string {
    const iso = isoDate.slice(0, 10);

    return bucketMode.value === 'day'
        ? iso
        : monthBucketKeyFromIso(iso, displayCalendar.value);
}

const flowByBucket = computed(() => {
    const totals = new Map<string, { income: number; cost: number }>();

    for (const bucket of buckets.value) {
        totals.set(bucket.key, { income: 0, cost: 0 });
    }

    for (const transaction of props.analyticsTransactions.incomes) {
        const entry = totals.get(bucketKeyFor(transaction.occurred_at));

        if (entry) {
            entry.income += amountOf(transaction);
        }
    }

    for (const transaction of props.analyticsTransactions.costs) {
        const entry = totals.get(bucketKeyFor(transaction.occurred_at));

        if (entry) {
            entry.cost += amountOf(transaction);
        }
    }

    return totals;
});

const cashFlowSeries = computed(() => [
    {
        name: t('finance.metrics.income'),
        key: 'income',
        color: '#02CD86',
        data: buckets.value.map(
            (bucket) => flowByBucket.value.get(bucket.key)?.income ?? 0,
        ),
    },
    {
        name: t('finance.metrics.costs'),
        key: 'cost',
        color: '#6C4EE9',
        data: buckets.value.map(
            (bucket) => flowByBucket.value.get(bucket.key)?.cost ?? 0,
        ),
    },
]);

const netSavings = computed(() =>
    buckets.value.map((bucket) => {
        const entry = flowByBucket.value.get(bucket.key);

        return (
            Math.round(((entry?.income ?? 0) - (entry?.cost ?? 0)) * 100) / 100
        );
    }),
);

// ── Top spending categories, ranked ──

const topSpending = computed(() => {
    const totals = new Map<string, number>();

    for (const transaction of props.analyticsTransactions.costs) {
        const name =
            transaction.category?.name ?? t('finance.categories.uncategorized');
        totals.set(name, (totals.get(name) ?? 0) + amountOf(transaction));
    }

    const entries = [...totals.entries()]
        .sort((a, b) => b[1] - a[1])
        .slice(0, 6);

    return {
        labels: entries.map(([name]) => name),
        values: entries.map(([, total]) => Math.round(total * 100) / 100),
        colors: entries.map(
            (_, index) => chartPalette[index % chartPalette.length],
        ),
    };
});

/** True once both totals have a real number behind them, so the narrative
 *  card can wait for the same signal the KPI cards already wait for. */
const reportReady = computed(
    () => incomeTotal.value !== null && costTotal.value !== null,
);

const reportHeadline = computed(() => {
    if (incomeTotal.value === null || costTotal.value === null) {
        return '';
    }

    const balance = incomeTotal.value - costTotal.value;
    const amount = formatMoney(
        Math.abs(balance).toFixed(2),
        props.selectedCurrency,
    );

    return balance >= 0
        ? t('finance.reports.read_headline_positive', { amount })
        : t('finance.reports.read_headline_negative', { amount });
});

/** Names the single biggest real driver behind the headline — the top
 *  spending category and its share — rather than a generic restatement of
 *  the totals already on screen above it. */
const reportBody = computed(() => {
    if (topSpending.value.labels.length === 0) {
        return t('finance.reports.read_body_no_spending');
    }

    const topLabel = topSpending.value.labels[0];
    const topValue = topSpending.value.values[0] ?? 0;
    const totalCost = costTotal.value ?? 0;
    const percent =
        totalCost > 0 ? Math.round((topValue / totalCost) * 100) : 0;

    return excludeInvestments.value
        ? t('finance.reports.read_body_transfers', {
              category: topLabel,
              percent,
          })
        : t('finance.reports.read_body_spending', {
              category: topLabel,
              percent,
          });
});

const reportCategories = computed(() => {
    if (selectedType.value === 'cost' || selectedType.value === 'income') {
        return props.categories[selectedType.value] ?? [];
    }

    return [...props.categories.cost, ...props.categories.income];
});

watch(
    () => props.filters,
    (filters) => {
        selectedRange.value = filters.range;
        fromDate.value = filters.from;
        toDate.value = filters.to;
        search.value = filters.search;
        selectedType.value = filters.type;
        selectedCategory.value = filters.category?.toString() ?? 'all';
        excludeInvestments.value = filters.exclude_investments;
    },
    { deep: true },
);

watch(selectedType, () => {
    if (
        selectedCategory.value !== 'all' &&
        !reportCategories.value.some(
            (category) => category.id.toString() === selectedCategory.value,
        )
    ) {
        selectedCategory.value = 'all';
    }
});

function selectRange(range: ReportRange): void {
    selectedRange.value = range;

    if (range === 'custom') {
        return;
    }

    applyFilters(range);
}

function applyFilters(
    range: ReportRange = selectedRange.value,
    costPage: number | null = null,
    incomePage: number | null = null,
): void {
    router.get(
        report.url(),
        {
            range,
            from: fromDate.value,
            to: toDate.value,
            search: search.value || null,
            type: selectedType.value === 'all' ? null : selectedType.value,
            category:
                selectedCategory.value === 'all'
                    ? null
                    : selectedCategory.value,
            currency: props.selectedCurrency,
            exclude_investments: excludeInvestments.value ? 1 : null,
            cost_page: costPage && costPage > 1 ? costPage : null,
            income_page: incomePage && incomePage > 1 ? incomePage : null,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function changeCostPage(page: number): void {
    const incomePage = props.transactions.meta.incomes.current_page;

    applyFilters(selectedRange.value, page, incomePage > 1 ? incomePage : null);
}

function changeIncomePage(page: number): void {
    const costPage = props.transactions.meta.costs.current_page;

    applyFilters(selectedRange.value, costPage > 1 ? costPage : null, page);
}

function applyTypeFilter(): void {
    if (
        selectedCategory.value !== 'all' &&
        !reportCategories.value.some(
            (category) => category.id.toString() === selectedCategory.value,
        )
    ) {
        selectedCategory.value = 'all';
    }

    applyFilters();
}

/** Ticking it re-queries straight away — a filter you have to confirm is a trap. */
function applyExcludeInvestments(checked: boolean | 'indeterminate'): void {
    excludeInvestments.value = checked === true;

    applyFilters();
}

function clearTransactionFilters(): void {
    search.value = '';
    selectedType.value = 'all';
    selectedCategory.value = 'all';
    excludeInvestments.value = false;
    applyFilters();
}

function formatMoney(amount: string | number, currency: Currency): string {
    const value = Number(amount);

    return `${new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 2,
        minimumFractionDigits: value % 1 === 0 ? 0 : 2,
    }).format(value)} ${currencyLabel(currency)}`;
}

function currencyLabel(value: Currency): string {
    return (
        props.currencies.find((currency) => currency.value === value)?.label ??
        t(`finance.currencies.${value}`)
    );
}

function displayDate(value: string): string {
    return formatAppDate(value, displayCalendar.value);
}
</script>
