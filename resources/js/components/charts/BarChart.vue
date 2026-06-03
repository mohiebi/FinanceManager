<script setup lang="ts">
import ApexCharts from 'apexcharts';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

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

// Column width scales up when fewer categories exist so bars never look thin
const columnWidth = computed(() => {
    const count = props.categories.length;
    if (count <= 2) return '40%';
    if (count <= 3) return '55%';
    if (count <= 4) return '65%';
    return '75%';
});

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
        min: 0,
    },
    plotOptions: {
        bar: {
            columnWidth: columnWidth.value,
            borderRadius: 6,
            borderRadiusApplication: 'end' as const,
            borderRadiusWhenStacked: 'last' as const,
            dataLabels: { position: 'top' },
        },
    },
    dataLabels: { enabled: false },
    grid: {
        borderColor: '#f0f0f0',
        strokeDashArray: 4,
        xaxis: { lines: { show: false } },
        yaxis: { lines: { show: true  } },
        padding: { top: 0, right: 16, bottom: 0, left: 8 },
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
    fill: {
        type: 'gradient',
        gradient: {
            shade: 'light',
            type: 'vertical',
            shadeIntensity: 0.15,
            opacityFrom: 1,
            opacityTo: 0.85,
            stops: [0, 100],
        },
    },
    legend: { show: false },
    noData: {
        text: 'No transactions yet',
        align: 'center' as const,
        verticalAlign: 'middle' as const,
        style: { color: '#989898', fontSize: '13px' },
    },
    states: {
        hover: { filter: { type: 'lighten' as const, value: 0.08 } },
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
