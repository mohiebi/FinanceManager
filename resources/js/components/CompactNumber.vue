<script setup lang="ts">
import { computed, useAttrs } from 'vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAmountMask } from '@/composables/useAmountMask';
import { useCompactFigures } from '@/composables/useCompactFigures';
import { formatCompactNumber, formatFullNumber } from '@/lib/number';
import type { DisplayNumber } from '@/lib/number';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    value: DisplayNumber;
}>();

const attrs = useAttrs();
const { masked } = useAmountMask();
const { compact } = useCompactFigures();

const full = computed(() => formatFullNumber(props.value));
const abbreviated = computed(() => formatCompactNumber(props.value));
const hasAbbreviation = computed(
    () => compact.value && abbreviated.value !== full.value,
);
const valueClass = computed(() =>
    masked.value
        ? 'blur-[6px] transition-[filter] duration-150 select-none'
        : 'transition-[filter] duration-150',
);
const displayedValue = computed(() =>
    hasAbbreviation.value ? abbreviated.value : full.value,
);
</script>

<template>
    <TooltipProvider v-if="hasAbbreviation && !masked" :delay-duration="120">
        <Tooltip>
            <TooltipTrigger as-child>
                <span
                    v-bind="attrs"
                    tabindex="0"
                    :aria-label="masked ? 'Amount hidden' : full"
                    :class="valueClass"
                >
                    {{ displayedValue }}
                </span>
            </TooltipTrigger>
            <TooltipContent
                side="top"
                class="border border-white/10 bg-[#252525] text-white shadow-xl"
            >
                <span class="tabular-nums" dir="ltr">{{ full }}</span>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>

    <span
        v-else
        v-bind="attrs"
        :aria-label="masked ? 'Amount hidden' : undefined"
        :class="valueClass"
    >
        {{ displayedValue }}
    </span>
</template>
