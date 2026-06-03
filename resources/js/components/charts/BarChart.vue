<script setup lang="ts">
import ApexCharts from 'apexcharts';
import { onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    incomeData: number[];
    costData: number[];
    categories: string[];
    height?: number;
}>();

const chartRef = ref<HTMLElement | null>(null);
let chart: ApexCharts | null = null;

const fmt = (val: number) => {
    if (val >= 1_000_000_000) return (val / 1_000_000_000).toFixed(1) + 'B';
    if (val >= 1_000_000)     return (val / 1_000_000).toFixed(1) + 'M';
    if (val >= 1_000)         return (val / 1_000).toFixed(0) + 'K';
    return val.toFixed(0);
};

const buildOptions = () => ({
    chart: {
        type: 'bar' as const,
        height: props.height ?? 220,
        background: 'transparent',
        toolbar: { show: false },
        animations: { enabled: true, speed: 500, easing: 'easeinout' as const },
        fontFamily: 'inherit',
    },
    series: [
        { name: 'Income', data: props.incomeData },
        { name: 'Costs',  data: props.costData  },
    ],
    colors: ['#02CD86', '#6C4EE9'],
    xaxis: {
        categories: props.categories,
        axisBorder: { show: false },
        axisTicks:  { show: false },
        labels: {
            style: { colors: '#989898', fontSize: '11px' },
        },
    },
    yaxis: {
        labels: {
            style: { colors: '#989898', fontSize: '11px' },
            formatter: fmt,
        },
        axisBorder: { show: false },
        axisTicks:  { show: false },
    },
    plotOptions: {
        bar: {
            columnWidth: '58%',
            borderRadius: 5,
            borderRadiusApplication: 'end' as const,
        },
    },
    dataLabels: { enabled: false },
    grid: {
        borderColor: '#f0f0f0',
        strokeDashArray: 4,
        xaxis: { lines: { show: false } },
        yaxis: { lines: { show: true  } },
        padding: { top: 0, right: 8, bottom: 0, left: 0 },
    },
    tooltip: {
        theme: 'light',
        shared: true,
        intersect: false,
        y: {
            formatter: (val: number) =>
                new Intl.NumberFormat('en-US').format(val),
        },
    },
    legend: { show: false },
    noData: {
        text: 'No transactions yet',
        style: { color: '#989898', fontSize: '12px' },
    },
});

onMounted(() => {
    if (!chartRef.value) return;
    chart = new ApexCharts(chartRef.value, buildOptions());
    chart.render();
});

watch(
    () => [props.incomeData, props.costData, props.categories],
    () => chart?.updateOptions(buildOptions(), false, true),
    { deep: true },
);

onUnmounted(() => chart?.destroy());
</script>

<template>
    <div ref="chartRef" />
</template>
