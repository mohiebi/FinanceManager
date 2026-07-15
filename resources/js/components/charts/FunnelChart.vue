<script setup lang="ts">
import { computed } from 'vue';
import { formatAdminNumber } from '@/lib/adminFormat';

const props = defineProps<{
    labels: string[];
    values: number[];
}>();

type FunnelStage = {
    label: string;
    value: number;
    shareOfFirst: number;
    dropFromPrevious: number | null;
};

const stages = computed<FunnelStage[]>(() => {
    const first = props.values[0] ?? 0;

    return props.labels.map((label, index) => {
        const value = props.values[index] ?? 0;
        const previous = index > 0 ? (props.values[index - 1] ?? 0) : null;

        return {
            label,
            value,
            shareOfFirst: first > 0 ? (value / first) * 100 : 0,
            dropFromPrevious:
                previous !== null && previous > 0
                    ? ((previous - value) / previous) * 100
                    : null,
        };
    });
});

const hasData = computed(() => (props.values[0] ?? 0) > 0);
</script>

<template>
    <div>
        <ol v-if="hasData" class="flex flex-col gap-2.5">
            <li v-for="stage in stages" :key="stage.label">
                <div
                    class="flex items-baseline justify-between gap-3 text-xs text-[#989898]"
                >
                    <span>{{ stage.label }}</span>
                    <span class="tabular-nums">
                        {{ formatAdminNumber(stage.value) }}
                        <span class="text-[#686868]">
                            · {{ stage.shareOfFirst.toFixed(0) }}%
                        </span>
                        <span
                            v-if="
                                stage.dropFromPrevious !== null &&
                                stage.dropFromPrevious > 0
                            "
                            class="text-[#ff9f9f]"
                        >
                            · −{{ stage.dropFromPrevious.toFixed(0) }}%
                        </span>
                    </span>
                </div>
                <div class="mt-1 h-7 rounded-lg bg-white/[0.04]">
                    <div
                        class="h-full min-w-1 rounded-lg bg-gradient-to-r from-[#02CD86] to-[#4cb6a2] transition-[width] duration-500"
                        :style="{
                            width: `${Math.max(stage.shareOfFirst, 1)}%`,
                        }"
                    />
                </div>
            </li>
        </ol>
        <p
            v-else
            class="flex h-[280px] items-center justify-center text-sm text-[#686868]"
        >
            No signups in this period yet
        </p>
    </div>
</template>
