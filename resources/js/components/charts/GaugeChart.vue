<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    value: number; // 0–100
}>();

const cx = 100;
const cy = 106;
const r  = 80;

const clamped    = computed(() => Math.max(0, Math.min(100, Math.round(props.value))));
const v          = computed(() => clamped.value / 100);

const gaugeColor = computed(() => {
    if (clamped.value < 40) return '#E94E50';
    if (clamped.value < 60) return '#F59E0B';
    return '#02CD86';
});

// Background arc — full semicircle, flat (butt) linecaps so no blobs at endpoints
const bgArc = `M ${cx - r} ${cy} A ${r} ${r} 0 0 1 ${cx + r} ${cy}`;

// Value arc — from left endpoint to the position for v
const valueArc = computed<string | null>(() => {
    const val = v.value;
    if (val <= 0.005) return null;
    if (val >= 0.995) {
        // Split to avoid SVG degenerate full-circle arc
        return `M ${cx - r} ${cy} A ${r} ${r} 0 0 1 ${cx} ${cy - r} A ${r} ${r} 0 0 1 ${cx + r} ${cy}`;
    }
    const angle = Math.PI * (1 - val);
    const ex    = (cx + r * Math.cos(angle)).toFixed(2);
    const ey    = (cy - r * Math.sin(angle)).toFixed(2);
    const large = val > 0.5 ? 1 : 0;
    return `M ${cx - r} ${cy} A ${r} ${r} 0 ${large} 1 ${ex} ${ey}`;
});
</script>

<template>
    <div class="flex flex-col items-center">
        <svg
            viewBox="0 0 200 118"
            class="w-full max-w-[200px]"
            role="img"
            :aria-label="`Finance Rate: ${clamped}%`"
        >
            <!-- Background track — butt linecaps = no endpoint blobs -->
            <path
                :d="bgArc"
                fill="none"
                stroke="#E2DBFF"
                stroke-width="8"
                stroke-linecap="butt"
            />

            <!-- Value arc — round linecap only at the leading tip -->
            <path
                v-if="valueArc"
                :d="valueArc"
                fill="none"
                :stroke="gaugeColor"
                stroke-width="8"
                stroke-linecap="round"
            />

            <!-- Value label -->
            <text
                :x="cx"
                :y="cy - 18"
                text-anchor="middle"
                font-size="28"
                font-weight="700"
                :fill="gaugeColor"
                font-family="inherit"
            >{{ clamped }}%</text>

            <!-- 0% / 100% anchor labels -->
            <text
                :x="cx - r - 2"
                :y="cy + 14"
                font-size="9"
                fill="#BBBBBB"
                text-anchor="middle"
                font-family="inherit"
            >0%</text>
            <text
                :x="cx + r + 2"
                :y="cy + 14"
                font-size="9"
                fill="#BBBBBB"
                text-anchor="middle"
                font-family="inherit"
            >100%</text>
        </svg>
    </div>
</template>
