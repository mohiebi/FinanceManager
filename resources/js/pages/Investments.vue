<template>
    <Head title="Investments" />

    <div
        class="flex h-full min-h-[calc(100vh-92px)] flex-1 flex-col overflow-x-auto bg-[#111111]"
    >
        <!-- ── Summary stat cards ────────────────────────────────── -->
        <div class="grid gap-[18px] px-[18px] pt-[18px] md:grid-cols-3">
            <article
                class="kpi-card-income overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 ring-1 ring-white/10 shadow-[0_18px_45px_rgba(0,0,0,0.2)]"
            >
                <p
                    class="text-xs font-medium tracking-[0.2em] text-[#02CD86] uppercase"
                >
                    Total Portfolio Value
                </p>
                <p class="mt-3 text-2xl font-bold text-white">
                    {{ props.summary.total_value_formatted }}
                    <span class="text-sm font-normal text-[#989898]">T</span>
                </p>
            </article>

            <article
                class="kpi-card-cost overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 ring-1 ring-white/10 shadow-[0_18px_45px_rgba(0,0,0,0.2)]"
            >
                <p
                    class="text-xs font-medium tracking-[0.2em] text-[#6C4EE9] uppercase"
                >
                    Asset Types
                </p>
                <p class="mt-3 text-2xl font-bold text-white">
                    {{ props.summary.asset_count }}
                    <span class="text-sm font-normal text-[#989898]">held</span>
                </p>
            </article>

            <article
                class="kpi-card-neutral overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 ring-1 ring-white/10 shadow-[0_18px_45px_rgba(0,0,0,0.2)]"
            >
                <p
                    class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                >
                    Total Entries
                </p>
                <p class="mt-3 text-2xl font-bold text-white">
                    {{ props.summary.entry_count }}
                    <span class="text-sm font-normal text-[#989898]"
                        >records</span
                    >
                </p>
            </article>
        </div>

        <!-- ── Charts row ────────────────────────────────────────── -->
        <div
            v-if="props.assets.length > 0"
            class="grid items-start gap-[18px] px-[18px] py-[18px] xl:grid-cols-[380px_1fr]"
        >
            <!-- Donut / allocation chart -->
            <section
                class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 ring-1 ring-white/10 shadow-[0_18px_45px_rgba(0,0,0,0.2)]"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-[18px] leading-none font-normal text-white">
                        Allocation
                    </h2>
                    <span class="text-xs text-[#989898]">by current value</span>
                </div>
                <DonutChart
                    :series="donutSeries"
                    :labels="donutLabels"
                    :colors="donutColors"
                    center-label="Portfolio"
                    :center-value="props.summary.total_value_formatted + ' T'"
                    @slice-click="onSliceClick"
                />
            </section>

            <!-- Line chart — value over time -->
            <section
                class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-5 ring-1 ring-white/10 shadow-[0_18px_45px_rgba(0,0,0,0.2)]"
            >
                <div
                    class="mb-4 flex flex-wrap items-center justify-between gap-3"
                >
                    <h2 class="text-[18px] leading-none font-normal text-white">
                        Value over time
                    </h2>
                    <!-- Range buttons -->
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="rangeOption in ranges"
                            :key="rangeOption.value"
                            type="button"
                            :class="[
                                'rounded-full px-3 py-1 text-xs font-medium transition',
                                selectedRange === rangeOption.value
                                    ? 'bg-[#111111] text-white'
                                    : 'bg-white text-[#2d2d2d] ring-1 ring-[#e6e6e6] hover:bg-[#f7f7f7]',
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
                                : 'bg-[#f4f4f4] text-[#989898] ring-1 ring-[#e6e6e6]',
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
                    :categories="props.chartData.categories"
                    :height="280"
                />
            </section>
        </div>

        <!-- ── Empty state when no entries yet ──────────────────── -->
        <div
            v-if="props.assets.length === 0"
            class="mx-[18px] my-[18px] flex flex-col items-center justify-center rounded-[22px] bg-[#1a1a1a] px-8 py-20 ring-1 ring-white/10"
        >
            <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#f0ecff]">
                <TrendingUp class="size-8 text-[#6C4EE9]" />
            </span>
            <h2 class="mt-4 text-xl font-semibold text-white">
                No investments yet
            </h2>
            <p class="mt-2 max-w-sm text-center text-sm text-[#989898]">
                Start tracking your assets — gold, silver, USD, crypto, and
                more. Add your first entry to see your portfolio come to life.
            </p>
            <Button
                class="mt-6 h-11 rounded-full bg-[linear-gradient(90deg,#947BFF_0%,#6C4EE9_100%)] px-6 text-white shadow-[0_8px_20px_rgba(108,78,233,0.25)] hover:brightness-105"
                @click="openCreateDialog()"
            >
                <Plus class="size-4" />
                Add first entry
            </Button>
        </div>

        <!-- ── Asset summary cards ───────────────────────────────── -->
        <div
            v-if="props.assets.length > 0"
            class="grid grid-cols-2 gap-[18px] px-[18px] sm:grid-cols-3 xl:grid-cols-6"
        >
            <div
                v-for="asset in props.assets"
                :key="asset.key"
                class="overflow-hidden rounded-[22px] bg-[#1a1a1a] p-4 ring-1 ring-white/10 shadow-[0_18px_45px_rgba(0,0,0,0.2)]"
                :style="{ borderTop: `2.5px solid ${asset.color}` }"
            >
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-xl leading-none">{{ asset.icon }}</span>
                    <span
                        class="rounded-md px-2 py-0.5 text-xs font-semibold text-white"
                        :style="{ backgroundColor: asset.color }"
                    >
                        {{ asset.allocation }}%
                    </span>
                </div>
                <p class="text-sm font-semibold text-[#2d2d2d]">
                    {{ asset.label }}
                </p>
                <p class="mt-0.5 text-xs text-[#989898]">
                    {{ asset.quantity_display }} {{ asset.unit }}
                </p>
                <p class="mt-2 text-sm font-bold text-[#2d2d2d]">
                    {{ asset.value_formatted }}
                    <span class="text-xs font-normal text-[#989898]">T</span>
                </p>
            </div>
        </div>

        <!-- ── Recent entries table ──────────────────────────────── -->
        <div
            v-if="props.entries.length > 0"
            class="mx-[18px] mt-[18px] mb-[38px] overflow-hidden rounded-[22px] bg-[#1a1a1a] ring-1 ring-white/10 shadow-[0_18px_45px_rgba(0,0,0,0.2)]"
        >
            <div class="flex items-center justify-between gap-4 px-5 py-[29px]">
                <h2 class="text-[22px] leading-none font-normal text-white">
                    Investment entries
                </h2>
                <Button
                    class="h-12 w-max justify-between rounded-md bg-[linear-gradient(90deg,#947BFF_0%,#6C4EE9_100%)] px-3.5 text-lg font-bold text-white shadow-[0_10px_20px_rgba(108,78,233,0.22)] hover:brightness-105"
                    @click="openCreateDialog()"
                >
                    <span>Add Entry</span>
                    <span
                        class="ml-2 grid h-[1.55em] w-[1.55em] shrink-0 place-items-center rounded-md border border-white/25 bg-[linear-gradient(135deg,rgba(255,255,255,0.24)_0%,rgba(45,45,45,0.72)_100%)] shadow-[inset_0_1px_0_rgba(255,255,255,0.22)]"
                    >
                        <Plus class="size-4" />
                    </span>
                </Button>
            </div>

            <div class="overflow-x-auto px-3 pb-5">
                <table
                    class="w-full border-separate border-spacing-y-0 text-sm"
                >
                    <thead>
                        <tr class="text-base">
                            <th
                                class="rounded-l-2xl bg-[#24212f] px-3 py-4 text-center font-normal text-[#c4b2ff] sm:px-5"
                            >
                                Asset
                            </th>
                            <th
                                class="bg-[#24212f] px-3 py-4 text-center font-normal text-[#c4b2ff] sm:px-5"
                            >
                                Quantity
                            </th>
                            <th
                                class="bg-[#24212f] px-3 py-4 text-center font-normal text-[#c4b2ff] sm:px-5"
                            >
                                Value
                            </th>
                            <th
                                class="hidden rounded-r-2xl bg-[#24212f] px-3 py-4 text-center font-normal text-[#c4b2ff] sm:table-cell sm:px-5"
                            >
                                Date
                            </th>
                            <th
                                class="rounded-r-2xl bg-[#24212f] px-3 py-4 text-center font-normal text-[#c4b2ff] sm:rounded-none sm:px-5"
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
                                {{
                                    formatEntryValue(
                                        entry.quantity,
                                        entry.asset_type,
                                    )
                                }}
                                <span class="text-xs text-[#989898]">T</span>
                            </td>
                            <td
                                class="hidden px-3 py-[14px] text-center text-[16px] leading-none font-normal text-black sm:table-cell sm:px-5"
                            >
                                {{ entry.occurred_at }}
                            </td>
                            <td class="px-3 py-[14px] text-center sm:px-5">
                                <div
                                    class="flex items-center justify-center gap-2 opacity-0 transition group-hover:opacity-100"
                                >
                                    <button
                                        type="button"
                                        class="rounded-md bg-[#f0ecff] px-2 py-1 text-xs text-[#6C4EE9] hover:bg-[#e4dfff]"
                                        @click="openEditDialog(entry)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md bg-[#fff0f0] px-2 py-1 text-xs text-[#E94E50] hover:bg-[#ffe0e0]"
                                        @click="deleteEntry(entry.id)"
                                    >
                                        Delete
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
                class="max-h-[calc(100vh-2rem)] overflow-y-auto rounded-[25px] border-0 bg-white p-0 text-[#2d2d2d] shadow-2xl sm:min-h-[560px] sm:max-w-[560px]"
                :show-close-button="false"
            >
                <form
                    class="px-6 pt-14 pb-10 sm:px-[80px] sm:pt-[68px]"
                    @submit.prevent="submitEntry"
                >
                    <DialogHeader class="mb-6 space-y-2 text-left">
                        <DialogTitle
                            class="text-[20px] leading-normal font-medium text-[#2d2d2d]"
                        >
                            {{
                                editingId !== null
                                    ? 'Edit entry'
                                    : 'Add investment entry'
                            }}
                        </DialogTitle>
                        <DialogDescription
                            class="text-[15px] leading-[18px] font-light text-[#989898]"
                        >
                            Record how much of an asset you held on a given
                            date.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-5">
                        <!-- Asset type + Quantity -->
                        <div class="grid gap-2 sm:grid-cols-[1fr_140px]">
                            <div class="grid gap-2">
                                <Label
                                    class="finance-dialog-label"
                                    for="asset_type"
                                >
                                    Asset
                                </Label>
                                <select
                                    id="asset_type"
                                    v-model="form.asset_type"
                                    required
                                    class="finance-dialog-field"
                                    :class="fieldClass"
                                >
                                    <option value="" disabled>
                                        Select asset
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
                                <InputError :message="form.errors.asset_type" />
                            </div>

                            <div class="grid gap-2">
                                <Label
                                    class="finance-dialog-label"
                                    for="quantity"
                                >
                                    Quantity
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
                                <InputError :message="form.errors.quantity" />
                            </div>
                        </div>

                        <!-- Date -->
                        <div class="grid gap-2">
                            <Label class="finance-dialog-label">Date</Label>
                            <div class="grid gap-2 sm:grid-cols-[1fr_1fr_1fr]">
                                <Select v-model="selectedMonth" required>
                                    <SelectTrigger
                                        class="finance-dialog-field"
                                        :class="fieldClass"
                                    >
                                        <SelectValue placeholder="Month" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="monthOption in months"
                                            :key="monthOption.value"
                                            :value="monthOption.value"
                                        >
                                            {{ monthOption.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Select v-model="selectedDay" required>
                                    <SelectTrigger
                                        class="finance-dialog-field"
                                        :class="fieldClass"
                                    >
                                        <SelectValue placeholder="Day" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="dayOption in daysInMonth"
                                            :key="dayOption"
                                            :value="dayOption"
                                        >
                                            {{ Number(dayOption) }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Select v-model="selectedYear" required>
                                    <SelectTrigger
                                        class="finance-dialog-field"
                                        :class="fieldClass"
                                    >
                                        <SelectValue placeholder="Year" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="yearOption in years"
                                            :key="yearOption"
                                            :value="yearOption"
                                        >
                                            {{ yearOption }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <InputError :message="form.errors.occurred_at" />
                        </div>

                        <!-- Note -->
                        <div class="grid gap-2">
                            <Label class="finance-dialog-label" for="note">
                                Note
                                <span class="font-light text-[#989898]"
                                    >(optional)</span
                                >
                            </Label>
                            <textarea
                                id="note"
                                v-model="form.note"
                                rows="2"
                                class="finance-dialog-field min-h-9 resize-none"
                                :class="fieldClass"
                                placeholder="e.g. bought at Tejarat bank"
                            />
                            <InputError :message="form.errors.note" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <Button
                            type="button"
                            class="h-9 w-[90px] rounded-[8px] bg-[#f4f4f4] px-[10px] text-[16px] font-normal text-[#2d2d2d] shadow-none hover:bg-[#ebebeb]"
                            @click="isDialogOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            class="h-9 w-[120px] rounded-[8px] bg-[#111111] px-[10px] text-[16px] font-normal text-white shadow-none hover:bg-[#1f1f1f]"
                            :disabled="form.processing"
                        >
                            <Spinner v-if="form.processing" />
                            Confirm
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Floating add button when there are already entries -->
        <div v-if="props.assets.length > 0" class="fixed right-6 bottom-6 z-10">
            <Button
                class="h-14 w-14 rounded-full bg-[linear-gradient(135deg,#947BFF_0%,#6C4EE9_100%)] p-0 text-white shadow-[0_8px_24px_rgba(108,78,233,0.35)] hover:brightness-105"
                @click="openCreateDialog()"
            >
                <Plus class="size-6" />
                <span class="sr-only">Add investment entry</span>
            </Button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, TrendingUp } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import DonutChart from '@/components/charts/DonutChart.vue';
import LineChart from '@/components/charts/LineChart.vue';
import type { ChartSeries } from '@/components/charts/LineChart.vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
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

const props = defineProps<{
    assets: AssetSummary[];
    summary: {
        total_value: number;
        total_value_formatted: string;
        asset_count: number;
        entry_count: number;
    };
    chartData: ChartData;
    assetTypes: AssetTypeOption[];
    entries: Entry[];
    selectedRange: string;
    prices: Record<AssetKey, number>;
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

const availableSeries = computed<ChartSeries[]>(
    () => props.chartData.series ?? [],
);
const activeSeries = ref<Set<string>>(
    new Set(availableSeries.value.map((seriesItem) => seriesItem.key)),
);

const filteredChartSeries = computed<ChartSeries[]>(() =>
    availableSeries.value.filter((seriesItem) =>
        activeSeries.value.has(seriesItem.key),
    ),
);

const donutSeries = computed(() =>
    props.assets.map((asset) => asset.allocation),
);
const donutLabels = computed(() => props.assets.map((asset) => asset.label));
const donutColors = computed(() => props.assets.map((asset) => asset.color));

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
        const asset = props.assets[sliceIndex];

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
        investmentsIndex.url({ query: { range } }),
        {},
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

watch(
    () => props.chartData.series,
    () => {
        activeSeries.value = new Set(
            (props.chartData.series ?? []).map((seriesItem) => seriesItem.key),
        );
    },
);

const isDialogOpen = ref(false);
const editingId = ref<number | null>(null);

const today = () => new Date().toISOString().slice(0, 10);

const selectedYear = ref('');
const selectedMonth = ref('');
const selectedDay = ref('');

const years = computed(() => {
    const currentYear = new Date().getFullYear();

    return Array.from({ length: 17 }, (_, yearIndex) =>
        String(currentYear + 1 - yearIndex),
    );
});

const months = [
    { value: '01', label: 'January' },
    { value: '02', label: 'February' },
    { value: '03', label: 'March' },
    { value: '04', label: 'April' },
    { value: '05', label: 'May' },
    { value: '06', label: 'June' },
    { value: '07', label: 'July' },
    { value: '08', label: 'August' },
    { value: '09', label: 'September' },
    { value: '10', label: 'October' },
    { value: '11', label: 'November' },
    { value: '12', label: 'December' },
];

const daysInMonth = computed(() => {
    const selectedYearNumber = Number(
        selectedYear.value || new Date().getFullYear(),
    );
    const selectedMonthNumber = Number(selectedMonth.value || 1);
    const dayCount = new Date(
        selectedYearNumber,
        selectedMonthNumber,
        0,
    ).getDate();

    return Array.from({ length: dayCount }, (_, dayIndex) =>
        String(dayIndex + 1).padStart(2, '0'),
    );
});

const form = useForm({
    asset_type: '',
    quantity: '',
    note: '',
    occurred_at: today(),
    cost_basis: '',
    cost_basis_currency: '',
});

const fieldClass =
    'finance-dialog-field finance-dialog-field-cost focus-visible:ring-[#947BFF]/30';

function syncDatePicker(date: string) {
    const [yearPart, monthPart, dayPart] = date.split('-');
    selectedYear.value = yearPart ?? '';
    selectedMonth.value = monthPart ?? '';
    selectedDay.value = dayPart ?? '';
}

watch([selectedYear, selectedMonth, selectedDay], () => {
    if (!selectedYear.value || !selectedMonth.value || !selectedDay.value) {
        return;
    }

    form.occurred_at = `${selectedYear.value}-${selectedMonth.value}-${selectedDay.value}`;
});

watch(daysInMonth, (days) => {
    if (selectedDay.value && !days.includes(selectedDay.value)) {
        selectedDay.value = days.at(-1) ?? '';
    }
});

function openCreateDialog(defaultType?: AssetKey) {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.asset_type = defaultType ?? props.assetTypes[0]?.value ?? '';
    form.occurred_at = today();
    syncDatePicker(form.occurred_at);
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
    form.cost_basis_currency = entry.cost_basis_currency ?? '';
    syncDatePicker(entry.occurred_at);
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

function deleteEntry(investmentId: number) {
    if (!confirm('Delete this investment entry?')) {
        return;
    }

    router.delete(`/investments/${investmentId}`, { preserveScroll: true });
}

function formatEntryValue(quantity: number, assetType: AssetKey): string {
    const price = props.prices[assetType] ?? 0;

    return new Intl.NumberFormat('en-US').format(Math.round(quantity * price));
}

syncDatePicker(form.occurred_at);
</script>
