<template>
    <Head :title="t('finance.investments.title')" />

    <div
        class="flex h-full min-h-[calc(100vh-92px)] flex-1 flex-col overflow-x-auto bg-[#111111]"
    >
        <!-- ── Summary stat cards ────────────────────────────────── -->
        <div class="grid gap-[18px] px-[18px] pt-[18px] md:grid-cols-3">
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
                'chartData',
                'summary',
                'prices',
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

            <!-- ── Charts row ────────────────────────────────────────── -->
            <div
                v-if="(props.assets ?? []).length > 0"
                class="grid items-start gap-[18px] px-[18px] py-[18px] xl:grid-cols-[380px_1fr]"
            >
                <!-- Donut / allocation chart -->
                <section
                    class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h2
                            class="text-[18px] leading-none font-normal text-white"
                        >
                            {{ t('finance.investments.allocation') }}
                        </h2>
                        <span class="text-xs text-[#989898]">{{
                            t('finance.investments.by_current_value')
                        }}</span>
                    </div>
                    <DonutChart
                        :series="donutSeries"
                        :labels="donutLabels"
                        :colors="donutColors"
                        :center-label="t('finance.investments.portfolio')"
                        :center-value="
                            props.pricesAvailable
                                ? props.summary?.total_value_formatted + ' T'
                                : t('finance.price_unavailable')
                        "
                        @slice-click="onSliceClick"
                    />
                </section>

                <!-- Line chart — value over time -->
                <section
                    class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                >
                    <div
                        class="mb-4 flex flex-wrap items-center justify-between gap-3"
                    >
                        <h2
                            class="text-[18px] leading-none font-normal text-white"
                        >
                            {{ t('finance.investments.value_over_time') }}
                        </h2>
                        <div class="flex flex-wrap items-center gap-3">
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
                            <!-- Range buttons -->
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="rangeOption in ranges"
                                    :key="rangeOption.value"
                                    type="button"
                                    :class="[
                                        'cursor-pointer rounded-full px-3 py-1 text-xs font-medium transition',
                                        selectedRange === rangeOption.value
                                            ? 'bg-white/15 text-white'
                                            : 'text-[#686868] ring-1 ring-white/10 hover:bg-white/10 hover:text-white',
                                    ]"
                                    @click="changeRange(rangeOption.value)"
                                >
                                    {{ rangeOption.label }}
                                </button>
                            </div>
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
                        :height="280"
                    />
                </section>
            </div>

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
                class="grid grid-cols-2 gap-[18px] px-[18px] sm:grid-cols-3 xl:grid-cols-6"
            >
                <div
                    v-for="asset in props.assets ?? []"
                    :key="asset.key"
                    class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-4 shadow-[0_18px_45px_rgba(0,0,0,0.2)] ring-1 ring-white/10"
                    :style="{ borderTop: `2.5px solid ${asset.color}` }"
                >
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-xl leading-none">{{
                            asset.icon
                        }}</span>
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
            <div class="flex items-center justify-between gap-4 px-5 py-[29px]">
                <h2 class="text-[22px] leading-none font-normal text-white">
                    {{ t('finance.investments.investment_entries') }}
                </h2>
                <div class="flex items-center gap-2">
                    <a
                        :href="`/investments/export?currency=${props.selectedCurrency}`"
                        class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm text-white ring-1 ring-white/20 transition-colors hover:bg-white/15"
                    >
                        <Download class="size-4" />
                        Export
                    </a>
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
                                <span class="mr-1">{{ entry.asset_icon }}</span>
                                {{ entry.asset_label }}
                            </td>
                            <td
                                class="px-3 py-[14px] text-center text-[16px] leading-none font-normal text-white sm:px-5"
                            >
                                {{ entry.quantity }} {{ entry.asset_unit }}
                            </td>
                            <td
                                class="px-3 py-[14px] text-center text-[16px] leading-none font-normal text-white sm:px-5"
                            >
                                <template v-if="props.pricesAvailable">
                                    {{
                                        formatEntryValue(
                                            entry.quantity,
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
                                <div
                                    class="flex items-center justify-center gap-2 opacity-0 transition group-hover:opacity-100"
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

        <!-- ── Add / Edit dialog ─────────────────────────────────── -->
        <Dialog v-model:open="isDialogOpen">
            <DialogContent
                class="max-h-[calc(100dvh-1rem)] overflow-hidden rounded-[20px] border-0 bg-[#1a1a1a] p-0 text-white shadow-2xl ring-1 ring-white/10 sm:max-h-[calc(100vh-2rem)] sm:min-h-[560px] sm:max-w-[560px] sm:rounded-[25px]"
                :show-close-button="false"
            >
                <form
                    class="flex max-h-[calc(100dvh-1rem)] flex-col sm:max-h-[calc(100vh-2rem)]"
                    @submit.prevent="submitEntry"
                >
                    <div
                        class="flex-1 overflow-y-auto px-4 pt-10 pb-5 sm:px-[80px] sm:pt-[68px] sm:pb-6"
                    >
                        <DialogHeader class="mb-6 space-y-2 text-left">
                            <DialogTitle
                                class="text-[20px] leading-normal font-medium text-white"
                            >
                                {{
                                    editingId !== null
                                        ? t('finance.form.edit_investment')
                                        : t('finance.form.add_investment')
                                }}
                            </DialogTitle>
                            <DialogDescription
                                class="text-[15px] leading-[18px] font-light text-[#989898]"
                            >
                                {{ t('finance.form.investment_description') }}
                            </DialogDescription>
                        </DialogHeader>

                        <div class="space-y-5">
                            <!-- Asset type + Quantity -->
                            <div class="grid gap-4 sm:grid-cols-[1fr_140px]">
                                <div class="grid gap-2">
                                    <Label
                                        class="finance-dialog-label"
                                        for="asset_type"
                                    >
                                        {{ t('finance.fields.asset') }}
                                    </Label>
                                    <select
                                        id="asset_type"
                                        v-model="form.asset_type"
                                        required
                                        class="finance-dialog-field"
                                        :class="fieldClass"
                                    >
                                        <option value="" disabled>
                                            {{
                                                t(
                                                    'finance.filters.select_asset',
                                                )
                                            }}
                                        </option>
                                        <option
                                            v-for="assetType in props.assetTypes"
                                            :key="assetType.value"
                                            :value="assetType.value"
                                        >
                                            {{ assetType.icon }}
                                            {{ assetType.label }} ({{
                                                assetType.unit
                                            }})
                                        </option>
                                    </select>
                                    <InputError
                                        :message="form.errors.asset_type"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label
                                        class="finance-dialog-label"
                                        for="quantity"
                                    >
                                        {{ t('finance.fields.quantity') }}
                                    </Label>
                                    <Input
                                        id="quantity"
                                        v-model="form.quantity"
                                        :class="fieldClass"
                                        required
                                        type="number"
                                        min="0.00000001"
                                        step="any"
                                        placeholder="0.00"
                                    />
                                    <InputError
                                        :message="form.errors.quantity"
                                    />
                                </div>
                            </div>

                            <!-- Cost basis + Currency -->
                            <div class="grid gap-4 sm:grid-cols-[1fr_140px]">
                                <div class="grid gap-2">
                                    <Label
                                        class="finance-dialog-label"
                                        for="cost_basis"
                                    >
                                        {{
                                            t(
                                                'finance.fields.cost_basis_per_unit',
                                            )
                                        }}
                                        <span class="font-light text-[#989898]"
                                            >({{
                                                t('finance.fields.optional')
                                            }})</span
                                        >
                                    </Label>
                                    <Input
                                        id="cost_basis"
                                        v-model="form.cost_basis"
                                        :class="fieldClass"
                                        type="number"
                                        min="0"
                                        step="any"
                                        placeholder="0.00"
                                    />
                                    <InputError
                                        :message="form.errors.cost_basis"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label
                                        class="finance-dialog-label"
                                        for="cost_basis_currency"
                                    >
                                        {{ t('finance.fields.currency') }}
                                    </Label>
                                    <select
                                        id="cost_basis_currency"
                                        v-model="form.cost_basis_currency"
                                        class="finance-dialog-field"
                                        :class="fieldClass"
                                    >
                                        <option
                                            v-for="currency in props.currencies"
                                            :key="currency.value"
                                            :value="currency.value"
                                        >
                                            {{ currency.label }}
                                        </option>
                                    </select>
                                    <InputError
                                        :message="
                                            form.errors.cost_basis_currency
                                        "
                                    />
                                </div>
                            </div>

                            <!-- Date -->
                            <div class="grid gap-2">
                                <Label class="finance-dialog-label">{{
                                    t('finance.fields.date')
                                }}</Label>
                                <BirthdatePicker
                                    v-model="form.occurred_at"
                                    name="occurred_at"
                                    :trigger-class="fieldClass"
                                    :years-back="16"
                                    :years-forward="1"
                                />
                                <InputError
                                    :message="form.errors.occurred_at"
                                />
                            </div>

                            <!-- Note -->
                            <div class="grid gap-2">
                                <Label class="finance-dialog-label" for="note">
                                    {{ t('finance.fields.note') }}
                                    <span class="font-light text-[#989898]"
                                        >({{
                                            t('finance.fields.optional')
                                        }})</span
                                    >
                                </Label>
                                <textarea
                                    id="note"
                                    v-model="form.note"
                                    rows="2"
                                    class="finance-dialog-field min-h-9 resize-none"
                                    :class="fieldClass"
                                    :placeholder="
                                        t(
                                            'finance.form.investment_note_placeholder',
                                        )
                                    "
                                />
                                <InputError :message="form.errors.note" />
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex shrink-0 justify-end gap-2 border-t border-white/10 bg-[#1a1a1a]/95 px-4 py-4 backdrop-blur sm:border-t-0 sm:bg-transparent sm:px-[80px] sm:pt-1 sm:pb-10"
                    >
                        <Button
                            type="button"
                            class="h-11 flex-1 rounded-[8px] bg-white/5 px-[10px] text-base font-normal text-[#989898] shadow-none ring-1 ring-white/10 hover:bg-white/10 hover:text-white sm:h-9 sm:w-[90px] sm:flex-none sm:text-[16px]"
                            @click="isDialogOpen = false"
                        >
                            {{ t('common.cancel') }}
                        </Button>
                        <Button
                            type="submit"
                            class="h-11 flex-1 rounded-[8px] bg-[#02CD86] px-[10px] text-base font-semibold text-[#101010] shadow-none hover:bg-[#08dd93] sm:h-9 sm:w-[120px] sm:flex-none sm:text-[16px]"
                            :disabled="form.processing"
                        >
                            <Spinner v-if="form.processing" />
                            {{ t('common.confirm') }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

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
import { Deferred, Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Download, Plus, Trash2, TrendingUp } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BirthdatePicker from '@/components/BirthdatePicker.vue';
import DonutChart from '@/components/charts/DonutChart.vue';
import LineChart from '@/components/charts/LineChart.vue';
import type { ChartSeries } from '@/components/charts/LineChart.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatAppDate } from '@/lib/date';
import { dashboard } from '@/routes';
import { index as investmentsIndex } from '@/routes/investments';

type AssetKey = 'gold' | 'silver' | 'usd' | 'eur' | 'coin' | 'bitcoin';

type AssetSummary = {
    key: AssetKey;
    label: string;
    icon: string;
    color: string;
    unit: string;
    quantity: number;
    quantity_display: string;
    price: number;
    price_formatted: string;
    value: number;
    value_formatted: string;
    allocation: number;
};

type AssetTypeOption = {
    value: AssetKey;
    label: string;
    unit: string;
    icon: string;
    color: string;
};

type Entry = {
    id: number;
    asset_type: AssetKey;
    asset_label: string;
    asset_icon: string;
    asset_color: string;
    asset_unit: string;
    quantity: number;
    cost_basis: number | null;
    cost_basis_currency: string | null;
    note: string | null;
    occurred_at: string;
};

type ChartData = {
    categories: string[];
    series: ChartSeries[];
};

type CurrencyOption = {
    label: string;
    value: string;
};

const props = defineProps<{
    assets?: AssetSummary[];
    summary?: {
        total_value: number;
        total_value_formatted: string;
        asset_count: number;
        entry_count: number;
    };
    chartData?: ChartData;
    assetTypes: AssetTypeOption[];
    entries: Entry[];
    selectedRange: string;
    entryCount: number;
    assetTypeCount: number;
    prices?: Record<AssetKey, number>;
    currencies: CurrencyOption[];
    selectedCurrency: string;
    pricesAvailable?: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Investments', href: investmentsIndex() },
        ],
    },
});

const ranges = [
    { label: '1W', value: '1w' },
    { label: '1M', value: '1m' },
    { label: '3M', value: '3m' },
    { label: '1Y', value: '1y' },
    { label: 'All', value: 'all' },
];

const selectedRange = ref(props.selectedRange);
const selectedCurrency = ref(props.selectedCurrency);
const page = usePage();
const { t } = useI18n();
const displayCalendar = computed(
    () => (page.props.calendar as string | undefined) ?? 'gregorian',
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

const pricesResolved = computed(() => props.pricesAvailable !== undefined);

const donutSeries = computed(() =>
    (props.assets ?? []).map((asset) => asset.allocation),
);
const donutLabels = computed(() =>
    (props.assets ?? []).map((asset) => asset.label),
);
const donutColors = computed(() =>
    (props.assets ?? []).map((asset) => asset.color),
);

function toggleSeries(key: string) {
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

function onSliceClick(sliceIndex: number | null) {
    if (sliceIndex === null) {
        activeSeries.value = new Set(
            availableSeries.value.map((seriesItem) => seriesItem.key),
        );
    } else {
        const asset = (props.assets ?? [])[sliceIndex];

        if (!asset) {
            return;
        }

        if (
            activeSeries.value.size === 1 &&
            activeSeries.value.has(asset.key)
        ) {
            activeSeries.value = new Set(
                availableSeries.value.map((seriesItem) => seriesItem.key),
            );
        } else {
            activeSeries.value = new Set([asset.key]);
        }
    }
}

function changeRange(range: string) {
    selectedRange.value = range;
    router.get(
        investmentsIndex.url({
            query: { range, currency: selectedCurrency.value },
        }),
        {},
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

function changeCurrency(currency: string) {
    if (currency === selectedCurrency.value) {
        return;
    }

    selectedCurrency.value = currency;
    router.get(
        investmentsIndex.url({
            query: { range: selectedRange.value, currency },
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
    () => props.selectedCurrency,
    (value) => {
        selectedCurrency.value = value;
    },
);

watch(
    () => props.selectedRange,
    (value) => {
        selectedRange.value = value;
    },
);

const isDialogOpen = ref(false);
const editingId = ref<number | null>(null);

const today = () => new Date().toISOString().slice(0, 10);

const form = useForm({
    asset_type: '',
    quantity: '',
    note: '',
    occurred_at: today(),
    cost_basis: '',
    cost_basis_currency: '',
});

const fieldClass =
    'finance-dialog-field finance-dialog-field-income focus-visible:ring-[#02CD86]/25';

function openCreateDialog(defaultType?: AssetKey) {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.asset_type = defaultType ?? props.assetTypes[0]?.value ?? '';
    form.occurred_at = today();
    form.cost_basis_currency =
        props.selectedCurrency || props.currencies[0]?.value || '';
    isDialogOpen.value = true;
}

function openEditDialog(entry: Entry) {
    editingId.value = entry.id;
    form.clearErrors();
    form.asset_type = entry.asset_type;
    form.quantity = String(entry.quantity);
    form.note = entry.note ?? '';
    form.occurred_at = entry.occurred_at;
    form.cost_basis = entry.cost_basis !== null ? String(entry.cost_basis) : '';
    form.cost_basis_currency =
        entry.cost_basis_currency ??
        (props.selectedCurrency || props.currencies[0]?.value || '');
    isDialogOpen.value = true;
}

function submitEntry() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            isDialogOpen.value = false;
            editingId.value = null;
        },
    };

    if (editingId.value !== null) {
        form.patch(`/investments/${editingId.value}`, opts);
    } else {
        form.post('/investments', opts);
    }
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
