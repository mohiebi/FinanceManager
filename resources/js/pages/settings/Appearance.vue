<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Palette } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import SettingsSection from '@/components/settings/SettingsSection.vue';
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

    <SettingsSection
        :icon="Palette"
        :title="t('settings.appearance.title')"
        :description="t('settings.appearance.description')"
    >
        <AppearanceTabs />
    </SettingsSection>
</template>
