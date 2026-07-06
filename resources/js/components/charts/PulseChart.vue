<script setup lang="ts">
import type ApexCharts from 'apexcharts';
import { onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    data: number[];
    categories: string[];
    seriesName: string;
    height?: number;
    color?: string;
    highlightColor?: string;
}>();

const chartRef = ref<HTMLElement | null>(null);
let chart: ApexCharts | null = null;

const formatAxisAmount = (amount: number): string => {
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
    const maxValue = Math.max(...props.data, 0);
    const baseColor = props.color ?? '#6C4EE9';
    const peakColor = props.highlightColor ?? '#E94E50';

    return {
        chart: {
            type: 'bar' as const,
            height: props.height ?? 220,
            background: 'transparent',
            toolbar: { show: false },
            animations: {
                enabled: true,
                speed: 500,
                easing: 'easeinout' as const,
            },
            fontFamily: 'inherit',
        },
        series: [{ name: props.seriesName, data: props.data }],
        // The heaviest spending day is tinted differently so spikes pop.
        colors: [
            ({ value }: { value: number }) =>
                maxValue > 0 && value === maxValue ? peakColor : baseColor,
        ],
        plotOptions: {
            bar: {
                columnWidth: '60%',
                borderRadius: 2,
                borderRadiusApplication: 'end' as const,
            },
        },
        dataLabels: { enabled: false },
        xaxis: {
            categories: props.categories,
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: {
                style: { colors: '#686868', fontSize: '10px' },
                hideOverlappingLabels: true,
                rotate: 0,
            },
            crosshairs: { stroke: { color: '#333333', dashArray: 4 } },
            tooltip: { enabled: false },
        },
        yaxis: {
            labels: {
                style: { colors: '#686868', fontSize: '11px' },
                formatter: formatAxisAmount,
            },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        grid: {
            borderColor: '#252525',
            strokeDashArray: 4,
            xaxis: { lines: { show: false } },
            yaxis: { lines: { show: true } },
            padding: { top: 4, right: 8, bottom: 0, left: 8 },
        },
        tooltip: {
            theme: 'dark' as const,
            y: { formatter: formatAxisAmount },
            style: { fontSize: '12px' },
        },
        legend: { show: false },
        states: {
            hover: { filter: { type: 'lighten' as const, value: 0.08 } },
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
    () => [props.data, props.categories],
    () => chart?.updateOptions(buildOptions(), false, true),
    { deep: true },
);

onUnmounted(() => chart?.destroy());
</script>

<template>
    <div ref="chartRef" />
</template>
