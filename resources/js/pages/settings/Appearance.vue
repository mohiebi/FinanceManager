<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import { edit } from '@/routes/appearance';

const { t } = useI18n();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Appearance settings', href: edit() }],
    },
});
</script>

<template>
    <Head :title="t('settings.appearance.title')" />

    <h1 class="sr-only">{{ t('settings.appearance.title') }}</h1>

    <div class="space-y-5">
        <div>
            <p
                class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
            >
                {{ t('settings.appearance.title') }}
            </p>
            <p class="mt-1 text-sm text-[#989898]">
                {{ t('settings.appearance.description') }}
            </p>
        </div>

        <AppearanceTabs />
    </div>
</template>
