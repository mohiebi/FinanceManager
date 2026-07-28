<script setup lang="ts">
import VaultUnlockDialog from '@/components/VaultUnlockDialog.vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>

    <!-- Layout-level on purpose: it has to catch every way a session resumes,
         including a plain reload, not just the login controllers. -->
    <VaultUnlockDialog />
</template>
