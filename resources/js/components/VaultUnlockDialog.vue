<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useVault } from '@/composables/useVault';
import { parseRecoveryKey } from '@/lib/vault/recoveryKey';

/**
 * Blocks the app whenever the vault is armed and the key is not in memory.
 *
 * Mounted at the layout, not wired into the login controllers: a user resumes an
 * authenticated session through six different paths (password, emailed code,
 * signup completion, Google, remember-me, a plain reload) and only a layout-level
 * gate catches all of them.
 */
const { t } = useI18n();
const page = usePage();
const { unlocked, unlockWithPassphrase, unlockWithRecoveryKey } = useVault();

const passphrase = ref('');
const recoveryKey = ref('');
const usingRecovery = ref(false);
const busy = ref(false);
const error = ref('');

const armed = computed(() => page.props.vault?.armed === true);
const open = computed(() => armed.value && !unlocked.value);

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

            await unlockWithRecoveryKey(parsed);
        } else {
            await unlockWithPassphrase(passphrase.value);
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
                class="w-full max-w-sm rounded-[22px] bg-[#1a1a1a] p-6 shadow-[0_18px_45px_rgba(0,0,0,0.5)] ring-1 ring-white/10"
            >
                <p class="text-[17px] font-medium text-white">
                    {{ t('settings.security.vault.unlock_title') }}
                </p>
                <p class="mt-2 text-sm text-[#989898]">
                    {{ t('settings.security.vault.unlock_description') }}
                </p>

                <form class="mt-5 space-y-3" @submit.prevent="submit">
                    <input
                        v-if="!usingRecovery"
                        v-model="passphrase"
                        type="password"
                        autocomplete="off"
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
                    {{ t('settings.security.vault.reload_note') }}
                </p>
            </div>
        </div>
    </Teleport>
</template>
