<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Bot,
    Check,
    Eye,
    FileSpreadsheet,
    KeyRound,
    Lock,
    ShieldCheck,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Checkbox } from '@/components/ui/checkbox';
import { useVault } from '@/composables/useVault';
import { aadFor, base64ToBytes, encrypt } from '@/lib/vault/crypto';
import {
    DEFAULT_ITERATIONS,
    DEFAULT_KDF,
    derivePassphraseKek,
    deriveRecoveryKek,
} from '@/lib/vault/kdf';
import {
    formatRecoveryKey,
    generatePassphrase,
    generateRecoveryKey,
    parseRecoveryKey,
} from '@/lib/vault/recoveryKey';
import { disable as disableVault, enable as enableVault } from '@/routes/vault';

const props = defineProps<{ hasPassword: boolean }>();

const { t } = useI18n();
const page = usePage();
const { exportKeyWithPassphrase, exportKeyWithRecoveryKey, lock } = useVault();

const armed = computed(() => page.props.vault?.armed === true);

type Step = 'idle' | 'tradeoff' | 'passphrase' | 'recovery' | 'disable';

const step = ref<Step>('idle');
const busy = ref(false);
const error = ref('');

const passphrase = ref('');
const recoveryBytes = ref<Uint8Array<ArrayBuffer> | null>(null);
const recoveryDisplay = ref('');
const recoveryGroupIndex = ref(0);
const recoveryEcho = ref('');
const acknowledged = ref(false);

const disableWord = ref('');
const disableUnderstood = ref(false);
const disablePassphrase = ref('');
const disableRecoveryKey = ref('');

/**
 * Which secret is being used to unwind the vault.
 *
 * Both open it for reading, so both have to be accepted here — offering only the
 * passphrase left anyone who had lost theirs armed forever, recovery key in hand.
 */
const disableUsingRecovery = ref(false);

/** Wrapped blobs, held between the passphrase and recovery steps. */
const wrapped = ref<{
    passphrase: string;
    recovery: string;
    salt: string;
    recoverySalt: string;
    fingerprint: string;
} | null>(null);

const confirmWord = computed(() =>
    t('settings.security.vault.confirm_word_value'),
);

const recoveryGroups = computed(() => recoveryDisplay.value.split('-'));

const recoveryMatches = computed(() => {
    const expected = recoveryGroups.value[recoveryGroupIndex.value];

    // Guarded rather than compared against a fallback, so an empty box can never
    // count as a match.
    return (
        expected !== undefined &&
        expected !== '' &&
        recoveryEcho.value.trim().toUpperCase() === expected
    );
});

const canFinish = computed(() => acknowledged.value && recoveryMatches.value);

const disableSecretProvided = computed(() =>
    disableUsingRecovery.value
        ? disableRecoveryKey.value.trim().length > 0
        : disablePassphrase.value.length > 0,
);

const canDisable = computed(
    () =>
        disableUnderstood.value &&
        disableWord.value.trim().toUpperCase() ===
            confirmWord.value.toUpperCase() &&
        disableSecretProvided.value,
);

/** What actually stops working once the vault is armed. */
const enableLosses = [
    { key: 'telegram', icon: Bot },
    { key: 'ai', icon: Sparkles },
    { key: 'spreadsheets', icon: FileSpreadsheet },
    { key: 'recovery', icon: KeyRound },
] as const;

/** What comes back — and what it costs — when it is switched off again. */
const disableEffects = [
    { key: 'readable', icon: Eye, tone: 'warn' },
    { key: 'telegram', icon: Bot, tone: 'gain' },
    { key: 'ai', icon: Sparkles, tone: 'gain' },
    { key: 'spreadsheets', icon: FileSpreadsheet, tone: 'gain' },
] as const;

function reset(): void {
    step.value = 'idle';
    error.value = '';
    passphrase.value = '';
    recoveryBytes.value = null;
    recoveryDisplay.value = '';
    recoveryEcho.value = '';
    acknowledged.value = false;
    wrapped.value = null;
    disableWord.value = '';
    disableUnderstood.value = false;
    disablePassphrase.value = '';
    disableRecoveryKey.value = '';
    disableUsingRecovery.value = false;
}

function csrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]!) : '';
}

/**
 * Fetch the data key, wrap it under the passphrase and a fresh recovery key, and
 * hold both blobs until the user has acknowledged the recovery key.
 *
 * Nothing is sent back to the server in this step — the point of no return is the
 * *next* one.
 */
async function prepareWrapping(): Promise<void> {
    busy.value = true;
    error.value = '';

    try {
        const response = await fetch('/settings/vault/enroll', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
            },
        });

        if (!response.ok) {
            error.value = t('settings.security.vault.unlock_failed');

            return;
        }

        const dek: string = (await response.json()).dek;

        const salt = crypto.getRandomValues(new Uint8Array(16));
        const recoverySalt = crypto.getRandomValues(new Uint8Array(16));
        const recovery = generateRecoveryKey();

        const passphraseKek = await derivePassphraseKek(passphrase.value, salt);
        const recoveryKek = await deriveRecoveryKek(recovery, recoverySalt);
        const aad = aadFor('vault', 'dek');

        wrapped.value = {
            passphrase: await encrypt(dek, passphraseKek, aad),
            recovery: await encrypt(dek, recoveryKek, aad),
            salt: btoa(String.fromCharCode(...salt)),
            recoverySalt: btoa(String.fromCharCode(...recoverySalt)),
            fingerprint: await fingerprint(base64ToBytes(dek)),
        };

        recoveryBytes.value = recovery;
        recoveryDisplay.value = formatRecoveryKey(recovery);
        recoveryGroupIndex.value = Math.floor(
            Math.random() * recoveryDisplay.value.split('-').length,
        );
        step.value = 'recovery';
    } finally {
        busy.value = false;
    }
}

async function fingerprint(raw: Uint8Array<ArrayBuffer>): Promise<string> {
    const digest = new Uint8Array(await crypto.subtle.digest('SHA-256', raw));

    return [...digest]
        .map((byte) => byte.toString(16).padStart(2, '0'))
        .join('');
}

function finishEnable(): void {
    if (wrapped.value === null) {
        return;
    }

    router.post(
        enableVault().url,
        {
            wrapped_passphrase: wrapped.value.passphrase,
            wrapped_recovery: wrapped.value.recovery,
            kdf: DEFAULT_KDF,
            kdf_iterations: DEFAULT_ITERATIONS,
            kdf_salt: wrapped.value.salt,
            recovery_salt: wrapped.value.recoverySalt,
            fingerprint: wrapped.value.fingerprint,
            acknowledged_recovery_key: true,
        },
        { preserveScroll: true, onFinish: reset },
    );
}

/** Re-derive the raw data key from whichever secret the user still has. */
async function exportKeyForDisable(): Promise<string> {
    if (!disableUsingRecovery.value) {
        return exportKeyWithPassphrase(disablePassphrase.value);
    }

    const parsed = parseRecoveryKey(disableRecoveryKey.value);

    if (parsed === null) {
        throw new Error('That is not a valid recovery key.');
    }

    return exportKeyWithRecoveryKey(parsed);
}

async function finishDisable(): Promise<void> {
    busy.value = true;
    error.value = '';

    try {
        const dek = await exportKeyForDisable();

        router.post(
            disableVault().url,
            { dek, confirmed: true },
            {
                preserveScroll: true,
                onSuccess: () => lock(),
                onFinish: reset,
            },
        );
    } catch {
        error.value = disableUsingRecovery.value
            ? t('settings.security.vault.recovery_unlock_failed')
            : t('settings.security.vault.unlock_failed');
        busy.value = false;
    }
}
</script>

<template>
    <div class="space-y-4">
        <!-- The title used to live here, as an uppercase `<p>` — which meant the
             one part of Security with the most to explain was the one part with
             no heading in the document outline. `SettingsSection` owns it now,
             as an `<h2>` like every other block on the page, and this keeps only
             the state badge, which is the part that actually changes. -->
        <p
            class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium"
            :class="
                armed
                    ? 'bg-[#02CD86]/10 text-[#02CD86]'
                    : 'bg-white/5 text-[#989898]'
            "
        >
            <component
                :is="armed ? ShieldCheck : Lock"
                class="size-3.5"
                aria-hidden="true"
            />
            {{
                armed
                    ? t('settings.security.vault.badge_on')
                    : t('settings.security.vault.badge_off')
            }}
        </p>

        <!-- What the user has right now, before any switch. The off state must not
             claim we cannot read the data — that is false at this level. -->
        <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
            <p class="text-sm font-medium text-white">
                {{
                    armed
                        ? t('settings.security.vault.on_heading')
                        : t('settings.security.vault.off_heading')
                }}
            </p>
            <ul class="mt-3 space-y-2">
                <li
                    v-for="index in 3"
                    :key="index"
                    class="flex gap-2 text-sm text-[#989898]"
                >
                    <Check class="mt-0.5 size-4 shrink-0 text-[#6f6f6f]" />
                    <span>
                        {{
                            t(
                                `settings.security.vault.${armed ? 'on' : 'off'}_point_${index}`,
                            )
                        }}
                    </span>
                </li>
            </ul>
        </div>

        <p v-if="!armed && !props.hasPassword" class="text-sm text-[#989898]">
            {{ t('settings.security.vault.needs_password') }}
        </p>

        <button
            v-else-if="!armed"
            type="button"
            class="cursor-pointer rounded-xl bg-[#02CD86] px-4 py-2.5 text-sm font-medium text-[#101010] transition hover:bg-[#02b678]"
            @click="step = 'tradeoff'"
        >
            {{ t('settings.security.vault.enable') }}
        </button>

        <button
            v-else
            type="button"
            class="cursor-pointer rounded-xl bg-white/10 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-white/15"
            @click="step = 'disable'"
        >
            {{ t('settings.security.vault.disable') }}
        </button>
    </div>

    <Teleport to="body">
        <div
            v-if="step !== 'idle'"
            class="app-scroll-thin fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/70 p-4 backdrop-blur-sm sm:items-center"
            @click.self="reset"
        >
            <!-- Wide enough for the consequences to be read rather than skimmed:
                 this is the only screen where the trade is spelled out, and the
                 old max-w-md truncated it into two sentences of hedging. -->
            <div
                class="my-auto w-full max-w-2xl rounded-[16px] bg-[#1a1a1a] p-6 shadow-[0_18px_45px_rgba(0,0,0,0.5)] ring-1 ring-white/10 sm:p-8"
            >
                <template v-if="step === 'tradeoff' || step === 'disable'">
                    <div class="flex items-start gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl"
                            :class="
                                step === 'tradeoff'
                                    ? 'bg-[#02CD86]/10 text-[#02CD86]'
                                    : 'bg-[#E94E50]/10 text-[#E94E50]'
                            "
                        >
                            <component
                                :is="step === 'tradeoff' ? ShieldCheck : Eye"
                                class="size-5"
                            />
                        </span>
                        <div>
                            <p class="text-[19px] font-medium text-white">
                                {{
                                    step === 'tradeoff'
                                        ? t('settings.security.vault.enable')
                                        : t('settings.security.vault.disable')
                                }}
                            </p>
                            <p class="mt-1 text-sm text-[#989898]">
                                {{
                                    step === 'tradeoff'
                                        ? t(
                                              'settings.security.vault.enable_intro',
                                          )
                                        : t(
                                              'settings.security.vault.disable_intro',
                                          )
                                }}
                            </p>
                        </div>
                    </div>

                    <!-- ── Turning it on ───────────────────────────────── -->
                    <template v-if="step === 'tradeoff'">
                        <div
                            class="mt-5 rounded-xl bg-[#02CD86]/10 p-4 ring-1 ring-[#02CD86]/20"
                        >
                            <p class="text-xs font-medium text-[#02CD86]">
                                {{ t('settings.security.vault.gain') }}
                            </p>
                            <p class="mt-1 text-sm text-white/80">
                                {{ t('settings.security.vault.enable_gain') }}
                            </p>
                        </div>

                        <!-- The headline warning. Telegram and the AI assistant do
                             not degrade under the vault, they stop: both run without
                             a browser, so there is nothing to decrypt with. -->
                        <div
                            class="mt-3 rounded-xl bg-[#E94E50]/10 p-4 ring-1 ring-[#E94E50]/25"
                        >
                            <div class="flex items-start gap-2.5">
                                <AlertTriangle
                                    class="mt-0.5 size-4 shrink-0 text-[#E94E50]"
                                />
                                <div>
                                    <p
                                        class="text-sm font-medium text-[#E94E50]"
                                    >
                                        {{
                                            t(
                                                'settings.security.vault.enable_warning_title',
                                            )
                                        }}
                                    </p>
                                    <p class="mt-1 text-sm text-white/80">
                                        {{
                                            t(
                                                'settings.security.vault.enable_warning_body',
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <ul class="mt-4 space-y-2.5">
                                <li
                                    v-for="loss in enableLosses"
                                    :key="loss.key"
                                    class="flex items-start gap-2.5"
                                >
                                    <component
                                        :is="loss.icon"
                                        class="mt-0.5 size-4 shrink-0 text-[#E94E50]/70"
                                    />
                                    <span class="text-sm text-[#989898]">
                                        <span class="text-white/85">{{
                                            t(
                                                `settings.security.vault.enable_loss.${loss.key}.title`,
                                            )
                                        }}</span>
                                        —
                                        {{
                                            t(
                                                `settings.security.vault.enable_loss.${loss.key}.text`,
                                            )
                                        }}
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <p class="mt-3 text-xs text-[#6f6f6f]">
                            {{ t('settings.security.vault.enable_keeps') }}
                        </p>
                    </template>

                    <!-- ── Turning it off ──────────────────────────────── -->
                    <template v-else>
                        <div
                            class="mt-5 rounded-xl bg-[#E94E50]/10 p-4 ring-1 ring-[#E94E50]/25"
                        >
                            <div class="flex items-start gap-2.5">
                                <AlertTriangle
                                    class="mt-0.5 size-4 shrink-0 text-[#E94E50]"
                                />
                                <div>
                                    <p
                                        class="text-sm font-medium text-[#E94E50]"
                                    >
                                        {{
                                            t(
                                                'settings.security.vault.disable_warning_title',
                                            )
                                        }}
                                    </p>
                                    <p class="mt-1 text-sm text-white/80">
                                        {{
                                            t(
                                                'settings.security.vault.disable_warning_body',
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <ul class="mt-3 space-y-2.5 rounded-xl bg-white/5 p-4">
                            <li
                                v-for="effect in disableEffects"
                                :key="effect.key"
                                class="flex items-start gap-2.5"
                            >
                                <component
                                    :is="effect.icon"
                                    class="mt-0.5 size-4 shrink-0"
                                    :class="
                                        effect.tone === 'warn'
                                            ? 'text-[#E94E50]'
                                            : 'text-[#02CD86]'
                                    "
                                />
                                <span class="text-sm text-[#989898]">
                                    <span class="text-white/85">{{
                                        t(
                                            `settings.security.vault.disable_effect.${effect.key}.title`,
                                        )
                                    }}</span>
                                    —
                                    {{
                                        t(
                                            `settings.security.vault.disable_effect.${effect.key}.text`,
                                        )
                                    }}
                                </span>
                            </li>
                        </ul>

                        <!-- Leaving the vault is the direction people skim, so it
                             carries the friction. -->
                        <div class="mt-5 space-y-3">
                            <input
                                v-model="disableWord"
                                type="text"
                                autocomplete="off"
                                :placeholder="
                                    t('settings.security.vault.confirm_word', {
                                        word: confirmWord,
                                    })
                                "
                                class="w-full rounded-xl bg-white/5 px-3 py-2.5 text-sm text-white ring-1 ring-white/10 outline-none focus:ring-[#02CD86]"
                            />

                            <!-- Either secret unwraps the key, so either is accepted
                                 for the downgrade. -->
                            <input
                                v-if="!disableUsingRecovery"
                                v-model="disablePassphrase"
                                type="password"
                                name="vault-passphrase"
                                autocomplete="current-password"
                                :placeholder="
                                    t('settings.security.vault.passphrase')
                                "
                                class="w-full rounded-xl bg-white/5 px-3 py-2.5 text-sm text-white ring-1 ring-white/10 outline-none focus:ring-[#02CD86]"
                            />
                            <input
                                v-else
                                v-model="disableRecoveryKey"
                                type="text"
                                autocomplete="off"
                                spellcheck="false"
                                :placeholder="
                                    t('settings.security.vault.recovery_key')
                                "
                                class="w-full rounded-xl bg-white/5 px-3 py-2.5 font-mono text-sm text-white ring-1 ring-white/10 outline-none focus:ring-[#02CD86]"
                            />

                            <button
                                type="button"
                                class="cursor-pointer text-xs text-[#02CD86] underline-offset-2 hover:underline"
                                @click="
                                    disableUsingRecovery = !disableUsingRecovery
                                "
                            >
                                {{
                                    disableUsingRecovery
                                        ? t(
                                              'settings.security.vault.use_passphrase',
                                          )
                                        : t(
                                              'settings.security.vault.use_recovery',
                                          )
                                }}
                            </button>

                            <label
                                class="flex cursor-pointer items-start gap-2"
                            >
                                <Checkbox
                                    :checked="disableUnderstood"
                                    class="mt-0.5"
                                    @update:checked="
                                        disableUnderstood = $event === true
                                    "
                                />
                                <span class="text-xs text-[#989898]">
                                    {{
                                        t(
                                            'settings.security.vault.confirm_understood',
                                        )
                                    }}
                                </span>
                            </label>
                        </div>
                    </template>

                    <p v-if="error" class="mt-3 text-sm text-[#E94E50]">
                        {{ error }}
                    </p>

                    <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                        <button
                            type="button"
                            class="flex-1 cursor-pointer rounded-xl bg-white/10 py-2.5 text-sm font-medium text-white transition hover:bg-white/15"
                            @click="reset"
                        >
                            {{ t('settings.security.vault.cancel') }}
                        </button>
                        <button
                            v-if="step === 'tradeoff'"
                            type="button"
                            class="flex-1 cursor-pointer rounded-xl bg-[#02CD86] py-2.5 text-sm font-medium text-[#101010] transition hover:bg-[#02b678]"
                            @click="step = 'passphrase'"
                        >
                            {{ t('settings.security.vault.continue') }}
                        </button>
                        <button
                            v-else
                            type="button"
                            :disabled="!canDisable || busy"
                            class="flex-1 cursor-pointer rounded-xl bg-[#E94E50] py-2.5 text-sm font-medium text-white transition hover:bg-[#d43e40] disabled:opacity-40"
                            @click="finishDisable"
                        >
                            {{ t('settings.security.vault.disable') }}
                        </button>
                    </div>
                </template>

                <template v-else-if="step === 'passphrase'">
                    <p class="text-[19px] font-medium text-white">
                        {{ t('settings.security.vault.passphrase') }}
                    </p>
                    <p class="mt-2 text-sm text-[#989898]">
                        {{ t('settings.security.vault.passphrase_help') }}
                    </p>

                    <input
                        v-model="passphrase"
                        type="text"
                        autocomplete="off"
                        spellcheck="false"
                        class="mt-4 w-full rounded-xl bg-white/5 px-3 py-2.5 font-mono text-sm text-white ring-1 ring-white/10 outline-none focus:ring-[#02CD86]"
                    />

                    <!-- A generated passphrase is worth more than any iteration
                         count: PBKDF2's weakness is guessability, not speed. -->
                    <button
                        type="button"
                        class="mt-2 cursor-pointer text-xs text-[#02CD86] underline-offset-2 hover:underline"
                        @click="passphrase = generatePassphrase()"
                    >
                        {{ t('settings.security.vault.generate') }}
                    </button>

                    <p
                        v-if="passphrase.length > 0 && passphrase.length < 12"
                        class="mt-2 text-xs text-[#E94E50]"
                    >
                        {{ t('settings.security.vault.passphrase_too_short') }}
                    </p>

                    <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                        <button
                            type="button"
                            class="flex-1 cursor-pointer rounded-xl bg-white/10 py-2.5 text-sm font-medium text-white transition hover:bg-white/15"
                            @click="reset"
                        >
                            {{ t('settings.security.vault.cancel') }}
                        </button>
                        <button
                            type="button"
                            :disabled="passphrase.length < 12 || busy"
                            class="flex-1 cursor-pointer rounded-xl bg-[#02CD86] py-2.5 text-sm font-medium text-[#101010] transition hover:bg-[#02b678] disabled:opacity-40"
                            @click="prepareWrapping"
                        >
                            {{ t('settings.security.vault.continue') }}
                        </button>
                    </div>
                </template>

                <template v-else-if="step === 'recovery'">
                    <p class="text-[19px] font-medium text-white">
                        {{ t('settings.security.vault.recovery_heading') }}
                    </p>
                    <p class="mt-2 text-sm text-[#989898]">
                        {{ t('settings.security.vault.recovery_help') }}
                    </p>

                    <!-- Rendered per group rather than as one string, so the group
                         we ask for can be pointed at instead of counted. -->
                    <div
                        class="mt-4 flex flex-wrap justify-center gap-1.5 rounded-xl bg-white/5 p-3 select-all"
                    >
                        <span
                            v-for="(group, index) in recoveryGroups"
                            :key="index"
                            class="rounded-md px-1.5 py-0.5 font-mono text-sm tracking-wider"
                            :class="
                                index === recoveryGroupIndex
                                    ? 'bg-[#02CD86]/20 text-[#02CD86] ring-1 ring-[#02CD86]/40'
                                    : 'text-white'
                            "
                        >
                            {{ group }}
                        </span>
                    </div>

                    <!-- Typing a group back is what makes "saved it" mean something.
                         This must clear before the server drops its copy — after
                         that there is no way back. -->
                    <label class="mt-4 block text-xs text-[#989898]">
                        {{ t('settings.security.vault.recovery_confirm') }}
                    </label>
                    <input
                        v-model="recoveryEcho"
                        type="text"
                        autocomplete="off"
                        spellcheck="false"
                        class="mt-1 w-full rounded-xl bg-white/5 px-3 py-2.5 font-mono text-sm tracking-wider text-white uppercase ring-1 ring-white/10 outline-none focus:ring-[#02CD86]"
                    />

                    <!-- Silence used to be the only feedback here, which reads as
                         "nothing I type works". -->
                    <p
                        v-if="recoveryEcho.trim() !== '' && !recoveryMatches"
                        class="mt-1.5 text-xs text-[#E94E50]"
                    >
                        {{ t('settings.security.vault.recovery_mismatch') }}
                    </p>

                    <label class="mt-3 flex cursor-pointer items-start gap-2">
                        <Checkbox
                            :checked="acknowledged"
                            class="mt-0.5"
                            @update:checked="acknowledged = $event === true"
                        />
                        <span class="text-xs text-[#989898]">
                            {{ t('settings.security.vault.acknowledge') }}
                        </span>
                    </label>

                    <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                        <button
                            type="button"
                            class="flex-1 cursor-pointer rounded-xl bg-white/10 py-2.5 text-sm font-medium text-white transition hover:bg-white/15"
                            @click="reset"
                        >
                            {{ t('settings.security.vault.cancel') }}
                        </button>
                        <button
                            type="button"
                            :disabled="!canFinish"
                            class="flex-1 cursor-pointer rounded-xl bg-[#02CD86] py-2.5 text-sm font-medium text-[#101010] transition hover:bg-[#02b678] disabled:opacity-40"
                            @click="finishEnable"
                        >
                            {{ t('settings.security.vault.enable') }}
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </Teleport>
</template>
