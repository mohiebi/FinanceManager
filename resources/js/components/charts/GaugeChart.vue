<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    value: number; // 0–100
}>();

// SVG geometry constants
const cx = 100;
const cy = 112;
const r  = 82;

const clamped    = computed(() => Math.max(0, Math.min(100, Math.round(props.value))));
const v          = computed(() => clamped.value / 100);

const gaugeColor = computed(() => {
    if (clamped.value < 40) return '#E94E50';
    if (clamped.value < 60) return '#F59E0B';
    return '#02CD86';
});

// Full-semicircle background arc
const bgArc = `M ${cx - r} ${cy} A ${r} ${r} 0 0 1 ${cx + r} ${cy}`;

// Value arc from the left endpoint up to the current angle
const valueArc = computed<string | null>(() => {
    const val = v.value;
    if (val <= 0.005) return null;
    if (val >= 0.995) {
        // Split into two halves to avoid SVG degenerate-arc bug
        return `M ${cx - r} ${cy} A ${r} ${r} 0 0 1 ${cx} ${cy - r} A ${r} ${r} 0 0 1 ${cx + r} ${cy}`;
    }
    const angle = Math.PI * (1 - val);
    const ex    = cx + r * Math.cos(angle);
    const ey    = cy - r * Math.sin(angle);
    const large = val > 0.5 ? 1 : 0;
    return `M ${cx - r} ${cy} A ${r} ${r} 0 ${large} 1 ${ex.toFixed(2)} ${ey.toFixed(2)}`;
});

// Glowing dot position at arc tip
const tipX = computed(() => cx + r * Math.cos(Math.PI * (1 - v.value)));
const tipY = computed(() => cy - r * Math.sin(Math.PI * (1 - v.value)));

// Tick marks — 11 ticks across the arc
const ticks = Array.from({ length: 11 }, (_, i) => {
    const a   = Math.PI * (1 - i / 10);
    const r1  = r + 3;
    const r2  = r + 9;
    return {
        x1: cx + r1 * Math.cos(a),
        y1: cy - r1 * Math.sin(a),
        x2: cx + r2 * Math.cos(a),
        y2: cy - r2 * Math.sin(a),
    };
});
</script>

<template>
    <div class="flex flex-col items-center">
        <svg
            viewBox="0 0 200 128"
            class="w-full max-w-[210px]"
            aria-label="`Finance Rate: ${clamped}%`"
        >
            <!-- Tick marks -->
            <line
                v-for="(tick, i) in ticks"
                :key="i"
                :x1="tick.x1"
                :y1="tick.y1"
                :x2="tick.x2"
                :y2="tick.y2"
                stroke="#e6e6e6"
                stroke-width="1.5"
                stroke-linecap="round"
            />

            <!-- Background track -->
            <path
                :d="bgArc"
                fill="none"
                stroke="#f0ecff"
                stroke-width="13"
                stroke-linecap="round"
            />

            <!-- Colored value arc -->
            <path
                v-if="valueArc"
                :d="valueArc"
                fill="none"
                :stroke="gaugeColor"
                stroke-width="13"
                stroke-linecap="round"
            />

            <!-- Glowing tip dot -->
            <circle
                v-if="v > 0.005"
                :cx="tipX"
                :cy="tipY"
                r="8"
                :fill="gaugeColor"
                opacity="0.20"
            />
            <circle
                v-if="v > 0.005"
                :cx="tipX"
                :cy="tipY"
                r="4.5"
                :fill="gaugeColor"
            />

            <!-- Big value label -->
            <text
                :x="cx"
                :y="cy - 22"
                text-anchor="middle"
                font-size="26"
                font-weight="700"
                :fill="gaugeColor"
                font-family="inherit"
            >{{ clamped }}%</text>

            <!-- 0% / 100% end labels -->
            <text
                :x="cx - r + 2"
                :y="cy + 16"
                font-size="9"
                fill="#989898"
                text-anchor="middle"
                font-family="inherit"
            >0%</text>
            <text
                :x="cx + r - 2"
                :y="cy + 16"
                font-size="9"
                fill="#989898"
                text-anchor="middle"
                font-family="inherit"
            >100%</text>
        </svg>
    </div>
</template>
