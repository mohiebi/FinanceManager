<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import WebEmailAuthController from '@/actions/App/Http/Controllers/Auth/WebEmailAuthController';
import BirthdatePicker from '@/components/BirthdatePicker.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
        title: 'Sign in or create an account',
        description: 'Start with your email address',
    },
});

const step = ref<AuthStep>('email');
const email = ref('');
const signupToken = ref('');

const stepCopy = computed(() => {
    switch (step.value) {
        case 'password':
            return {
                title: 'Welcome back',
                description: 'Enter your password to continue.',
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
                title: 'Enter your recovery code',
                description: 'Use the 6-digit code we sent to your email.',
            };
        default:
            return {
                title: 'Continue with email',
                description: 'We will send a code if this email is new.',
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

const returnToEmail = () => {
    step.value = 'email';
    signupToken.value = '';
};

const googleError = computed(() => page.props.errors.google);
</script>

<template>
    <Head title="Sign in" />

    <div class="flex flex-col gap-6">
        <div class="space-y-1 text-center">
            <h2 class="text-lg font-medium tracking-normal">
                {{ stepCopy.title }}
            </h2>
            <p class="text-sm text-muted-foreground">
                {{ stepCopy.description }}
            </p>
        </div>

        <div
            v-if="props.status"
            class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"
        >
            {{ props.status }}
        </div>

        <div
            v-if="googleError"
            class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
        >
            {{ googleError }}
        </div>

        <div v-if="step === 'email' || step === 'password'" class="grid gap-3">
            <Button as-child variant="outline" class="w-full">
                <a :href="googleRedirect.url()"> Continue with Google </a>
            </Button>
            <div
                class="relative text-center text-xs text-muted-foreground uppercase"
            >
                <span class="bg-background px-2">Or continue with email</span>
            </div>
        </div>

        <Form
            v-if="step === 'email'"
            v-bind="WebEmailAuthController.start.form()"
            v-slot="{ errors, processing }"
            class="grid gap-5"
        >
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    v-model="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <Button type="submit" class="w-full" :disabled="processing">
                <Spinner v-if="processing" />
                Continue
            </Button>
        </Form>

        <Form
            v-else-if="step === 'password'"
            v-bind="WebEmailAuthController.login.form()"
            v-slot="{ errors, processing }"
            class="grid gap-5"
        >
            <input type="hidden" name="email" :value="email" />

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autofocus
                    autocomplete="current-password"
                    placeholder="Password"
                />
                <InputError :message="errors.email || errors.password" />
            </div>

            <div class="flex items-center justify-between gap-3">
                <Label for="remember" class="flex items-center gap-2 text-sm">
                    <Checkbox id="remember" name="remember" />
                    <span>Remember me</span>
                </Label>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="returnToEmail"
                >
                    Use another email
                </Button>
            </div>

            <Button type="submit" class="w-full" :disabled="processing">
                <Spinner v-if="processing" />
                Log in
            </Button>
        </Form>

        <Form
            v-if="step === 'password'"
            v-bind="WebEmailAuthController.sendRecovery.form()"
            v-slot="{ errors, processing }"
            class="-mt-4 text-center"
        >
            <input type="hidden" name="email" :value="email" />
            <InputError :message="errors.email" />
            <Button
                type="submit"
                variant="link"
                size="sm"
                :disabled="processing"
            >
                <Spinner v-if="processing" />
                Send me a code instead
            </Button>
        </Form>

        <Form
            v-else-if="step === 'signup_code'"
            v-bind="WebEmailAuthController.verifySignup.form()"
            v-slot="{ errors, processing }"
            class="grid gap-5"
        >
            <input type="hidden" name="email" :value="email" />

            <div class="grid gap-2">
                <Label for="signup-code">Verification code</Label>
                <Input
                    id="signup-code"
                    type="text"
                    name="code"
                    required
                    autofocus
                    inputmode="numeric"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    autocomplete="one-time-code"
                    placeholder="123456"
                />
                <InputError :message="errors.code" />
            </div>

            <div class="grid gap-3">
                <Button type="submit" class="w-full" :disabled="processing">
                    <Spinner v-if="processing" />
                    Verify email
                </Button>
                <Button type="button" variant="ghost" @click="returnToEmail">
                    Use another email
                </Button>
            </div>
        </Form>

        <Form
            v-else-if="step === 'complete_signup'"
            v-bind="WebEmailAuthController.completeSignup.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="grid gap-5"
        >
            <input type="hidden" name="signup_token" :value="signupToken" />
            <input type="hidden" name="email" :value="email" />

            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    type="text"
                    name="name"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Full name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="birthdate">Birthdate</Label>
                <BirthdatePicker name="birthdate" />
                <InputError :message="errors.birthdate" />
            </div>

            <div class="grid gap-2">
                <Label for="new-password">Password</Label>
                <PasswordInput
                    id="new-password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password-confirmation">Confirm password</Label>
                <PasswordInput
                    id="password-confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Confirm password"
                />
            </div>

            <InputError :message="errors.signup_token" />

            <Button type="submit" class="w-full" :disabled="processing">
                <Spinner v-if="processing" />
                Create account
            </Button>
        </Form>

        <Form
            v-else-if="step === 'recovery_code'"
            v-bind="WebEmailAuthController.verifyRecovery.form()"
            v-slot="{ errors, processing }"
            class="grid gap-5"
        >
            <input type="hidden" name="email" :value="email" />

            <div class="grid gap-2">
                <Label for="recovery-code">Recovery code</Label>
                <Input
                    id="recovery-code"
                    type="text"
                    name="code"
                    required
                    autofocus
                    inputmode="numeric"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    autocomplete="one-time-code"
                    placeholder="123456"
                />
                <InputError :message="errors.code" />
            </div>

            <div class="grid gap-3">
                <Button type="submit" class="w-full" :disabled="processing">
                    <Spinner v-if="processing" />
                    Log in with code
                </Button>
                <Button type="button" variant="ghost" @click="returnToEmail">
                    Use another email
                </Button>
            </div>
        </Form>
    </div>
</template>
