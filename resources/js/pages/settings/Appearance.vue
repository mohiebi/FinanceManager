<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Appearance settings', href: edit() }],
    },
});
</script>

<template>
    <Head title="Appearance settings" />

    <h1 class="sr-only">Appearance settings</h1>

    <div class="space-y-5">
        <div>
            <p
                class="text-xs font-medium tracking-[0.2em] uppercase text-[#989898]"
            >
                Appearance settings
            </p>
            <p class="mt-1 text-sm text-[#989898]">
                Update your account's appearance settings
            </p>
        </div>

        <AppearanceTabs />
    </div>
</template>
