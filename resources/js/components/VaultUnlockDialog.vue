<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { useIdle } from '@vueuse/core';
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Checkbox } from '@/components/ui/checkbox';
import { useVault } from '@/composables/useVault';
import { TRUST_DAYS } from '@/lib/vault/keyStore';
import { parseRecoveryKey } from '@/lib/vault/recoveryKey';

/**
 * Blocks the app whenever the vault is armed, the key is not available, and the
 * current page actually needs it.
 *
 * Mounted at the layout, not wired into the login controllers: a session resumes
 * through six different paths (password, emailed code, signup completion, Google,
 * remember-me, a plain reload) and only a layout-level gate catches all of them.
 */
const { t } = useI18n();
const page = usePage();
const { unlocked, unlockWithPassphrase, unlockWithRecoveryKey, restore, lock } =
    useVault();

const passphrase = ref('');
const recoveryKey = ref('');
const usingRecovery = ref(false);
const trustDevice = ref(false);
const busy = ref(false);
const restoring = ref(true);
const error = ref('');

const armed = computed(() => page.props.vault?.armed === true);

/**
 * Pages that never render encrypted values, so there is nothing to unlock for.
 *
 * Asking for a key on the settings screens was the main reason the prompt felt
 * relentless — most of them have no ciphertext on them at all.
 */
const PLAINTEXT_PATHS = ['/settings', '/modules'];

const pageNeedsKey = computed(
    () => !PLAINTEXT_PATHS.some((path) => page.url.startsWith(path)),
);

const open = computed(
    () =>
        armed.value &&
        !unlocked.value &&
        !restoring.value &&
        pageNeedsKey.value,
);

// A trusted device should never flash the dialog on its way to being restored.
onMounted(async () => {
    try {
        await restore();
    } finally {
        restoring.value = false;
    }
});

// Locking on idle is what keeps "trust this device" from meaning "forever".
const { idle } = useIdle(30 * 60 * 1000);

watch(idle, (isIdle) => {
    if (isIdle && unlocked.value) {
        lock();
    }
});

async function submit(): Promise<void> {
    busy.value = true;
    error.value = '';

    try {
        if (usingRecovery.value) {
            const parsed = parseRecoveryKey(recoveryKey.value);

            if (parsed === null) {
                error.value = t('settings.security.vault.unlock_failed');

                return;
            }

            await unlockWithRecoveryKey(parsed, trustDevice.value);
        } else {
            await unlockWithPassphrase(passphrase.value, trustDevice.value);
        }

        passphrase.value = '';
        recoveryKey.value = '';
    } catch {
        // Deliberately the same message either way — never confirm which half of
        // a guess was right.
        error.value = t('settings.security.vault.unlock_failed');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm"
        >
            <div
                class="w-full max-w-sm rounded-[16px] bg-[#1a1a1a] p-6 shadow-[0_18px_45px_rgba(0,0,0,0.5)] ring-1 ring-white/10"
            >
                <p class="text-[17px] font-medium text-white">
                    {{ t('settings.security.vault.unlock_title') }}
                </p>
                <p class="mt-2 text-sm text-[#989898]">
                    {{ t('settings.security.vault.unlock_description') }}
                </p>

                <form class="mt-5 space-y-3" @submit.prevent="submit">
                    <!-- A real password field with a stable name, so a password
                         manager can offer to save and autofill it. The passphrase
                         belongs in one; refusing to let it be saved just pushed
                         people towards weaker ones. -->
                    <input
                        v-if="!usingRecovery"
                        v-model="passphrase"
                        type="password"
                        name="vault-passphrase"
                        autocomplete="current-password"
                        :placeholder="t('settings.security.vault.passphrase')"
                        class="w-full rounded-xl bg-white/5 px-3 py-2.5 text-sm text-white ring-1 ring-white/10 outline-none focus:ring-[#02CD86]"
                    />
                    <input
                        v-else
                        v-model="recoveryKey"
                        type="text"
                        autocomplete="off"
                        spellcheck="false"
                        :placeholder="t('settings.security.vault.recovery_key')"
                        class="w-full rounded-xl bg-white/5 px-3 py-2.5 font-mono text-sm text-white ring-1 ring-white/10 outline-none focus:ring-[#02CD86]"
                    />

                    <label class="flex cursor-pointer items-start gap-2">
                        <Checkbox
                            :checked="trustDevice"
                            class="mt-0.5"
                            @update:checked="trustDevice = $event === true"
                        />
                        <span class="text-xs text-[#989898]">
                            {{
                                t('settings.security.vault.trust_device', {
                                    days: TRUST_DAYS,
                                })
                            }}
                        </span>
                    </label>

                    <p v-if="error" class="text-sm text-[#E94E50]">
                        {{ error }}
                    </p>

                    <button
                        type="submit"
                        :disabled="busy"
                        class="w-full cursor-pointer rounded-xl bg-[#02CD86] py-2.5 text-sm font-medium text-[#101010] transition hover:bg-[#02b678] disabled:opacity-50"
                    >
                        {{ t('settings.security.vault.unlock') }}
                    </button>
                </form>

                <button
                    type="button"
                    class="mt-3 w-full cursor-pointer text-xs text-[#989898] underline-offset-2 hover:underline"
                    @click="usingRecovery = !usingRecovery"
                >
                    {{
                        usingRecovery
                            ? t('settings.security.vault.use_passphrase')
                            : t('settings.security.vault.use_recovery')
                    }}
                </button>

                <p
                    class="mt-4 border-t border-white/5 pt-3 text-xs text-[#6f6f6f]"
                >
                    {{
                        trustDevice
                            ? t('settings.security.vault.trust_device_note')
                            : t('settings.security.vault.reload_note')
                    }}
                </p>
            </div>
        </div>
    </Teleport>
</template>
