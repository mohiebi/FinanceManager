<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { login } from '@/routes';

defineOptions({
    layout: {
        title: 'Verify your email',
        description:
            'Email verification now happens with a 6-digit code during signup.',
    },
});
</script>

<template>
    <Head title="Verify email" />

    <div class="auth-copy-muted text-center text-base font-normal">
        Return to
        <TextLink :href="login()">sign in</TextLink>
        to continue verification.
    </div>
</template>
