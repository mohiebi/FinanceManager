<script setup lang="ts">
import { computed } from 'vue';
import type { RingSegment } from '@/lib/advisor/series';
import { ringGradient } from '@/lib/advisor/series';

const props = withDefaults(
    defineProps<{
        segments: RingSegment[];
        /** How far round the sweep has reached, 0–100. */
        filled?: number;
        /** Diameter in pixels; the design uses 268 on both screens that show it. */
        size?: number;
        /** The linear, thinner variant used while a plan is being generated. */
        filling?: boolean;
        /** Accessible description — the segments in words, since colour alone says nothing. */
        label?: string;
    }>(),
    { filled: 100, size: 268, filling: false, label: undefined },
);

const gradient = computed(() => ringGradient(props.segments, props.filled));
</script>

<template>
    <div
        class="relative mx-auto"
        :style="{ width: `${size}px`, height: `${size}px` }"
    >
        <!-- The glow sits behind the ring rather than inside it, so it never
             tints a segment colour. -->
        <div
            class="pointer-events-none absolute -inset-[30px] bg-[radial-gradient(circle,rgba(217,196,143,0.09),transparent_62%)]"
            aria-hidden="true"
        />
        <div
            class="absolute inset-0"
            :class="
                filling ? 'advisor-ring advisor-ring-filling' : 'advisor-ring'
            "
            :style="{ background: gradient }"
            :role="label ? 'img' : undefined"
            :aria-label="label"
            :aria-hidden="label ? undefined : 'true'"
        />
        <div class="absolute inset-0 grid place-items-center text-center">
            <div><slot /></div>
        </div>
    </div>
</template>
