<script setup lang="ts">
withDefaults(
    defineProps<{
        icon?: string | null;
        iconSvg?: string | null;
        label: string;
        color?: string | null;
        size?: 'sm' | 'md' | 'lg';
    }>(),
    {
        icon: null,
        iconSvg: null,
        color: '#02CD86',
        size: 'md',
    },
);

const sizeClasses = {
    sm: 'size-5 text-xs',
    md: 'size-9 text-sm',
    lg: 'size-10 text-xl',
};
</script>

<template>
    <span
        class="asset-icon inline-flex shrink-0 items-center justify-center rounded-lg font-semibold"
        :class="sizeClasses[size]"
        :style="{
            backgroundColor: (iconSvg || icon) ? 'transparent' : (color ?? '#02CD86') + '22',
            color: (iconSvg || icon) ? undefined : (color ?? '#02CD86'),
        }"
        :aria-label="label"
    >
        <span
            v-if="iconSvg"
            class="inline-flex size-full items-center justify-center"
            v-html="iconSvg"
        />
        <template v-else>{{ icon || label.slice(0, 1) }}</template>
    </span>
</template>

<style scoped>
.asset-icon :deep(svg) {
    display: block;
    width: 100%;
    height: 100%;
    max-width: 100%;
    max-height: 100%;
}
</style>
