<script setup lang="ts">
import type ApexCharts from 'apexcharts';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    incomeData: number[];
    costData: number[];
    categories: string[];
    height?: number;
}>();

const chartRef = ref<HTMLElement | null>(null);
let chart: ApexCharts | null = null;
const { t } = useI18n();

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

const columnWidth = computed(() => {
    const categoryCount = props.categories.length;

    if (categoryCount <= 2) {
        return '40%';
    }

    if (categoryCount <= 3) {
        return '55%';
    }

    if (categoryCount <= 4) {
        return '65%';
    }

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
        { name: t('finance.metrics.income'), data: props.incomeData },
        { name: t('finance.metrics.costs'), data: props.costData },
    ],
    colors: ['#02CD86', '#6C4EE9'],
    xaxis: {
        categories: props.categories,
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: {
            style: { colors: '#989898', fontSize: '11px' },
        },
    },
    yaxis: {
        labels: {
            style: { colors: '#989898', fontSize: '11px' },
            formatter: formatAxisAmount,
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
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
        borderColor: '#252525',
        strokeDashArray: 4,
        xaxis: { lines: { show: false } },
        yaxis: { lines: { show: true } },
        padding: { top: 0, right: 16, bottom: 0, left: 8 },
    },
    tooltip: {
        theme: 'dark' as const,
        shared: true,
        intersect: false,
        y: {
            formatter: formatAxisAmount,
        },
        style: { fontSize: '12px' },
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
    () => [props.incomeData, props.costData, props.categories],
    () => chart?.updateOptions(buildOptions(), false, true),
    { deep: true },
);

onUnmounted(() => chart?.destroy());
</script>

<template>
    <div ref="chartRef" />
</template>
