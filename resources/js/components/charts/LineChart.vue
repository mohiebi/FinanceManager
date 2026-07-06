<script setup lang="ts">
import type ApexCharts from 'apexcharts';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { formatChartDateLabel } from '@/lib/date';

export type ChartSeries = {
    name: string;
    key: string;
    color: string;
    data: number[];
};

const props = defineProps<{
    series: ChartSeries[];
    categories: string[];
    height?: number;
    calendar?: string;
    // When the categories are already display-ready (e.g. month names),
    // skip the ISO-date reformatting of the x-axis labels.
    rawLabels?: boolean;
}>();

const chartRef = ref<HTMLElement | null>(null);
let chart: ApexCharts | null = null;

const abbreviate = (amount: number): string => {
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

const buildOptions = () => ({
    chart: {
        type: 'area' as const,
        height: props.height ?? 300,
        background: 'transparent',
        toolbar: { show: false },
        zoom: { enabled: false },
        fontFamily: 'inherit',
        animations: { enabled: true, speed: 500, easing: 'easeinout' as const },
    },
    dataLabels: { enabled: false },
    series: props.series.map((s) => ({ name: s.name, data: s.data })),
    colors: props.series.map((s) => s.color),
    xaxis: {
        categories: props.categories,
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: {
            style: { colors: '#686868', fontSize: '11px' },
            hideOverlappingLabels: true,
            rotate: 0,
            formatter: (v: string) =>
                props.rawLabels ? v : formatChartDateLabel(v, props.calendar),
        },
        crosshairs: { stroke: { color: '#333333', dashArray: 4 } },
        tooltip: { enabled: false },
    },
    yaxis: {
        labels: {
            style: { colors: '#686868', fontSize: '11px' },
            formatter: abbreviate,
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    stroke: { curve: 'smooth' as const, width: 2 },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.18,
            opacityTo: 0.0,
            stops: [0, 85, 100],
        },
    },
    grid: {
        borderColor: '#252525',
        strokeDashArray: 4,
        xaxis: { lines: { show: false } },
        yaxis: { lines: { show: true } },
        padding: { top: 4, right: 16, bottom: 0, left: 8 },
    },
    tooltip: {
        theme: 'dark' as const,
        shared: true,
        intersect: false,
        y: {
            formatter: (amount: number) => abbreviate(amount) + ' T',
        },
        style: { fontSize: '12px' },
    },
    legend: {
        show: props.series.length > 1,
        position: 'top' as const,
        horizontalAlign: 'right' as const,
        fontFamily: 'inherit',
        fontSize: '12px',
        labels: { colors: '#989898' },
        markers: { size: 6 },
        itemMargin: { horizontal: 8 },
    },
    markers: {
        size: 0,
        hover: { size: 4 },
    },
    noData: {
        text: 'No data yet — add your first investment entry.',
        style: { color: '#686868', fontSize: '13px' },
    },
});

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
    () => [props.series, props.categories, props.calendar],
    () => chart?.updateOptions(buildOptions(), false, true),
    { deep: true },
);

onUnmounted(() => chart?.destroy());
</script>

<template>
    <div ref="chartRef" />
</template>
