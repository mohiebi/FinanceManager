<script setup lang="ts">
import type ApexCharts from 'apexcharts';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { formatChartDateLabel } from '@/lib/date';
import { formatCompactCurrencyNumber } from '@/lib/money';

export type ChartSeries = {
    name: string;
    key: string;
    color: string;
    data: number[];
    // Render this series as columns instead of an area line. Useful next to
    // a dual axis, where two lines with independent scales would invite a
    // misleading visual comparison of their heights.
    type?: 'area' | 'column';
};

const props = defineProps<{
    series: ChartSeries[];
    categories: string[];
    height?: number;
    calendar?: string;
    // When the categories are already display-ready (e.g. month names),
    // skip the ISO-date reformatting of the x-axis labels.
    rawLabels?: boolean;
    valuePrefix?: string;
    valueSuffix?: string;
    /** Use compact values for axes and tooltips. Defaults to preserve the
     * existing compact charts outside the Portfolio page. */
    compactValues?: boolean;
    /** Exact values retain the selected currency's precision when compact
     * figures are disabled. */
    valueFractionDigits?: number;
    noDataText?: string;
    // Plot the second series on an opposite y-axis so series with very
    // different magnitudes (e.g. new vs cumulative customers) stay readable.
    dualAxis?: boolean;
}>();

const chartRef = ref<HTMLElement | null>(null);
let chart: ApexCharts | null = null;

const formatExactValue = (amount: number): string => {
    const fractionDigits = Math.min(
        Math.max(props.valueFractionDigits ?? 0, 0),
        20,
    );

    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    }).format(Number.isFinite(amount) ? amount : 0);
};

const formatChartValue = (amount: number): string =>
    props.compactValues === false
        ? formatExactValue(amount)
        : formatCompactCurrencyNumber(amount);

const formatAxisValue = (amount: number): string =>
    (props.valuePrefix ?? '') +
    formatChartValue(amount) +
    (props.valueSuffix ?? '');

const formatTooltipValue = (amount: number): string =>
    (props.valuePrefix ?? '') +
    formatChartValue(amount) +
    (props.valueSuffix ?? ' T');

const hasMixedTypes = () => props.series.some((s) => s.type === 'column');

const buildOptions = () => ({
    chart: {
        type: hasMixedTypes() ? ('line' as const) : ('area' as const),
        height: props.height ?? 300,
        background: 'transparent',
        toolbar: { show: false },
        zoom: { enabled: false },
        fontFamily: 'inherit',
        animations: { enabled: true, speed: 500, easing: 'easeinout' as const },
    },
    dataLabels: { enabled: false },
    series: props.series.map((s) => ({
        name: s.name,
        data: s.data,
        ...(hasMixedTypes() ? { type: s.type ?? 'area' } : {}),
    })),
    colors: props.series.map((s) => s.color),
    plotOptions: {
        bar: {
            columnWidth: '45%',
            borderRadius: 3,
            borderRadiusApplication: 'end' as const,
        },
    },
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
    yaxis:
        props.dualAxis && props.series.length === 2
            ? props.series.map((s, index) => ({
                  seriesName: s.name,
                  opposite: index === 1,
                  labels: {
                      style: { colors: s.color, fontSize: '11px' },
                      formatter: formatAxisValue,
                  },
                  axisBorder: { show: false },
                  axisTicks: { show: false },
              }))
            : {
                  labels: {
                      style: { colors: '#686868', fontSize: '11px' },
                      formatter: formatAxisValue,
                  },
                  axisBorder: { show: false },
                  axisTicks: { show: false },
              },
    stroke: {
        curve: 'smooth' as const,
        width: hasMixedTypes()
            ? props.series.map((s) => (s.type === 'column' ? 0 : 2))
            : 2,
    },
    fill: {
        type: hasMixedTypes()
            ? props.series.map((s) =>
                  s.type === 'column' ? 'solid' : 'gradient',
              )
            : 'gradient',
        opacity: hasMixedTypes()
            ? props.series.map((s) => (s.type === 'column' ? 0.85 : 1))
            : 1,
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
            formatter: formatTooltipValue,
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
        text:
            props.noDataText ??
            'No data yet — add your first investment entry.',
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
    () => [
        props.series,
        props.categories,
        props.calendar,
        props.valuePrefix,
        props.valueSuffix,
        props.compactValues,
        props.valueFractionDigits,
    ],
    () => chart?.updateOptions(buildOptions(), false, true),
    { deep: true },
);

onUnmounted(() => chart?.destroy());
</script>

<template>
    <div ref="chartRef" />
</template>
