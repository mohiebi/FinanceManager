<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { login } from '@/routes';

defineOptions({
    layout: {
        title: 'Use recovery code',
        description: 'Password recovery now uses a one-time email code.',
    },
});
</script>

<template>
    <Head title="Reset password" />

    <div class="auth-copy-muted text-center text-base font-normal">
        Return to
        <TextLink :href="login()">sign in</TextLink>
        and request a recovery code.
    </div>
</template>
