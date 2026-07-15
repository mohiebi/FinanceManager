<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        label: string;
        value: string;
        caption?: string;
        captionTone?: 'muted' | 'green' | 'purple';
        footnote?: string;
        delta?: number | null;
        deltaLabel?: string;
        clickable?: boolean;
        active?: boolean;
    }>(),
    {
        caption: undefined,
        captionTone: 'muted',
        footnote: undefined,
        delta: null,
        deltaLabel: undefined,
        clickable: false,
        active: false,
    },
);

const emit = defineEmits<{ select: [] }>();

const captionClass = computed(
    () =>
        ({
            muted: 'text-[#686868]',
            green: 'text-[#7ee8c4]',
            purple: 'text-[#947bff]',
        })[props.captionTone],
);

function handleClick(): void {
    if (props.clickable) {
        emit('select');
    }
}
</script>

<template>
    <component
        :is="clickable ? 'button' : 'article'"
        :type="clickable ? 'button' : undefined"
        :aria-pressed="clickable ? active : undefined"
        class="rounded-[18px] bg-[#1a1a1a] p-5 text-left ring-1 transition"
        :class="[
            active ? 'ring-[#02CD86]/60' : 'ring-white/10',
            clickable
                ? 'cursor-pointer hover:bg-[#1f1f1f] hover:ring-white/20 active:translate-y-px'
                : '',
        ]"
        @click="handleClick"
    >
        <div class="flex items-center justify-between">
            <slot name="icon" />
            <span v-if="caption" class="text-xs" :class="captionClass">{{
                caption
            }}</span>
        </div>
        <p class="mt-5 text-3xl font-semibold text-white tabular-nums">
            {{ value }}
        </p>
        <p
            class="mt-1 flex flex-wrap items-center gap-2 text-sm text-[#989898]"
        >
            {{ label }}
            <span
                v-if="delta !== null"
                class="rounded-md px-1.5 py-0.5 text-[11px] font-medium tabular-nums"
                :class="
                    delta >= 0
                        ? 'bg-[#0d2e22] text-[#7ee8c4]'
                        : 'bg-[#2f1717] text-[#ffb4b4]'
                "
                :title="deltaLabel"
            >
                {{ delta >= 0 ? '+' : '' }}{{ delta.toFixed(1) }}%
            </span>
        </p>
        <p
            v-if="footnote || $slots.footnote"
            class="mt-3 text-xs text-[#686868]"
        >
            <slot name="footnote">{{ footnote }}</slot>
        </p>
    </component>
</template>
