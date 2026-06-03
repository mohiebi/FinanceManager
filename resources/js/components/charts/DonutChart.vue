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
                _e: MouseEvent,
                _ctx?: ApexCharts,
                config?: { dataPointIndex?: number },
            ) => {
                const idx = config?.dataPointIndex;

                if (idx === undefined || idx < 0) {
                    return;
                }

                if (activeSlice === idx) {
                    activeSlice = null;
                    emit('sliceClick', null);
                } else {
                    activeSlice = idx;
                    emit('sliceClick', idx);
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
            opts?: {
                w?: { globals?: { series?: number[] } };
                seriesIndex?: number;
            },
        ) => {
            const series = opts?.w?.globals?.series ?? [];
            const index = opts?.seriesIndex ?? -1;
            const total = series.reduce((a: number, b: number) => a + b, 0);
            const value = index >= 0 ? (series[index] ?? 0) : 0;
            const pct = total > 0 ? ((value / total) * 100).toFixed(1) : '0';

            return `${label} — ${pct}%`;
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
                        formatter: (val: string) => val + '%',
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
        y: { formatter: (val: number) => val.toFixed(1) + '%' },
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
