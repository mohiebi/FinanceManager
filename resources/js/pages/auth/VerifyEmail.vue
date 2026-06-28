<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import TextLink from '@/components/TextLink.vue';
import { login } from '@/routes';

const { t } = useI18n();

defineOptions({ layout: { title: '', description: '' } });

watchEffect(() => {
    setLayoutProps({
        title: t('pages.verify_email.title'),
        description: t('pages.verify_email.description'),
    });
});
</script>

<template>
    <Head :title="$t('pages.verify_email.title')" />

    <div class="auth-copy-muted text-center text-base font-normal">
        {{ $t('pages.return_to_sign_in') }}
        <TextLink :href="login()">{{ $t('buttons.sign_in_link') }}</TextLink>
        {{ $t('pages.continue_verification') }}
    </div>
</template>
