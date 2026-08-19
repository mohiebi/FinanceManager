<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { KeyRound, Laptop, ShieldCheck, Vault } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SettingsRow from '@/components/settings/SettingsRow.vue';
import SettingsSaveBar from '@/components/settings/SettingsSaveBar.vue';
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
import { useRelativeTime } from '@/composables/useRelativeTime';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { edit } from '@/routes/security';
import { destroy as destroySession } from '@/routes/security/sessions';
import { disable, enable } from '@/routes/two-factor';

type Session = {
    id: string;
    browser: string | null;
    platform: string | null;
    ip_address: string | null;
    last_active_at: string | null;
    is_current: boolean;
};

type Props = {
    canManageTwoFactor?: boolean;
    hasPassword?: boolean;
    needsPasswordConfirmation?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
    sessions?: Session[];
};

withDefaults(defineProps<Props>(), {
    canManageTwoFactor: false,
    hasPassword: false,
    needsPasswordConfirmation: false,
    requiresConfirmation: false,
    twoFactorEnabled: false,
    sessions: () => [],
});

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Security settings', href: edit() }],
    },
});

const { t } = useI18n();
const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const { formatRelativeTime } = useRelativeTime();
const showSetupModal = ref<boolean>(false);
const revokeTarget = ref<Session | null>(null);
const revokingSession = ref(false);

onUnmounted(() => clearTwoFactorAuthData());

function sessionLabel(session: Session): string {
    const browser =
        session.browser ?? t('settings.security.sessions.unknown_browser');
    const platform =
        session.platform ?? t('settings.security.sessions.unknown_platform');

    return `${browser} · ${platform}`;
}

function confirmRevokeSession(): void {
    if (!revokeTarget.value) {
        return;
    }

    revokingSession.value = true;

    router.delete(destroySession.url(revokeTarget.value.id), {
        preserveScroll: true,
        onFinish: () => {
            revokingSession.value = false;
            revokeTarget.value = null;
        },
    });
}
</script>

<template>
    <Head :title="t('settings.security.title')" />

    <div class="flex flex-col gap-[18px]">
        <SettingsSection
            :icon="KeyRound"
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
                        class="settings-input"
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
                        class="settings-input"
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
                        class="settings-input"
                    />
                    <InputError :message="errors.password_confirmation" />
                </SettingsRow>

                <div class="pt-5">
                    <SettingsSaveBar
                        :processing="processing"
                        :recently-successful="recentlySuccessful"
                        :label="
                            hasPassword
                                ? t('settings.security.save_password')
                                : t('settings.security.add_password')
                        "
                    />
                </div>
            </Form>
        </SettingsSection>

        <SettingsSection
            v-if="canManageTwoFactor"
            :icon="ShieldCheck"
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
                        class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl bg-[#02CD86] px-5 text-sm font-medium text-[#101010] transition-[filter] duration-200 hover:brightness-110 focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none"
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
                            class="inline-flex min-h-11 cursor-pointer items-center rounded-xl bg-[#02CD86] px-5 text-sm font-medium text-[#101010] transition-[filter,opacity] duration-200 hover:brightness-110 focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none disabled:opacity-50"
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
                        class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl bg-[#E94E50]/10 px-5 text-sm font-medium text-[#E94E50] transition-colors duration-200 hover:bg-[#E94E50]/20 focus-visible:ring-2 focus-visible:ring-[#E94E50] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] focus-visible:outline-none disabled:opacity-50"
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

        <SettingsSection
            :icon="Laptop"
            :title="t('settings.security.sessions.title')"
            :description="t('settings.security.sessions.description')"
        >
            <p v-if="sessions.length === 0" class="text-sm text-[#989898]">
                {{ t('settings.security.sessions.empty') }}
            </p>

            <ul v-else class="divide-y divide-white/5">
                <li
                    v-for="session in sessions"
                    :key="session.id"
                    class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#252525] text-[#989898]"
                        >
                            <Laptop class="size-[18px]" aria-hidden="true" />
                        </span>

                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium text-white">
                                    {{ sessionLabel(session) }}
                                </p>
                                <span
                                    v-if="session.is_current"
                                    class="rounded-full bg-[#02CD86]/10 px-2 py-0.5 text-xs font-medium text-[#02CD86]"
                                >
                                    {{
                                        t(
                                            'settings.security.sessions.this_device',
                                        )
                                    }}
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs text-[#989898]">
                                <span v-if="session.last_active_at">{{
                                    t(
                                        'settings.security.sessions.last_active',
                                        {
                                            when: formatRelativeTime(
                                                session.last_active_at,
                                            ),
                                        },
                                    )
                                }}</span>
                                <span v-if="session.ip_address">
                                    ·
                                    {{
                                        t(
                                            'settings.security.sessions.ip_address',
                                            { address: session.ip_address },
                                        )
                                    }}
                                </span>
                            </p>
                        </div>
                    </div>

                    <button
                        v-if="!session.is_current"
                        type="button"
                        class="shrink-0 rounded-lg px-3 py-1.5 text-sm font-medium text-[#E94E50] transition-colors duration-200 hover:bg-[#E94E50]/10"
                        @click="revokeTarget = session"
                    >
                        {{ t('settings.security.sessions.revoke') }}
                    </button>
                </li>
            </ul>
        </SettingsSection>

        <SettingsSection
            :icon="Vault"
            :title="t('settings.security.vault.title')"
            :description="t('settings.security.vault.description')"
        >
            <VaultSection :has-password="hasPassword" />
        </SettingsSection>
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
                        class="settings-input"
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

    <ConfirmDeleteModal
        :open="revokeTarget !== null"
        :title="t('settings.security.sessions.revoke_confirm_title')"
        :description="t('settings.security.sessions.revoke_confirm_body')"
        :processing="revokingSession"
        @update:open="(open) => !open && (revokeTarget = null)"
        @confirm="confirmRevokeSession"
    />
</template>
