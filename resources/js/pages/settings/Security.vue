<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SettingsRow from '@/components/settings/SettingsRow.vue';
import SettingsSection from '@/components/settings/SettingsSection.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import VaultSection from '@/components/VaultSection.vue';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { edit } from '@/routes/security';
import { disable, enable } from '@/routes/two-factor';

type Props = {
    canManageTwoFactor?: boolean;
    hasPassword?: boolean;
    needsPasswordConfirmation?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

withDefaults(defineProps<Props>(), {
    canManageTwoFactor: false,
    hasPassword: false,
    needsPasswordConfirmation: false,
    requiresConfirmation: false,
    twoFactorEnabled: false,
});

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Security settings', href: edit() }],
    },
});

const { t } = useI18n();
const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

onUnmounted(() => clearTwoFactorAuthData());
</script>

<template>
    <Head :title="t('settings.security.title')" />

    <h1 class="sr-only">{{ t('settings.security.title') }}</h1>

    <div class="flex flex-col gap-[18px]">
        <SettingsSection
            :title="t('settings.security.password_login')"
            :description="
                hasPassword
                    ? t('settings.security.password_login_description')
                    : t('settings.security.password_login_description_oauth')
            "
        >
            <Form
                v-bind="SecurityController.update.form()"
                :options="{ preserveScroll: true }"
                reset-on-success
                :reset-on-error="[
                    'password',
                    'password_confirmation',
                    'current_password',
                ]"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <SettingsRow
                    v-if="hasPassword"
                    :label="t('settings.security.current_password')"
                    control-id="current_password"
                >
                    <PasswordInput
                        id="current_password"
                        name="current_password"
                        autocomplete="current-password"
                        :placeholder="t('settings.security.current_password')"
                        class="border-white/10 bg-[#252525] text-white placeholder:text-[#686868] focus-visible:border-[#02cd86] focus-visible:ring-1 focus-visible:ring-[#02cd86] dark:bg-[#252525]"
                    />
                    <InputError :message="errors.current_password" />
                </SettingsRow>

                <SettingsRow
                    :label="
                        hasPassword
                            ? t('settings.security.new_password')
                            : t('fields.password')
                    "
                    control-id="password"
                >
                    <PasswordInput
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        :placeholder="t('settings.security.new_password')"
                        class="border-white/10 bg-[#252525] text-white placeholder:text-[#686868] focus-visible:border-[#02cd86] focus-visible:ring-1 focus-visible:ring-[#02cd86] dark:bg-[#252525]"
                    />
                    <InputError :message="errors.password" />
                </SettingsRow>

                <SettingsRow
                    :label="
                        hasPassword
                            ? t('settings.security.confirm_password')
                            : t('settings.security.confirm_your_password')
                    "
                    control-id="password_confirmation"
                    last
                >
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        autocomplete="new-password"
                        :placeholder="t('settings.security.confirm_password')"
                        class="border-white/10 bg-[#252525] text-white placeholder:text-[#686868] focus-visible:border-[#02cd86] focus-visible:ring-1 focus-visible:ring-[#02cd86] dark:bg-[#252525]"
                    />
                    <InputError :message="errors.password_confirmation" />
                </SettingsRow>

                <div class="flex items-center gap-4 pt-5">
                    <button
                        type="submit"
                        :disabled="processing"
                        data-test="update-password-button"
                        class="cursor-pointer rounded-xl bg-[#02CD86] px-5 py-2.5 text-sm font-medium text-[#101010] transition-[filter,opacity] duration-200 hover:brightness-110 focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none disabled:opacity-50"
                    >
                        {{
                            hasPassword
                                ? t('settings.security.save_password')
                                : t('settings.security.add_password')
                        }}
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
                            {{ t('common.saved') }}
                        </p>
                    </Transition>
                </div>
            </Form>
        </SettingsSection>

        <SettingsSection
            v-if="canManageTwoFactor"
            :title="t('settings.security.two_factor')"
            :description="t('settings.security.two_factor_description')"
        >
            <div
                v-if="!twoFactorEnabled"
                class="flex flex-col items-start gap-4"
            >
                <p class="text-sm text-[#989898]">
                    {{ t('settings.security.two_factor_enable_description') }}
                </p>

                <div>
                    <button
                        v-if="hasSetupData"
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-[#02CD86] px-5 py-2.5 text-sm font-medium text-[#101010] transition-[filter] duration-200 hover:brightness-110 focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none"
                        @click="showSetupModal = true"
                    >
                        <ShieldCheck class="size-4" />
                        {{ t('settings.security.continue_setup') }}
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
                            class="cursor-pointer rounded-xl bg-[#02CD86] px-5 py-2.5 text-sm font-medium text-[#101010] transition-[filter,opacity] duration-200 hover:brightness-110 focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none disabled:opacity-50"
                        >
                            {{ t('settings.security.enable_2fa') }}
                        </button>
                    </Form>
                </div>
            </div>

            <div v-else class="flex flex-col items-start gap-4">
                <p class="text-sm text-[#989898]">
                    {{ t('settings.security.two_factor_enabled_description') }}
                </p>

                <Form v-bind="disable.form()" #default="{ processing }">
                    <button
                        type="submit"
                        :disabled="processing"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-[#E94E50]/10 px-5 py-2.5 text-sm font-medium text-[#E94E50] transition-colors duration-200 hover:bg-[#E94E50]/20 focus-visible:ring-2 focus-visible:ring-[#E94E50] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none disabled:opacity-50"
                    >
                        {{ t('settings.security.disable_2fa') }}
                    </button>
                </Form>

                <TwoFactorRecoveryCodes />
            </div>

            <TwoFactorSetupModal
                v-model:isOpen="showSetupModal"
                :requiresConfirmation="requiresConfirmation"
                :twoFactorEnabled="twoFactorEnabled"
            />
        </SettingsSection>

        <!-- VaultSection renders its own icon-led title internally, so it goes
             straight into the card surface rather than a SettingsSection —
             wrapping it there would print the heading twice. -->
        <div class="settings-card">
            <VaultSection :has-password="hasPassword" />
        </div>
    </div>

    <Dialog :open="needsPasswordConfirmation">
        <DialogContent
            :show-close-button="false"
            class="sm:max-w-md"
            @escape-key-down="(event) => event.preventDefault()"
            @pointer-down-outside="(event) => event.preventDefault()"
            @interact-outside="(event) => event.preventDefault()"
        >
            <Form
                v-bind="SecurityController.confirmPassword.form()"
                reset-on-success
                :reset-on-error="['password']"
                v-slot="{ errors, processing }"
            >
                <DialogHeader class="space-y-3">
                    <DialogTitle>{{
                        t('settings.security.confirm_password_title')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t('settings.security.confirm_password_description')
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="mt-4 grid gap-1.5">
                    <label
                        for="confirm_password_modal"
                        class="text-xs font-medium tracking-[0.2em] text-[#989898] uppercase"
                        >{{ t('fields.password') }}</label
                    >
                    <PasswordInput
                        id="confirm_password_modal"
                        name="password"
                        autocomplete="current-password"
                        autofocus
                        :placeholder="t('fields.password')"
                        class="border-white/10 bg-[#252525] text-white placeholder:text-[#686868] focus-visible:border-[#02cd86] focus-visible:ring-1 focus-visible:ring-[#02cd86] dark:bg-[#252525]"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="mt-6 flex justify-end">
                    <Button type="submit" :disabled="processing">
                        {{ t('settings.security.confirm_password_button') }}
                    </Button>
                </div>
            </Form>
        </DialogContent>
    </Dialog>
</template>
