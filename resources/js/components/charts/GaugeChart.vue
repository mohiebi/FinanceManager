<script setup lang="ts">
import ApexCharts from 'apexcharts';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    value: number;
    height?: number;
}>();

const chartRef = ref<HTMLElement | null>(null);
let chart: ApexCharts | null = null;

const clampedValue = computed(() =>
    Math.max(0, Math.min(100, Math.round(props.value))),
);

const gaugeColor = computed(() => {
    if (clampedValue.value < 40) {
        return '#E94E50';
    }

    if (clampedValue.value < 60) {
        return '#F59E0B';
    }

    return '#02CD86';
});

const buildOptions = () => ({
    chart: {
        type: 'radialBar' as const,
        height: props.height ?? 166,
        background: 'transparent',
        sparkline: { enabled: true },
        toolbar: { show: false },
        animations: { enabled: true, speed: 500, easing: 'easeinout' as const },
        fontFamily: 'inherit',
    },
    series: [clampedValue.value],
    colors: [gaugeColor.value],
    plotOptions: {
        radialBar: {
            startAngle: -90,
            endAngle: 90,
            hollow: {
                margin: 0,
                size: '66%',
                background: 'transparent',
            },
            track: {
                background: '#E2DBFF',
                strokeWidth: '100%',
                margin: 0,
                startAngle: -90,
                endAngle: 90,
            },
            dataLabels: {
                name: { show: false },
                value: {
                    show: true,
                    offsetY: -4,
                    color: gaugeColor.value,
                    fontSize: '28px',
                    fontWeight: 700,
                    formatter: (percentageValue: number) =>
                        `${Math.round(percentageValue)}%`,
                },
            },
        },
    },
    stroke: {
        lineCap: 'round' as const,
    },
    fill: {
        type: 'solid',
        colors: [gaugeColor.value],
    },
    states: {
        hover: { filter: { type: 'none' as const } },
        active: { filter: { type: 'none' as const } },
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
    () => [props.value, props.height],
    () => chart?.updateOptions(buildOptions(), false, true),
);

onUnmounted(() => chart?.destroy());
</script>

<template>
    <div
        class="relative flex w-full max-w-[210px] flex-col items-center"
        role="img"
        :aria-label="`Finance Rate: ${clampedValue}%`"
    >
        <div ref="chartRef" class="w-full" />
        <div
            class="pointer-events-none absolute right-[13px] bottom-[18px] left-[13px] flex items-center justify-between text-[10px] text-[#BBBBBB]"
        >
            <span>0%</span>
            <span>100%</span>
        </div>
    </div>
</template>
