<script setup lang="ts">
import ApexCharts from 'apexcharts';
import { onMounted, onUnmounted, ref, watch } from 'vue';

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
}>();

const chartRef = ref<HTMLElement | null>(null);
let chart: ApexCharts | null = null;

const formatAxisAmount = (amount: number) => {
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
    series: props.series.map((seriesItem) => ({
        name: seriesItem.name,
        data: seriesItem.data,
    })),
    colors: props.series.map((seriesItem) => seriesItem.color),
    xaxis: {
        categories: props.categories,
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: {
            style: { colors: '#989898', fontSize: '11px' },
            hideOverlappingLabels: true,
            rotate: 0,
            formatter: (categoryValue: string) => {
                const categoryDate = new Date(categoryValue);

                if (isNaN(categoryDate.getTime())) {
                    return categoryValue;
                }

                return categoryDate.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                });
            },
        },
        crosshairs: { stroke: { color: '#e6e6e6', dashArray: 4 } },
        tooltip: { enabled: false },
    },
    yaxis: {
        labels: {
            style: { colors: '#989898', fontSize: '11px' },
            formatter: formatAxisAmount,
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    stroke: { curve: 'smooth' as const, width: 2 },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.22,
            opacityTo: 0.0,
            stops: [0, 85, 100],
        },
    },
    grid: {
        borderColor: '#f0f0f0',
        strokeDashArray: 4,
        xaxis: { lines: { show: false } },
        yaxis: { lines: { show: true } },
        padding: { top: 4, right: 16, bottom: 0, left: 8 },
    },
    tooltip: {
        theme: 'light' as const,
        shared: true,
        intersect: false,
        y: {
            formatter: (amount: number) =>
                new Intl.NumberFormat('en-US').format(amount) + ' T',
        },
    },
    legend: {
        show: props.series.length > 1,
        position: 'top' as const,
        horizontalAlign: 'right' as const,
        fontFamily: 'inherit',
        fontSize: '12px',
        labels: { colors: '#2d2d2d' },
        markers: { size: 6 },
        itemMargin: { horizontal: 8 },
    },
    markers: {
        size: 0,
        hover: { size: 4 },
    },
    noData: {
        text: 'No data yet — add your first investment entry.',
        style: { color: '#989898', fontSize: '13px' },
    },
});

onMounted(() => {
    if (!chartRef.value) {
        return;
    }

    chart = new ApexCharts(chartRef.value, buildOptions());
    chart.render();
});

watch(
    () => [props.series, props.categories],
    () => chart?.updateOptions(buildOptions(), false, true),
    { deep: true },
);

onUnmounted(() => chart?.destroy());
</script>

<template>
    <div ref="chartRef" />
</template>
