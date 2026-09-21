<template>
    <Head :title="t('finance.reports.title')" />

    <div
        class="finance-dense flex min-h-[calc(100vh-92px)] shrink-0 flex-col overflow-x-hidden bg-[#111111]"
    >
        <!-- Filters -->
        <section
            class="mx-[18px] mt-5 rounded-[16px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
        >
            <div class="flex flex-col gap-4">
                <!-- One dense row: range pills, search, category — matching
                     the mock exactly, no field labels. -->
                <div
                    class="flex flex-col items-stretch gap-2.5 sm:flex-row sm:flex-wrap sm:items-center"
                >
                    <div
                        class="flex w-fit flex-wrap gap-0.5 rounded-[10px] bg-[#252525] p-[3px] ring-1 ring-white/[0.08]"
                    >
                        <button
                            v-for="range in ranges"
                            :key="range.value"
                            type="button"
                            :class="[
                                'rounded-[7px] px-3.5 py-1.5 text-[13px] font-normal transition',
                                selectedRange === range.value
                                    ? 'bg-[#02cd86] text-[#101010]'
                                    : 'text-[#989898] hover:text-white',
                            ]"
                            @click="selectRange(range.value)"
                        >
                            {{ range.label }}
                        </button>
                    </div>

                    <Input
                        id="report_search"
                        v-model="search"
                        :class="[
                            filterFieldClass,
                            'w-full min-w-0 sm:min-w-[180px] sm:flex-1',
                        ]"
                        :placeholder="t('finance.filters.title_or_note')"
                        :aria-label="t('finance.fields.search')"
                        @keyup.enter="applyFilters()"
                    />

                    <Select
                        v-model="selectedCategory"
                        @update:model-value="applyFilters()"
                    >
                        <SelectTrigger
                            id="report_category"
                            :class="[
                                filterFieldClass,
                                '!w-full shrink-0 sm:!w-[240px]',
                            ]"
                            :aria-label="t('finance.fields.category')"
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
                                :class="
                                    category.depth === 1
                                        ? SUBCATEGORY_ITEM_CLASS
                                        : ''
                                "
                            >
                                {{ category.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Hidden entirely when there is no investment category to
                     exclude, so the toggle is never a no-op. -->
                <label
                    v-if="props.hasInvestmentCategory"
                    class="flex w-fit cursor-pointer items-center gap-2.5 border-t border-white/[0.07] pt-4"
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
                            container-class="grid-cols-[1.2fr_1fr_1fr]"
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
                            container-class="grid-cols-[1.2fr_1fr_1fr]"
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
                class="overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div class="mb-2.5 flex items-center gap-2">
                    <span
                        class="size-[7px] shrink-0 rounded-[2px] bg-[#02CD86]"
                    />
                    <p class="text-xs text-[#989898]">
                        {{ t('finance.metrics.income') }} ·
                        {{ currencyLabel(props.selectedCurrency) }}
                    </p>
                </div>
                <p
                    class="text-[25px] leading-none font-semibold text-[#02CD86]"
                >
                    <template v-if="incomeTotal !== null">
                        <CompactMoney
                            :value="incomeTotal"
                            :currency="props.selectedCurrency"
                            mask-variant="placeholder"
                        />
                    </template>
                    <span
                        v-else
                        aria-hidden="true"
                        class="inline-block h-[1em] w-32 animate-pulse rounded bg-white/10 align-middle"
                    />
                </p>
            </article>

            <article
                class="overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <div class="mb-2.5 flex items-center gap-2">
                    <span
                        class="size-[7px] shrink-0 rounded-[2px] bg-[#6C4EE9]"
                    />
                    <p class="text-xs text-[#989898]">
                        {{ costsLabel }} ·
                        {{ currencyLabel(props.selectedCurrency) }}
                    </p>
                </div>
                <p
                    class="text-[25px] leading-none font-semibold text-[#947BFF]"
                >
                    <template v-if="costTotal !== null">
                        <CompactMoney
                            :value="costTotal"
                            :currency="props.selectedCurrency"
                            mask-variant="placeholder"
                        />
                    </template>
                    <span
                        v-else
                        aria-hidden="true"
                        class="inline-block h-[1em] w-32 animate-pulse rounded bg-white/10 align-middle"
                    />
                </p>
            </article>

            <article
                class="overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <p class="mb-2.5 text-xs text-[#989898]">
                    {{ t('finance.metrics.balance') }} ·
                    {{ currencyLabel(props.selectedCurrency) }}
                </p>
                <p class="text-[25px] leading-none font-semibold text-white">
                    <template v-if="balanceTotal !== null">
                        <CompactMoney
                            :value="balanceTotal"
                            :currency="props.selectedCurrency"
                            mask-variant="placeholder"
                        />
                    </template>
                    <span
                        v-else
                        aria-hidden="true"
                        class="inline-block h-[1em] w-32 animate-pulse rounded bg-white/10 align-middle"
                    />
                </p>
            </article>

            <article
                class="overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
            >
                <p class="mb-2.5 text-xs text-[#989898]">
                    {{ t('finance.metrics.entries') }}
                </p>
                <p class="text-[25px] leading-none font-semibold text-white">
                    {{ props.summary.count }}
                </p>
            </article>
        </div>

        <!-- The read: a narrative built from this period's real numbers,
             not a restatement of the KPI cards above it. -->
        <section
            v-if="reportReady"
            class="mx-[18px] mt-[18px] rounded-[16px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
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
                class="overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
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
                class="overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
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
                class="overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
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
                class="overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 pt-5 pb-2.5 ring-1 ring-white/10"
            >
                <div class="mb-4 flex items-center gap-2">
                    <span
                        class="size-[7px] shrink-0 rounded-[2px] bg-[#6C4EE9]"
                    />
                    <p
                        class="text-[11px] font-medium tracking-[0.13em] text-[#947BFF] uppercase"
                    >
                        {{ t('finance.tables.money_going_out') }}
                    </p>
                    <span class="ml-auto text-[11.5px] text-[#686868]">
                        {{ props.transactions.meta.costs.total }}
                        {{ t('finance.metrics.entries') }}
                    </span>
                </div>

                <div
                    class="grid grid-cols-[minmax(0,1fr)_max-content] items-center gap-2.5 pb-2.5 text-[10px] font-medium tracking-[0.1em] text-[#686868] uppercase sm:grid-cols-[minmax(120px,1fr)_112px_92px_118px] sm:gap-3"
                >
                    <div>{{ t('finance.fields.subject') }}</div>
                    <div class="hidden sm:block">
                        {{ t('finance.fields.category') }}
                    </div>
                    <div class="hidden sm:block">
                        {{ t('finance.fields.date') }}
                    </div>
                    <div class="text-end">
                        {{ t('finance.fields.amount') }}
                        <span dir="ltr">({{ selectedCurrencySymbol }})</span>
                    </div>
                </div>

                <div
                    v-for="transaction in props.transactions.costs"
                    :key="transaction.id"
                    class="grid grid-cols-[minmax(0,1fr)_max-content] items-center gap-2.5 border-t border-white/[0.06] py-2.5 transition-colors hover:bg-white/[0.02] sm:grid-cols-[minmax(120px,1fr)_112px_92px_118px] sm:gap-3"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm text-white">
                            <Ciphered
                                :value="transaction.title"
                                table="transactions"
                            />
                        </p>
                        <p
                            v-if="transaction.description"
                            class="mt-0.5 truncate text-[11px] text-[#686868]"
                        >
                            <Ciphered
                                :value="transaction.description"
                                table="transactions"
                            />
                        </p>
                        <CategoryChip
                            class="mt-1 sm:hidden"
                            :name="categoryName(transaction)"
                            :color="categoryColor(transaction)"
                        />
                    </div>
                    <CategoryChip
                        class="hidden sm:inline-flex"
                        :name="categoryName(transaction)"
                        :color="categoryColor(transaction)"
                    />
                    <span
                        dir="ltr"
                        class="hidden text-xs text-[#686868] tabular-nums sm:block"
                    >
                        {{ displayDate(transaction.occurred_at) }}
                    </span>
                    <span
                        class="max-w-[122px] truncate text-end text-[14.5px] text-[#947BFF] tabular-nums sm:max-w-none"
                        :class="maskClass"
                        dir="ltr"
                    >
                        <CipheredMoney
                            :amount="transaction.amount"
                            :display-amount="transaction.display_amount"
                            :currency="transaction.currency"
                            :display-currency="transaction.display_currency"
                            :rates="props.rates"
                        />
                    </span>
                </div>

                <p
                    v-if="props.transactions.costs.length === 0"
                    class="py-10 text-center text-sm text-[#989898]"
                >
                    {{ t('finance.dashboard.no_costs') }}
                </p>

                <div
                    v-if="props.transactions.meta.costs.last_page > 1"
                    class="flex items-center justify-center gap-3 py-4 text-xs text-[#686868]"
                >
                    <button
                        type="button"
                        :aria-label="t('buttons.back')"
                        :disabled="
                            props.transactions.meta.costs.current_page <= 1
                        "
                        class="cursor-pointer rounded-[7px] bg-[#252525] px-[11px] py-[5px] text-white transition-colors hover:bg-[#2e2e2e] disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeCostPage(
                                props.transactions.meta.costs.current_page - 1,
                            )
                        "
                    >
                        ‹
                    </button>
                    <span>
                        {{ props.transactions.meta.costs.current_page }} /
                        {{ props.transactions.meta.costs.last_page }} ·
                        {{ props.transactions.meta.costs.total }}
                        {{ t('finance.metrics.entries') }}
                    </span>
                    <button
                        type="button"
                        :aria-label="t('buttons.next')"
                        :disabled="
                            props.transactions.meta.costs.current_page >=
                            props.transactions.meta.costs.last_page
                        "
                        class="cursor-pointer rounded-[7px] bg-[#252525] px-[11px] py-[5px] text-white transition-colors hover:bg-[#2e2e2e] disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeCostPage(
                                props.transactions.meta.costs.current_page + 1,
                            )
                        "
                    >
                        ›
                    </button>
                </div>
            </section>

            <!-- Incomes table -->
            <section
                class="overflow-hidden rounded-[16px] bg-[#1a1a1a] p-5 pt-5 pb-2.5 ring-1 ring-white/10"
            >
                <div class="mb-4 flex items-center gap-2">
                    <span
                        class="size-[7px] shrink-0 rounded-[2px] bg-[#02CD86]"
                    />
                    <p
                        class="text-[11px] font-medium tracking-[0.13em] text-[#02CD86] uppercase"
                    >
                        {{ t('finance.tables.money_coming_in') }}
                    </p>
                    <span class="ml-auto text-[11.5px] text-[#686868]">
                        {{ props.transactions.meta.incomes.total }}
                        {{ t('finance.metrics.entries') }}
                    </span>
                </div>

                <div
                    class="grid grid-cols-[minmax(0,1fr)_max-content] items-center gap-2.5 pb-2.5 text-[10px] font-medium tracking-[0.1em] text-[#686868] uppercase sm:grid-cols-[minmax(120px,1fr)_112px_92px_118px] sm:gap-3"
                >
                    <div>{{ t('finance.fields.subject') }}</div>
                    <div class="hidden sm:block">
                        {{ t('finance.fields.category') }}
                    </div>
                    <div class="hidden sm:block">
                        {{ t('finance.fields.date') }}
                    </div>
                    <div class="text-end">
                        {{ t('finance.fields.amount') }}
                        <span dir="ltr">({{ selectedCurrencySymbol }})</span>
                    </div>
                </div>

                <div
                    v-for="transaction in props.transactions.incomes"
                    :key="transaction.id"
                    class="grid grid-cols-[minmax(0,1fr)_max-content] items-center gap-2.5 border-t border-white/[0.06] py-2.5 transition-colors hover:bg-white/[0.02] sm:grid-cols-[minmax(120px,1fr)_112px_92px_118px] sm:gap-3"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm text-white">
                            <Ciphered
                                :value="transaction.title"
                                table="transactions"
                            />
                        </p>
                        <p
                            v-if="transaction.description"
                            class="mt-0.5 truncate text-[11px] text-[#686868]"
                        >
                            <Ciphered
                                :value="transaction.description"
                                table="transactions"
                            />
                        </p>
                        <CategoryChip
                            class="mt-1 sm:hidden"
                            :name="categoryName(transaction)"
                            :color="categoryColor(transaction)"
                        />
                    </div>
                    <CategoryChip
                        class="hidden sm:inline-flex"
                        :name="categoryName(transaction)"
                        :color="categoryColor(transaction)"
                    />
                    <span
                        dir="ltr"
                        class="hidden text-xs text-[#686868] tabular-nums sm:block"
                    >
                        {{ displayDate(transaction.occurred_at) }}
                    </span>
                    <span
                        class="max-w-[122px] truncate text-end text-[14.5px] text-[#02CD86] tabular-nums sm:max-w-none"
                        :class="maskClass"
                        dir="ltr"
                    >
                        <CipheredMoney
                            :amount="transaction.amount"
                            :display-amount="transaction.display_amount"
                            :currency="transaction.currency"
                            :display-currency="transaction.display_currency"
                            :rates="props.rates"
                        />
                    </span>
                </div>

                <p
                    v-if="props.transactions.incomes.length === 0"
                    class="py-10 text-center text-sm text-[#989898]"
                >
                    {{ t('finance.dashboard.no_incomes') }}
                </p>

                <div
                    v-if="props.transactions.meta.incomes.last_page > 1"
                    class="flex items-center justify-center gap-3 py-4 text-xs text-[#686868]"
                >
                    <button
                        type="button"
                        :aria-label="t('buttons.back')"
                        :disabled="
                            props.transactions.meta.incomes.current_page <= 1
                        "
                        class="cursor-pointer rounded-[7px] bg-[#252525] px-[11px] py-[5px] text-white transition-colors hover:bg-[#2e2e2e] disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeIncomePage(
                                props.transactions.meta.incomes.current_page -
                                    1,
                            )
                        "
                    >
                        ‹
                    </button>
                    <span>
                        {{ props.transactions.meta.incomes.current_page }} /
                        {{ props.transactions.meta.incomes.last_page }} ·
                        {{ props.transactions.meta.incomes.total }}
                        {{ t('finance.metrics.entries') }}
                    </span>
                    <button
                        type="button"
                        :aria-label="t('buttons.next')"
                        :disabled="
                            props.transactions.meta.incomes.current_page >=
                            props.transactions.meta.incomes.last_page
                        "
                        class="cursor-pointer rounded-[7px] bg-[#252525] px-[11px] py-[5px] text-white transition-colors hover:bg-[#2e2e2e] disabled:cursor-not-allowed disabled:opacity-40"
                        @click="
                            changeIncomePage(
                                props.transactions.meta.incomes.current_page +
                                    1,
                            )
                        "
                    >
                        ›
                    </button>
                </div>
            </section>
        </div>

        <!-- Export belongs with the completed report tables, not among the
             filters that define the report. -->
        <div
            class="mx-[18px] mb-[38px] flex flex-wrap items-center justify-between gap-4 rounded-[16px] bg-[#1a1a1a] px-[22px] py-[18px] ring-1 ring-white/10"
        >
            <p class="text-[13.5px] text-[#989898]">
                {{ t('finance.reports.export_hint') }}
            </p>
            <a
                href="/transactions/export"
                class="cursor-pointer rounded-[10px] bg-[#252525] px-[15px] py-2 text-[13.5px] text-white ring-1 ring-white/[0.14] transition-colors hover:bg-[#2e2e2e]"
            >
                {{ t('finance.actions.export_transactions') }}
            </a>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BirthdatePicker from '@/components/BirthdatePicker.vue';
import LineChart from '@/components/charts/LineChart.vue';
import PulseChart from '@/components/charts/PulseChart.vue';
import RankedBarChart from '@/components/charts/RankedBarChart.vue';
import Ciphered from '@/components/Ciphered.vue';
import CipheredMoney from '@/components/CipheredMoney.vue';
import CompactMoney from '@/components/CompactMoney.vue';
import CategoryChip from '@/components/transactions/CategoryChip.vue';
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
import { orderByParent, SUBCATEGORY_ITEM_CLASS } from '@/lib/categories';
import {
    dayBucketsBetween,
    formatAppDate,
    formatChartDateLabel,
    monthBucketKeyFromIso,
    monthBucketsBetween,
} from '@/lib/date';
import { currencySymbol, formatCurrencyDisplay } from '@/lib/money';
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
    for_both_types: boolean;
    parent_id: number | null;
    color: string | null;
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
    'h-9 w-full rounded-[10px] !border-white/[0.08] !bg-[#252525] px-3 text-[13px] font-normal !text-[#e5e5e5] shadow-none [color-scheme:dark] placeholder:!text-[#686868] focus-visible:!border-[#947BFF] focus-visible:!ring-2 focus-visible:!ring-[#947BFF]/25 [&_svg]:!text-[#989898]';

const selectedRange = ref<ReportRange>(props.filters.range);
const fromDate = ref(props.filters.from);
const toDate = ref(props.filters.to);
const search = ref(props.filters.search);
const selectedCategory = ref(props.filters.category?.toString() ?? 'all');
const excludeInvestments = ref(props.filters.exclude_investments);
const selectedCurrencySymbol = computed(() =>
    currencySymbol(props.selectedCurrency),
);

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

const balanceTotal = computed(() => {
    if (incomeTotal.value === null || costTotal.value === null) {
        return null;
    }

    return incomeTotal.value - costTotal.value;
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

// Merged from both types' lists, where a shared category appears twice —
// orderByParent drops the repeat as well as grouping subcategories.
const reportCategories = computed(() =>
    orderByParent([...props.categories.cost, ...props.categories.income]),
);

watch(
    () => props.filters,
    (filters) => {
        selectedRange.value = filters.range;
        fromDate.value = filters.from;
        toDate.value = filters.to;
        search.value = filters.search;
        selectedCategory.value = filters.category?.toString() ?? 'all';
        excludeInvestments.value = filters.exclude_investments;
    },
    { deep: true },
);

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

/** Ticking it re-queries straight away — a filter you have to confirm is a trap. */
function applyExcludeInvestments(checked: boolean | 'indeterminate'): void {
    excludeInvestments.value = checked === true;

    applyFilters();
}

function formatMoney(amount: string | number, currency: Currency): string {
    return formatCurrencyDisplay(amount, currency);
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

// The mock's per-row category tag colours text on a fixed dark chip using
// each category's own colour — never a generic badge. Falls back to the
// prop lookup for rows whose relation wasn't eager-loaded.
function findCategory(transaction: Transaction): Category | null {
    if (transaction.category) {
        return transaction.category;
    }

    return (
        (props.categories[transaction.type] ?? []).find(
            (category) => category.id === transaction.category_id,
        ) ?? null
    );
}

function categoryName(transaction: Transaction): string {
    return (
        findCategory(transaction)?.name ?? t('finance.categories.uncategorized')
    );
}

function categoryColor(transaction: Transaction): string | null {
    return findCategory(transaction)?.color ?? null;
}
</script>
