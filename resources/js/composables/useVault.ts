import { usePage } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { readonly, ref } from 'vue';
import type { EncryptedFieldType } from '@/lib/vault/codec';
import { decode, encode } from '@/lib/vault/codec';
import {
    aadFor,
    base64ToBytes,
    decrypt,
    encrypt,
    importDek,
} from '@/lib/vault/crypto';
import { derivePassphraseKek, deriveRecoveryKek } from '@/lib/vault/kdf';
import type { Ciphertext, Encrypted, VaultDescriptor } from '@/types/vault';
import { isCiphertext } from '@/types/vault';

/**
 * The unwrapped data key, held only in memory as a non-extractable CryptoKey.
 *
 * Deliberately not sessionStorage: persisting it would require an *extractable*
 * key, which any XSS could read out and keep forever. Non-extractable means an
 * attacker can use the key while the tab is open but never steal it — the
 * difference between a session-scoped compromise and a permanent one.
 *
 * The cost is honest and belongs in the UI: every hard reload and every new tab
 * re-prompts. Inertia navigation preserves it, so in practice this is once per
 * browsing session.
 */
let dek: CryptoKey | null = null;

const unlocked = ref(false);

/** Decrypted values keyed by blob, so a list re-render never re-decrypts. */
const cache = new Map<string, unknown>();

/** AAD for the wrapped data key itself. Only ever unwrapped in the browser. */
const DEK_AAD = aadFor('vault', 'dek');

async function fingerprintOf(raw: Uint8Array<ArrayBuffer>): Promise<string> {
    const digest = new Uint8Array(await crypto.subtle.digest('SHA-256', raw));

    return [...digest]
        .map((byte) => byte.toString(16).padStart(2, '0'))
        .join('');
}

async function adoptDek(
    kek: CryptoKey,
    wrapped: string,
    expectedFingerprint: string,
): Promise<void> {
    const raw = base64ToBytes(await decrypt(wrapped, kek, DEK_AAD));

    if ((await fingerprintOf(raw)) !== expectedFingerprint) {
        throw new Error(
            'Unwrapped key does not match the recorded fingerprint.',
        );
    }

    dek = await importDek(raw, false);
    unlocked.value = true;
}

export type UseVaultReturn = {
    unlocked: Readonly<Ref<boolean>>;
    isArmed: () => boolean;
    reveal: <T>(value: Encrypted<T> | null | undefined) => T | undefined;
    revealAsync: <T>(
        value: Encrypted<T> | null | undefined,
        table: string,
        type?: EncryptedFieldType,
    ) => Promise<T | undefined>;
    unlockWithPassphrase: (passphrase: string) => Promise<void>;
    unlockWithRecoveryKey: (
        recoveryKey: Uint8Array<ArrayBuffer>,
    ) => Promise<void>;
    exportKeyWithPassphrase: (passphrase: string) => Promise<string>;
    sealForSubmit: <T extends Record<string, unknown>>(
        values: T,
        table: string,
        fields: Record<string, EncryptedFieldType>,
    ) => Promise<T>;
    lock: () => void;
};

export function useVault(): UseVaultReturn {
    const page = usePage();

    const descriptor = (): VaultDescriptor | null =>
        (page.props.vault as VaultDescriptor | null | undefined) ?? null;

    const isArmed = (): boolean => descriptor()?.armed === true;

    /**
     * Synchronous read. Returns the value straight through when it is not
     * encrypted — which is what lets <Ciphered> be adopted across the app long
     * before any vault exists — or a cached plaintext, or undefined while a
     * decryption is still pending.
     */
    function reveal<T>(value: Encrypted<T> | null | undefined): T | undefined {
        if (value === null || value === undefined) {
            return undefined;
        }

        if (!isCiphertext(value)) {
            return value;
        }

        // The cache holds already-decoded values, so neither the table nor the
        // field type is needed here — only revealAsync does real work.
        return cache.has(value.c) ? (cache.get(value.c) as T) : undefined;
    }

    async function revealAsync<T>(
        value: Encrypted<T> | null | undefined,
        table: string,
        type: EncryptedFieldType = 'string',
    ): Promise<T | undefined> {
        if (value === null || value === undefined) {
            return undefined;
        }

        if (!isCiphertext(value)) {
            return value;
        }

        const blob = (value as Ciphertext).c;

        if (cache.has(blob)) {
            return cache.get(blob) as T;
        }

        if (dek === null) {
            return undefined;
        }

        const plaintext = await decrypt(
            blob,
            dek,
            aadFor(table, (value as Ciphertext).f),
        );

        const decoded = decode(plaintext, type) as T;
        cache.set(blob, decoded);

        return decoded;
    }

    async function unlockWithPassphrase(passphrase: string): Promise<void> {
        const vault = descriptor();

        if (vault === null || !vault.armed) {
            return;
        }

        await adoptDek(
            await derivePassphraseKek(
                passphrase,
                base64ToBytes(vault.salt),
                vault.iterations,
            ),
            vault.wrappedPassphrase,
            vault.fingerprint,
        );
    }

    async function unlockWithRecoveryKey(
        recoveryKey: Uint8Array<ArrayBuffer>,
    ): Promise<void> {
        const vault = descriptor();

        if (vault === null || !vault.armed) {
            return;
        }

        await adoptDek(
            await deriveRecoveryKek(
                recoveryKey,
                base64ToBytes(vault.recoverySalt),
            ),
            vault.wrappedRecovery,
            vault.fingerprint,
        );
    }

    /**
     * Re-derive the raw data key from the passphrase, for handing back to the
     * server when leaving the vault.
     *
     * Deliberately not read from the unlocked key: that one is imported
     * non-extractable precisely so it can never be exported. Re-deriving means the
     * raw bytes exist only for this call, and it makes the privacy downgrade
     * re-authenticated rather than something a stray click can do.
     */
    async function exportKeyWithPassphrase(
        passphrase: string,
    ): Promise<string> {
        const vault = descriptor();

        if (vault === null || !vault.armed) {
            throw new Error('The vault is not armed.');
        }

        const kek = await derivePassphraseKek(
            passphrase,
            base64ToBytes(vault.salt),
            vault.iterations,
        );

        const raw = await decrypt(vault.wrappedPassphrase, kek, DEK_AAD);

        if ((await fingerprintOf(base64ToBytes(raw))) !== vault.fingerprint) {
            throw new Error('That passphrase did not open the vault.');
        }

        return raw;
    }

    /**
     * Encrypt the named fields of a form payload before it is submitted.
     *
     * A no-op when the vault is not armed, so callers submit through this
     * unconditionally rather than branching on vault state at every form.
     *
     * @param  fields  column name => how the server will decode it
     */
    async function sealForSubmit<T extends Record<string, unknown>>(
        values: T,
        table: string,
        fields: Record<string, EncryptedFieldType>,
    ): Promise<T> {
        if (!isArmed()) {
            return values;
        }

        if (dek === null) {
            throw new Error('The vault is locked.');
        }

        const sealed: Record<string, unknown> = { ...values };

        for (const [field, type] of Object.entries(fields)) {
            const value = sealed[field];

            // Empty optional fields stay empty — encrypting '' would store a blob
            // the server then has to treat as present.
            if (value === null || value === undefined || value === '') {
                continue;
            }

            sealed[field] = await encrypt(
                encode(value, type),
                dek,
                aadFor(table, field),
            );
        }

        return sealed as T;
    }

    function lock(): void {
        dek = null;
        unlocked.value = false;
        cache.clear();
    }

    return {
        unlocked: readonly(unlocked),
        isArmed,
        reveal,
        revealAsync,
        unlockWithPassphrase,
        unlockWithRecoveryKey,
        exportKeyWithPassphrase,
        sealForSubmit,
        lock,
    };
}
