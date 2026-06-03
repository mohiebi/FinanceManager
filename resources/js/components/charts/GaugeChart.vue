<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    value: number;
}>();

const centerX = 100;
const centerY = 106;
const radius = 80;

const clamped = computed(() =>
    Math.max(0, Math.min(100, Math.round(props.value))),
);
const valueRatio = computed(() => clamped.value / 100);

const gaugeColor = computed(() => {
    if (clamped.value < 40) {
        return '#E94E50';
    }

    if (clamped.value < 60) {
        return '#F59E0B';
    }

    return '#02CD86';
});

const backgroundArc = `M ${centerX - radius} ${centerY} A ${radius} ${radius} 0 0 1 ${centerX + radius} ${centerY}`;

const valueArc = computed<string | null>(() => {
    const currentRatio = valueRatio.value;

    if (currentRatio <= 0.005) {
        return null;
    }

    if (currentRatio >= 0.995) {
        return (
            `M ${centerX - radius} ${centerY}` +
            ` A ${radius} ${radius} 0 0 1 ${centerX} ${centerY - radius}` +
            ` A ${radius} ${radius} 0 0 1 ${centerX + radius} ${centerY}`
        );
    }

    const angle = Math.PI * (1 - currentRatio);
    const endpointX = (centerX + radius * Math.cos(angle)).toFixed(2);
    const endpointY = (centerY - radius * Math.sin(angle)).toFixed(2);
    const largeArc = currentRatio > 0.5 ? 1 : 0;

    return `M ${centerX - radius} ${centerY} A ${radius} ${radius} 0 ${largeArc} 1 ${endpointX} ${endpointY}`;
});

const indicatorX = computed(
    () => centerX + radius * Math.cos(Math.PI * (1 - valueRatio.value)),
);
const indicatorY = computed(
    () => centerY - radius * Math.sin(Math.PI * (1 - valueRatio.value)),
);
</script>

<template>
    <div class="flex flex-col items-center">
        <svg
            viewBox="0 0 200 134"
            class="w-full max-w-[200px]"
            role="img"
            :aria-label="`Finance Rate: ${clamped}%`"
        >
            <path
                :d="backgroundArc"
                fill="none"
                stroke="#E2DBFF"
                stroke-width="7"
                stroke-linecap="butt"
            />

            <path
                v-if="valueArc"
                :d="valueArc"
                fill="none"
                :stroke="gaugeColor"
                stroke-width="7"
                stroke-linecap="butt"
            />

            <circle
                v-if="valueRatio > 0.005 && valueRatio < 0.995"
                :cx="indicatorX"
                :cy="indicatorY"
                r="5.5"
                :fill="gaugeColor"
                stroke="white"
                stroke-width="2.5"
            />

            <text
                :x="centerX"
                :y="centerY - 18"
                text-anchor="middle"
                font-size="28"
                font-weight="700"
                :fill="gaugeColor"
                font-family="inherit"
            >
                {{ clamped }}%
            </text>

            <text
                :x="centerX - radius"
                :y="centerY + 14"
                font-size="9"
                fill="#BBBBBB"
                text-anchor="middle"
                font-family="inherit"
            >
                0%
            </text>
            <text
                :x="centerX + radius"
                :y="centerY + 14"
                font-size="9"
                fill="#BBBBBB"
                text-anchor="middle"
                font-family="inherit"
            >
                100%
            </text>
        </svg>
    </div>
</template>
