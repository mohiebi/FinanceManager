<script setup lang="ts">
import type ApexCharts from 'apexcharts';
import type { ApexChartEventOpts } from 'apexcharts';
import { onMounted, onUnmounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        labels: string[];
        values: number[];
        colors: string[];
        seriesName: string;
        height?: number;
        percentageLabels?: boolean;
        // The values themselves are already percentages (0-100); format
        // labels, axis, and tooltip accordingly instead of as counts.
        percentValues?: boolean;
        // Bars report clicks through `select`, and show that they can.
        selectable?: boolean;
    }>(),
    {
        percentageLabels: true,
        percentValues: false,
        selectable: false,
    },
);

const emit = defineEmits<{
    select: [index: number];
}>();

const chartRef = ref<HTMLElement | null>(null);
let chart: ApexCharts | null = null;

const formatAmount = (amount: number): string => {
    if (amount >= 1_000_000_000) {
        return (amount / 1_000_000_000).toFixed(1) + 'B';
    }

    if (amount >= 1_000_000) {
        return (amount / 1_000_000).toFixed(1) + 'M';
    }

    if (amount >= 1_000) {
        return (amount / 1_000).toFixed(0) + 'K';
    }

    return amount.toFixed(0);
};

const buildOptions = () => {
    const total = props.values.reduce((acc, value) => acc + value, 0);

    return {
        chart: {
            type: 'bar' as const,
            height: props.height ?? 280,
            background: 'transparent',
            toolbar: { show: false },
            animations: {
                enabled: true,
                speed: 500,
                easing: 'easeinout' as const,
            },
            fontFamily: 'inherit',
            events: {
                dataPointSelection: (
                    _event: MouseEvent,
                    _chart?: ApexCharts,
                    options?: ApexChartEventOpts,
                ) => {
                    if (props.selectable && options !== undefined) {
                        emit('select', options.dataPointIndex);
                    }
                },
            },
        },
        series: [{ name: props.seriesName, data: props.values }],
        colors: props.colors,
        plotOptions: {
            bar: {
                horizontal: true,
                distributed: true,
                barHeight: '55%',
                borderRadius: 4,
                borderRadiusApplication: 'end' as const,
            },
        },
        dataLabels: {
            enabled: true,
            formatter: (value: number) => {
                if (props.percentValues) {
                    return value.toFixed(1) + '%';
                }

                return props.percentageLabels && total > 0
                    ? ((value / total) * 100).toFixed(0) + '%'
                    : formatAmount(value);
            },
            style: { fontSize: '11px', fontWeight: 600 },
            offsetX: 4,
        },
        xaxis: {
            categories: props.labels,
            axisBorder: { show: false },
            axisTicks: { show: false },
            max: props.percentValues ? 100 : undefined,
            labels: {
                style: { colors: '#686868', fontSize: '11px' },
                formatter: (value: number) =>
                    props.percentValues
                        ? Number(value).toFixed(0) + '%'
                        : formatAmount(value),
            },
        },
        yaxis: {
            labels: {
                style: { colors: '#989898', fontSize: '12px' },
                maxWidth: 110,
            },
        },
        grid: {
            borderColor: '#252525',
            strokeDashArray: 4,
            xaxis: { lines: { show: true } },
            yaxis: { lines: { show: false } },
            padding: { top: 0, right: 24, bottom: 0, left: 8 },
        },
        legend: { show: false },
        tooltip: {
            theme: 'dark' as const,
            y: {
                formatter: (value: number) =>
                    props.percentValues
                        ? value.toFixed(1) + '%'
                        : formatAmount(value),
            },
            style: { fontSize: '12px' },
        },
        states: {
            hover: { filter: { type: 'lighten' as const, value: 0.08 } },
            // A click swaps the data rather than marking a bar, so ApexCharts'
            // sticky "selected" shading would only linger on the wrong bar.
            active: { filter: { type: 'none' as const } },
        },
    };
};

onMounted(async () => {
    if (!chartRef.value) {
        return;
    }

    const { default: ApexCharts } = await import('apexcharts');

    if (!chartRef.value) {
        return;
    }

    chart = new ApexCharts(chartRef.value, buildOptions());
    chart.render();
});

watch(
    () => [props.values, props.labels],
    () => chart?.updateOptions(buildOptions(), false, true),
    { deep: true },
);

onUnmounted(() => chart?.destroy());
</script>

<template>
    <div
        ref="chartRef"
        :class="
            props.selectable ? '[&_.apexcharts-bar-area]:cursor-pointer' : ''
        "
    />
</template>
