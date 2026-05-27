<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import logoGreen from '../../img/Logo-green.svg';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        :src="logoGreen"
        alt="CashPilot"
        class="object-contain"
        :class="className"
        v-bind="$attrs"
    />
</template>
