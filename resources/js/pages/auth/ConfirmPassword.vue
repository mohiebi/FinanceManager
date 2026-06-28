<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

const { t } = useI18n();

defineOptions({ layout: { title: '', description: '' } });

watchEffect(() => {
    setLayoutProps({
        title: t('pages.confirm_password.title'),
        description: t('pages.confirm_password.description'),
    });
});

const formClass = 'auth-login-form mx-auto w-full max-w-[420px]';
const fieldClass = 'auth-field text-xs';
const labelClass = 'auth-label text-base font-normal';
const primaryButtonClass = 'auth-primary-button w-full text-base font-medium';
</script>

<template>
    <Head :title="$t('pages.confirm_password.title')" />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
        :class="formClass"
    >
        <div class="space-y-3">
            <div class="grid gap-2">
                <Label for="password" :class="labelClass">{{ $t('fields.password') }}</Label>
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
                    {{ $t('buttons.confirm_password') }}
                </Button>
            </div>
        </div>
    </Form>
</template>
