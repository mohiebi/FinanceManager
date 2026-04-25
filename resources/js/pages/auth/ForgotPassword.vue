<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { login } from '@/routes';

defineOptions({
    layout: {
        title: 'Use email sign in',
        description: 'Password recovery now happens from the email-first sign-in flow.',
    },
});
</script>

<template>
    <Head title="Forgot password" />

    <div class="text-center text-sm text-muted-foreground">
        Return to
        <TextLink :href="login()">sign in</TextLink>
        and choose “send me a code instead”.
    </div>
</template>
