<script setup lang="ts">
import ApexCharts from 'apexcharts';
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
        labels: { colors: '#2d2d2d' },
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
                (runningTotal: number, seriesValue: number) =>
                    runningTotal + seriesValue,
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
                        color: '#2d2d2d',
                        offsetY: 4,
                        formatter: (percentageValue: string) =>
                            percentageValue + '%',
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
    stroke: { width: 2, colors: ['#ffffff'] },
    tooltip: {
        theme: 'light' as const,
        y: {
            formatter: (percentageValue: number) =>
                percentageValue.toFixed(1) + '%',
        },
    },
    states: {
        hover: { filter: { type: 'lighten' as const, value: 0.08 } },
        active: { filter: { type: 'darken' as const, value: 0.12 } },
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
    () => [props.series, props.labels, props.centerValue],
    () => chart?.updateOptions(buildOptions(), false, true),
    { deep: true },
);

onUnmounted(() => chart?.destroy());
</script>

<template>
    <div ref="chartRef" />
</template>
