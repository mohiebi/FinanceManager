<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

defineOptions({
    layout: {
        title: 'Confirm your password',
        description:
            'This is a secure area of the application. Please confirm your password before continuing.',
    },
});

const formClass = 'auth-login-form mx-auto w-full max-w-[420px]';
const fieldClass = 'auth-field text-xs';
const labelClass = 'auth-label text-base font-normal';
const primaryButtonClass = 'auth-primary-button w-full text-base font-medium';
</script>

<template>
    <Head title="Confirm password" />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
        :class="formClass"
    >
        <div class="space-y-3">
            <div class="grid gap-2">
                <Label for="password" :class="labelClass">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    :class="fieldClass"
                    required
                    autocomplete="current-password"
                    autofocus
                />

                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center">
                <Button
                    :class="primaryButtonClass"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" />
                    Confirm password
                </Button>
            </div>
        </div>
    </Form>
</template>
