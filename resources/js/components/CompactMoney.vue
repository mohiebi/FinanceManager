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
import {
    formatCompactCurrencyDisplay,
    formatCurrencyDisplay,
    isNegativeCurrencyValue,
} from '@/lib/money';
import type { CurrencyCode } from '@/lib/money';
import type { DisplayNumber } from '@/lib/number';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    value: DisplayNumber;
    currency: CurrencyCode;
}>();

const attrs = useAttrs();
const { masked } = useAmountMask();
const { compact } = useCompactFigures();

const full = computed(() => formatCurrencyDisplay(props.value, props.currency));
const abbreviated = computed(() =>
    formatCompactCurrencyDisplay(props.value, props.currency),
);
const hasAbbreviation = computed(
    () => compact.value && abbreviated.value !== full.value,
);
const isNegative = computed(() => isNegativeCurrencyValue(props.value));
const valueClass = computed(() =>
    [
        'transition-[filter] duration-150',
        isNegative.value ? '!text-[#E94E50]' : '',
    ].join(' '),
);
const displayedValue = computed(() =>
    masked.value
        ? '••••••'
        : hasAbbreviation.value
          ? abbreviated.value
          : full.value,
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
                    dir="ltr"
                >
                    {{ displayedValue }}
                </span>
            </TooltipTrigger>
            <TooltipContent
                side="top"
                class="border border-white/10 bg-[#252525] text-white shadow-xl"
            >
                <span
                    class="tabular-nums"
                    :class="isNegative ? 'text-[#E94E50]' : ''"
                    dir="ltr"
                    >{{ full }}</span
                >
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>

    <span
        v-else
        v-bind="attrs"
        :aria-label="masked ? 'Amount hidden' : undefined"
        :class="valueClass"
        dir="ltr"
    >
        {{ displayedValue }}
    </span>
</template>
