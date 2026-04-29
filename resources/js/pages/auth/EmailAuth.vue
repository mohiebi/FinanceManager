<script setup lang="ts">
import { Form, Head, setLayoutProps, usePage } from '@inertiajs/vue3';
import { computed, ref, watch, watchEffect } from 'vue';
import WebEmailAuthController from '@/actions/App/Http/Controllers/Auth/WebEmailAuthController';
import BirthdatePicker from '@/components/BirthdatePicker.vue';
import InputError from '@/components/InputError.vue';
import OtpCodeInput from '@/components/OtpCodeInput.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { redirect as googleRedirect } from '@/routes/auth/google';

type AuthStep =
    | 'email'
    | 'password'
    | 'signup_code'
    | 'complete_signup'
    | 'recovery_code';

type AuthFlow = {
    email?: string;
    status?: string;
    next_step?: AuthStep;
    signup_token?: string;
} | null;

const props = defineProps<{
    authFlow?: AuthFlow;
    status?: string;
}>();

const page = usePage<{ errors: Record<string, string> }>();

defineOptions({
    layout: {
        title: 'Welcome',
        description: 'Enter your email to access your account.',
        caption: 'Start with your email address',
    },
});

const step = ref<AuthStep>('email');
const email = ref('');
const signupToken = ref('');
const signupCode = ref('');
const recoveryCode = ref('');

const stepCopy = computed(() => {
    switch (step.value) {
        case 'password':
            return {
                title: 'Welcome back',
                description: 'Enter your password to access your account.',
            };
        case 'signup_code':
            return {
                title: 'Check your email',
                description: 'Enter the 6-digit code we sent you.',
            };
        case 'complete_signup':
            return {
                title: 'Finish your profile',
                description:
                    'Add the details you will use in your finance dashboard.',
            };
        case 'recovery_code':
            return {
                title: 'Check your email',
                description: 'Enter the 6-digit code we sent to your email.',
            };
        default:
            return {
                title: 'Welcome',
                description: 'Enter your email to access your account.',
            };
    }
});

watch(
    () => props.authFlow,
    (flow) => {
        if (!flow) {
            return;
        }

        if (flow.email) {
            email.value = flow.email;
        }

        if (flow.signup_token) {
            signupToken.value = flow.signup_token;
        }

        if (flow.next_step) {
            step.value = flow.next_step;
        }
    },
    { immediate: true },
);

watchEffect(() => {
    setLayoutProps({
        title: stepCopy.value.title,
        description: stepCopy.value.description,
        caption: 'Start with your email address',
    });
});

const returnToEmail = () => {
    step.value = 'email';
    email.value = '';
    signupToken.value = '';
    signupCode.value = '';
    recoveryCode.value = '';
};

const googleError = computed(() => page.props.errors.google);
const fieldClass = 'auth-field text-sm';
const linkClass =
    'auth-inline-link text-sm font-medium underline decoration-transparent underline-offset-4 transition hover:decoration-current';
</script>

<template>
    <Head :title="stepCopy.title" />

    <div class="flex flex-col gap-6">
        <div
            v-if="props.status"
            class="auth-status-success rounded-xl border px-4 py-3 text-sm"
        >
            {{ props.status }}
        </div>

        <div
            v-if="googleError"
            class="auth-status-error rounded-xl border px-4 py-3 text-sm"
        >
            {{ googleError }}
        </div>

        <Form
            v-if="step === 'email'"
            v-bind="WebEmailAuthController.start.form()"
            v-slot="{ errors, processing }"
            class="grid gap-4"
        >
            <div class="grid gap-2">
                <Label for="email" class="auth-label text-sm font-medium"
                    >Email</Label
                >
                <Input
                    id="email"
                    v-model="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="Enter your email"
                    :class="fieldClass"
                    :aria-invalid="errors.email ? 'true' : undefined"
                />
                <InputError :message="errors.email" />
            </div>

            <Button
                type="submit"
                class="auth-primary-button w-full text-sm font-medium"
                :disabled="processing"
            >
                <Spinner v-if="processing" />
                Sign In
            </Button>

            <Button
                as-child
                variant="outline"
                class="auth-secondary-button w-full text-sm font-medium"
            >
                <a
                    :href="googleRedirect.url()"
                    class="inline-flex items-center gap-3"
                >
                    <svg viewBox="0 0 24 24" class="h-4 w-4" aria-hidden="true">
                        <path
                            fill="#EA4335"
                            d="M12 10.2v3.9h5.5c-.2 1.2-.9 2.2-1.9 2.9l3.1 2.4c1.8-1.7 2.8-4.1 2.8-7 0-.7-.1-1.4-.2-2.1H12Z"
                        />
                        <path
                            fill="#34A853"
                            d="M12 21c2.6 0 4.8-.9 6.4-2.5l-3.1-2.4c-.9.6-2 .9-3.3.9-2.5 0-4.6-1.7-5.4-4H3.4v2.5A9.7 9.7 0 0 0 12 21Z"
                        />
                        <path
                            fill="#4A90E2"
                            d="M6.6 13c-.2-.6-.3-1.3-.3-2s.1-1.4.3-2V6.5H3.4A9.7 9.7 0 0 0 2.3 11c0 1.6.4 3.1 1.1 4.5L6.6 13Z"
                        />
                        <path
                            fill="#FBBC05"
                            d="M12 5.1c1.4 0 2.6.5 3.6 1.4l2.7-2.7C16.8 2.3 14.6 1.4 12 1.4A9.7 9.7 0 0 0 3.4 6.5L6.6 9c.8-2.3 3-3.9 5.4-3.9Z"
                        />
                    </svg>
                    <span>Sign In with Google</span>
                </a>
            </Button>
        </Form>

        <Form
            v-else-if="step === 'password'"
            v-bind="WebEmailAuthController.login.form()"
            v-slot="{ errors, processing }"
            class="grid gap-4"
        >
            <input type="hidden" name="email" :value="email" />

            <div class="grid gap-2">
                <Label for="password" class="auth-label text-sm font-medium">
                    Password
                </Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autofocus
                    autocomplete="current-password"
                    placeholder="Enter your Password"
                    :class="fieldClass"
                    :aria-invalid="
                        errors.email || errors.password ? 'true' : undefined
                    "
                />
                <InputError :message="errors.email || errors.password" />
            </div>

            <Button
                type="submit"
                class="auth-primary-button w-full text-sm font-medium"
                :disabled="processing"
            >
                <Spinner v-if="processing" />
                Submit
            </Button>
        </Form>

        <Form
            v-if="step === 'password'"
            v-bind="WebEmailAuthController.sendRecovery.form()"
            v-slot="{ errors, processing }"
            class="-mt-2"
        >
            <input type="hidden" name="email" :value="email" />
            <InputError :message="errors.email" />
            <div class="flex items-center justify-between gap-3">
                <Button
                    type="submit"
                    variant="link"
                    size="sm"
                    class="auth-inline-link px-0 text-sm font-medium"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" />
                    Forgot password?
                </Button>
                <button type="button" :class="linkClass" @click="returnToEmail">
                    Use another email
                </button>
            </div>
        </Form>

        <Form
            v-else-if="step === 'signup_code'"
            v-bind="WebEmailAuthController.verifySignup.form()"
            v-slot="{ errors, processing }"
            class="grid gap-4"
            @error="signupCode = ''"
        >
            <input type="hidden" name="email" :value="email" />
            <input type="hidden" name="code" :value="signupCode" />

            <div class="grid gap-2">
                <Label for="signup-code" class="auth-label text-sm font-medium">
                    Verification code
                </Label>
                <div class="flex justify-center pt-1">
                    <OtpCodeInput
                        v-model="signupCode"
                        :invalid="Boolean(errors.code)"
                        :disabled="processing"
                    />
                </div>
                <InputError :message="errors.code" />
            </div>

            <div class="grid gap-3">
                <Button
                    type="submit"
                    class="auth-primary-button w-full text-sm font-medium"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" />
                    Verify email
                </Button>

                <Button
                    type="button"
                    variant="ghost"
                    class="auth-inline-link text-sm font-medium hover:bg-transparent"
                    @click="returnToEmail"
                >
                    Use another email
                </Button>
            </div>
        </Form>

        <Form
            v-else-if="step === 'complete_signup'"
            v-bind="WebEmailAuthController.completeSignup.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="grid gap-4"
        >
            <input type="hidden" name="signup_token" :value="signupToken" />
            <input type="hidden" name="email" :value="email" />

            <div class="grid gap-2">
                <Label for="name" class="auth-label text-sm font-medium"
                    >Name</Label
                >
                <Input
                    id="name"
                    type="text"
                    name="name"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Enter your Name"
                    :class="fieldClass"
                    :aria-invalid="errors.name ? 'true' : undefined"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="birthdate" class="auth-label text-sm font-medium">
                    Birthdate
                </Label>
                <BirthdatePicker name="birthdate" :trigger-class="fieldClass" />
                <InputError :message="errors.birthdate" />
            </div>

            <div class="grid gap-2">
                <Label
                    for="new-password"
                    class="auth-label text-sm font-medium"
                >
                    Password
                </Label>
                <PasswordInput
                    id="new-password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="Enter your Password"
                    :class="fieldClass"
                    :aria-invalid="errors.password ? 'true' : undefined"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label
                    for="password-confirmation"
                    class="auth-label text-sm font-medium"
                >
                    Confirm password
                </Label>
                <PasswordInput
                    id="password-confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Repeat your Password"
                    :class="fieldClass"
                />
            </div>

            <InputError :message="errors.signup_token" />

            <Button
                type="submit"
                class="auth-primary-button w-full text-sm font-medium"
                :disabled="processing"
            >
                <Spinner v-if="processing" />
                Create account
            </Button>
        </Form>

        <Form
            v-else-if="step === 'recovery_code'"
            v-bind="WebEmailAuthController.verifyRecovery.form()"
            v-slot="{ errors, processing }"
            class="grid gap-4"
            @error="recoveryCode = ''"
        >
            <input type="hidden" name="email" :value="email" />
            <input type="hidden" name="code" :value="recoveryCode" />

            <div class="grid gap-2">
                <Label
                    for="recovery-code"
                    class="auth-label text-sm font-medium"
                >
                    Verification code
                </Label>
                <div class="flex justify-center pt-1">
                    <OtpCodeInput
                        v-model="recoveryCode"
                        :invalid="Boolean(errors.code)"
                        :disabled="processing"
                    />
                </div>
                <InputError :message="errors.code" />
            </div>

            <div class="grid gap-3">
                <Button
                    type="submit"
                    class="auth-primary-button w-full text-sm font-medium"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" />
                    Verify email
                </Button>

                <Button
                    type="button"
                    variant="ghost"
                    class="auth-inline-link text-sm font-medium hover:bg-transparent"
                    @click="returnToEmail"
                >
                    Use another email
                </Button>
            </div>
        </Form>
    </div>
</template>
