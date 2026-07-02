<script setup lang="ts">
import type ApexCharts from 'apexcharts';
import { onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    series: number[];
    labels: string[];
    colors: string[];
    centerLabel?: string;
    centerValue?: string;
}>();

const emit = defineEmits<{
    sliceClick: [index: number | null];
}>();

const chartRef = ref<HTMLElement | null>(null);
let chart: ApexCharts | null = null;
let activeSlice: number | null = null;

const buildOptions = () => ({
    chart: {
        type: 'donut' as const,
        height: 300,
        background: 'transparent',
        toolbar: { show: false },
        animations: { enabled: true, speed: 500 },
        events: {
            dataPointSelection: (
                _event: MouseEvent,
                _chartContext?: ApexCharts,
                config?: { dataPointIndex?: number },
            ) => {
                const sliceIndex = config?.dataPointIndex;

                if (sliceIndex === undefined || sliceIndex < 0) {
                    return;
                }

                if (activeSlice === sliceIndex) {
                    activeSlice = null;
                    emit('sliceClick', null);
                } else {
                    activeSlice = sliceIndex;
                    emit('sliceClick', sliceIndex);
                }
            },
        },
    },
    series: props.series,
    labels: props.labels,
    colors: props.colors,
    legend: {
        show: true,
        position: 'bottom' as const,
        fontFamily: 'inherit',
        fontSize: '12px',
        labels: { colors: '#989898' },
        markers: { size: 7 },
        itemMargin: { horizontal: 6, vertical: 3 },
        formatter: (
            label: string,
            legendOptions?: {
                w?: { globals?: { series?: number[] } };
                seriesIndex?: number;
            },
        ) => {
            const seriesValues = legendOptions?.w?.globals?.series ?? [];
            const seriesIndex = legendOptions?.seriesIndex ?? -1;
            const totalValue = seriesValues.reduce(
                (acc: number, v: number) => acc + v,
                0,
            );
            const currentValue =
                seriesIndex >= 0 ? (seriesValues[seriesIndex] ?? 0) : 0;
            const percentage =
                totalValue > 0
                    ? ((currentValue / totalValue) * 100).toFixed(1)
                    : '0';

            return `${label} — ${percentage}%`;
        },
    },
    plotOptions: {
        pie: {
            donut: {
                size: '72%',
                labels: {
                    show: true,
                    name: {
                        show: true,
                        fontSize: '13px',
                        fontFamily: 'inherit',
                        color: '#989898',
                        offsetY: -8,
                    },
                    value: {
                        show: true,
                        fontSize: '22px',
                        fontFamily: 'inherit',
                        fontWeight: 700,
                        color: '#ffffff',
                        offsetY: 4,
                        formatter: (v: string) => {
                            const total = props.series.reduce(
                                (acc, val) => acc + val,
                                0,
                            );
                            const percentage =
                                total > 0 ? (Number(v) / total) * 100 : 0;

                            return percentage.toFixed(1) + '%';
                        },
                    },
                    total: {
                        show: true,
                        label: props.centerLabel ?? 'Portfolio',
                        fontSize: '13px',
                        fontFamily: 'inherit',
                        color: '#989898',
                        formatter: () => props.centerValue ?? '',
                    },
                },
            },
        },
    },
    dataLabels: { enabled: false },
    stroke: { width: 2, colors: ['#1a1a1a'] },
    tooltip: {
        theme: 'dark' as const,
        y: {
            formatter: (v: number) => v.toFixed(1) + '%',
        },
        style: { fontSize: '12px' },
    },
    states: {
        hover: { filter: { type: 'lighten' as const, value: 0.08 } },
        active: { filter: { type: 'darken' as const, value: 0.12 } },
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
    () => [props.series, props.labels, props.centerValue],
    () => chart?.updateOptions(buildOptions(), false, true),
    { deep: true },
);

onUnmounted(() => chart?.destroy());
</script>

<template>
    <div ref="chartRef" />
</template>
