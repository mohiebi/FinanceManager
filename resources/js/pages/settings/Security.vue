<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { edit } from '@/routes/security';
import { disable, enable } from '@/routes/two-factor';

type Props = {
    canManageTwoFactor?: boolean;
    hasPassword?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

withDefaults(defineProps<Props>(), {
    canManageTwoFactor: false,
    hasPassword: false,
    requiresConfirmation: false,
    twoFactorEnabled: false,
});

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Security settings', href: edit() }],
    },
});

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

onUnmounted(() => clearTwoFactorAuthData());
</script>

<template>
    <Head title="Security settings" />

    <h1 class="sr-only">Security settings</h1>

    <div class="flex flex-col gap-8">
        <!-- Password section -->
        <div class="space-y-5">
            <div>
                <p
                    class="text-xs font-medium tracking-[0.2em] uppercase text-[#989898]"
                >
                    Password login
                </p>
                <p class="mt-1 text-sm text-[#989898]">
                    {{
                        hasPassword
                            ? 'Ensure your account is using a long, random password to stay secure'
                            : 'This account signs in with Google only right now. Add a password if you want email and password login too.'
                    }}
                </p>
            </div>

            <Form
                v-bind="SecurityController.update.form()"
                :options="{ preserveScroll: true }"
                reset-on-success
                :reset-on-error="['password', 'password_confirmation', 'current_password']"
                class="space-y-5"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <div v-if="hasPassword" class="grid gap-1.5">
                    <label
                        for="current_password"
                        class="text-xs font-medium tracking-[0.2em] uppercase text-[#989898]"
                        >Current password</label
                    >
                    <PasswordInput
                        id="current_password"
                        name="current_password"
                        autocomplete="current-password"
                        placeholder="Current password"
                        class="border-white/10 bg-[#252525] dark:bg-[#252525] text-white placeholder:text-[#686868] focus-visible:ring-1 focus-visible:ring-[#02cd86] focus-visible:border-[#02cd86]"
                    />
                    <InputError :message="errors.current_password" />
                </div>

                <div class="grid gap-1.5">
                    <label
                        for="password"
                        class="text-xs font-medium tracking-[0.2em] uppercase text-[#989898]"
                        >{{ hasPassword ? 'New password' : 'Password' }}</label
                    >
                    <PasswordInput
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        placeholder="New password"
                        class="border-white/10 bg-[#252525] dark:bg-[#252525] text-white placeholder:text-[#686868] focus-visible:ring-1 focus-visible:ring-[#02cd86] focus-visible:border-[#02cd86]"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-1.5">
                    <label
                        for="password_confirmation"
                        class="text-xs font-medium tracking-[0.2em] uppercase text-[#989898]"
                        >{{
                            hasPassword ? 'Confirm password' : 'Confirm your password'
                        }}</label
                    >
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        autocomplete="new-password"
                        placeholder="Confirm password"
                        class="border-white/10 bg-[#252525] dark:bg-[#252525] text-white placeholder:text-[#686868] focus-visible:ring-1 focus-visible:ring-[#02cd86] focus-visible:border-[#02cd86]"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <div class="flex items-center gap-4 pt-1">
                    <button
                        type="submit"
                        :disabled="processing"
                        data-test="update-password-button"
                        class="rounded-xl bg-[#02CD86] px-5 py-2.5 text-sm font-medium text-[#101010] transition hover:brightness-110 disabled:opacity-50"
                    >
                        {{ hasPassword ? 'Save password' : 'Add password' }}
                    </button>

                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p
                            v-show="recentlySuccessful"
                            class="text-sm text-[#02CD86]"
                        >
                            Saved.
                        </p>
                    </Transition>
                </div>
            </Form>
        </div>

        <!-- 2FA section -->
        <div v-if="canManageTwoFactor" class="space-y-5 border-t border-white/5 pt-8">
            <div>
                <p
                    class="text-xs font-medium tracking-[0.2em] uppercase text-[#989898]"
                >
                    Two-factor authentication
                </p>
                <p class="mt-1 text-sm text-[#989898]">
                    Manage your two-factor authentication settings
                </p>
            </div>

            <div
                v-if="!twoFactorEnabled"
                class="flex flex-col items-start gap-4"
            >
                <p class="text-sm text-[#989898]">
                    When you enable two-factor authentication, you will be
                    prompted for a secure pin during login. This pin can be
                    retrieved from a TOTP-supported application on your phone.
                </p>

                <div>
                    <button
                        v-if="hasSetupData"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-[#02CD86] px-5 py-2.5 text-sm font-medium text-[#101010] transition hover:brightness-110"
                        @click="showSetupModal = true"
                    >
                        <ShieldCheck class="size-4" />
                        Continue setup
                    </button>
                    <Form
                        v-else
                        v-bind="enable.form()"
                        @success="showSetupModal = true"
                        #default="{ processing }"
                    >
                        <button
                            type="submit"
                            :disabled="processing"
                            class="rounded-xl bg-[#02CD86] px-5 py-2.5 text-sm font-medium text-[#101010] transition hover:brightness-110 disabled:opacity-50"
                        >
                            Enable 2FA
                        </button>
                    </Form>
                </div>
            </div>

            <div v-else class="flex flex-col items-start gap-4">
                <p class="text-sm text-[#989898]">
                    You will be prompted for a secure, random pin during login,
                    which you can retrieve from the TOTP-supported application
                    on your phone.
                </p>

                <Form v-bind="disable.form()" #default="{ processing }">
                    <button
                        type="submit"
                        :disabled="processing"
                        class="inline-flex items-center gap-2 rounded-xl bg-[#E94E50]/10 px-5 py-2.5 text-sm font-medium text-[#E94E50] transition hover:bg-[#E94E50]/20 disabled:opacity-50"
                    >
                        Disable 2FA
                    </button>
                </Form>

                <TwoFactorRecoveryCodes />
            </div>

            <TwoFactorSetupModal
                v-model:isOpen="showSetupModal"
                :requiresConfirmation="requiresConfirmation"
                :twoFactorEnabled="twoFactorEnabled"
            />
        </div>
    </div>
</template>
