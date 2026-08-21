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
    /** Use the solid placeholder only for prominent summary figures. */
    maskVariant?: 'blur' | 'placeholder';
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
        masked.value && props.maskVariant === 'placeholder'
            ? "relative inline-block !text-transparent transition-colors duration-150 select-none before:absolute before:inset-x-0 before:top-1/2 before:h-[0.7em] before:-translate-y-1/2 before:rounded-sm before:bg-white/30 before:content-['']"
            : masked.value
              ? 'blur-[6px] transition-[filter] duration-150 select-none'
              : 'transition-[filter] duration-150',
        isNegative.value &&
        !(masked.value && props.maskVariant === 'placeholder')
            ? '!text-[#E94E50]'
            : '',
    ].join(' '),
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
