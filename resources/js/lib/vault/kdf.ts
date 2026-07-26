/**
 * Key derivation for the vault.
 *
 *   passphrase -> KEK  via PBKDF2-HMAC-SHA256
 *   recoveryKey -> KEK via HKDF-SHA256
 *
 * PBKDF2 rather than Argon2id, deliberately: Argon2id is the better primitive,
 * but the only implementation available in a browser is a WASM package — a new
 * runtime dependency on the one library that touches the user's sole secret, and
 * one that has to load on the latency-critical unlock path. PBKDF2 is native.
 *
 * The weakness of PBKDF2 is guessability, not iteration count, so the real
 * mitigation is passphrase strength — see generatePassphrase in recoveryKey.ts.
 * Iterations and algorithm are stored per user, so switching later is a re-wrap
 * rather than a migration.
 *
 * A recovery key needs no stretching at all: it is already 256 uniformly random
 * bits, so HKDF is the right primitive and it is instant — which matters, because
 * recovery is the panic path.
 *
 * Deliberately free of imports: the Node test runner loads this file directly.
 */

export const DEFAULT_KDF = 'pbkdf2-sha256';
export const DEFAULT_ITERATIONS = 600_000;
export const RECOVERY_INFO = 'cashpilot-vault-recovery-v1';

export async function derivePassphraseBits(
    passphrase: string,
    salt: Uint8Array<ArrayBuffer>,
    iterations: number = DEFAULT_ITERATIONS,
): Promise<Uint8Array<ArrayBuffer>> {
    const material = await crypto.subtle.importKey(
        'raw',
        // NFKC is not optional: without it the same passphrase typed on iOS and
        // on Windows can produce different bytes, and the vault refuses to open.
        new TextEncoder().encode(passphrase.normalize('NFKC')),
        'PBKDF2',
        false,
        ['deriveBits'],
    );

    return new Uint8Array(
        await crypto.subtle.deriveBits(
            { name: 'PBKDF2', salt, iterations, hash: 'SHA-256' },
            material,
            256,
        ),
    );
}

export async function deriveRecoveryBits(
    recoveryKey: Uint8Array<ArrayBuffer>,
    salt: Uint8Array<ArrayBuffer>,
    info: string = RECOVERY_INFO,
): Promise<Uint8Array<ArrayBuffer>> {
    const material = await crypto.subtle.importKey(
        'raw',
        recoveryKey,
        'HKDF',
        false,
        ['deriveBits'],
    );

    return new Uint8Array(
        await crypto.subtle.deriveBits(
            {
                name: 'HKDF',
                hash: 'SHA-256',
                salt,
                info: new TextEncoder().encode(info),
            },
            material,
            256,
        ),
    );
}

/** Wrap derived bits as a non-extractable AES-GCM key. */
export async function toKek(bits: Uint8Array<ArrayBuffer>): Promise<CryptoKey> {
    return crypto.subtle.importKey('raw', bits, 'AES-GCM', false, [
        'encrypt',
        'decrypt',
    ]);
}

export async function derivePassphraseKek(
    passphrase: string,
    salt: Uint8Array<ArrayBuffer>,
    iterations: number = DEFAULT_ITERATIONS,
): Promise<CryptoKey> {
    return toKek(await derivePassphraseBits(passphrase, salt, iterations));
}

export async function deriveRecoveryKek(
    recoveryKey: Uint8Array<ArrayBuffer>,
    salt: Uint8Array<ArrayBuffer>,
): Promise<CryptoKey> {
    return toKek(await deriveRecoveryBits(recoveryKey, salt));
}
