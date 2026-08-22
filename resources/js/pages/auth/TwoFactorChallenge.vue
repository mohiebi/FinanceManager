<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

const { t } = useI18n();

defineOptions({ layout: { title: '', description: '' } });

const showRecoveryInput = ref<boolean>(false);

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: t('pages.two_factor.recovery_code.title'),
            description: t('pages.two_factor.recovery_code.description'),
            buttonText: t('buttons.login_using_auth_code'),
        };
    }

    return {
        title: t('pages.two_factor.auth_code.title'),
        description: t('pages.two_factor.auth_code.description'),
        buttonText: t('buttons.login_using_recovery_code'),
    };
});

watchEffect(() => {
    setLayoutProps({
        title: authConfigContent.value.title,
        description: authConfigContent.value.description,
    });
});

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};

const code = ref<string>('');
const formClass = 'auth-login-form mx-auto w-full max-w-[420px] space-y-3';
const fieldClass = 'auth-field text-xs';
const primaryButtonClass = 'auth-primary-button w-full text-base font-medium';
const helperTextClass = 'auth-copy-muted text-center text-base font-normal';
const inlineButtonClass =
    'auth-inline-link underline decoration-transparent underline-offset-4 transition hover:decoration-current';
</script>

<template>
    <Head :title="$t('pages.two_factor.auth_code.title')" />

    <div class="space-y-6">
        <template v-if="!showRecoveryInput">
            <Form
                v-bind="store.form()"
                :options="{ preserveState: false }"
                :class="formClass"
                reset-on-error
                @error="code = ''"
                #default="{ errors, processing, clearErrors }"
            >
                <input type="hidden" name="code" :value="code" />
                <div
                    class="flex flex-col items-center justify-center space-y-3 text-center"
                >
                    <div class="flex w-full items-center justify-center">
                        <InputOTP
                            id="otp"
                            v-model="code"
                            :maxlength="6"
                            :disabled="processing"
                            autofocus
                        >
                            <InputOTPGroup>
                                <InputOTPSlot
                                    v-for="index in 6"
                                    :key="index"
                                    :index="index - 1"
                                />
                            </InputOTPGroup>
                        </InputOTP>
                    </div>
                    <InputError :message="errors.code" />
                </div>
                <Button
                    type="submit"
                    :class="primaryButtonClass"
                    :disabled="processing"
                >
                    {{ $t('buttons.continue') }}
                </Button>
                <div :class="helperTextClass">
                    <span>{{ $t('pages.or_you_can') }} </span>
                    <button
                        type="button"
                        :class="inlineButtonClass"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>

        <template v-else>
            <Form
                v-bind="store.form()"
                :options="{ preserveState: false }"
                :class="formClass"
                reset-on-error
                #default="{ errors, processing, clearErrors }"
            >
                <Input
                    name="recovery_code"
                    type="text"
                    :placeholder="$t('fields.enter_recovery_code')"
                    :autofocus="showRecoveryInput"
                    :class="fieldClass"
                    required
                />
                <InputError :message="errors.recovery_code" />
                <Button
                    type="submit"
                    :class="primaryButtonClass"
                    :disabled="processing"
                >
                    {{ $t('buttons.continue') }}
                </Button>

                <div :class="helperTextClass">
                    <span>{{ $t('pages.or_you_can') }} </span>
                    <button
                        type="button"
                        :class="inlineButtonClass"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>
    </div>
</template>
