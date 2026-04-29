<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

const {
    title = '',
    description = '',
    caption = '',
} = defineProps<{
    title?: string;
    description?: string;
    caption?: string;
}>();
</script>

<template>
    <AuthLayout :title="title" :description="description" :caption="caption">
        <slot />
    </AuthLayout>
</template>
