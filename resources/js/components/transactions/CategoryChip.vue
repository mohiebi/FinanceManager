<script setup lang="ts">
/**
 * The mock's transaction-row category tag: coloured text on a fixed dark
 * chip, never a coloured background — the colour is `category.color`, with
 * a neutral grey for uncategorised rows or categories nobody has coloured.
 */
defineProps<{
    name: string;
    color?: string | null;
}>();
</script>

<template>
    <span
        class="w-fit rounded-[7px] bg-[#252525] px-[9px] py-1 text-[11.5px] whitespace-nowrap"
        :style="{ color: color ?? '#686868' }"
    >
        {{ name }}
    </span>
</template>
