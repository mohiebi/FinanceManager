/**
 * The `cp1` field-encryption format, the browser half.
 *
 *   blob = "cp1" || iv(12) || ciphertext || tag(16),  base64
 *   AAD  = "cp1:{table}:{column}"
 *
 * Must stay byte-for-byte compatible with App\Support\Encryption\UserCrypto.
 * tests/js/vault/crypto.test.ts and tests/Unit/UserCryptoTest.php both assert
 * against tests/fixtures/crypto-vectors.json, so a drift on either side fails
 * both suites.
 *
 * Deliberately free of imports: the Node test runner loads this file directly.
 */

const MAGIC = 'cp1';
const IV_BYTES = 12;
const TAG_BYTES = 16;

export function bytesToBase64(bytes: Uint8Array<ArrayBuffer>): string {
    let binary = '';

    for (const byte of bytes) {
        binary += String.fromCharCode(byte);
    }

    return btoa(binary);
}

export function base64ToBytes(value: string): Uint8Array<ArrayBuffer> {
    const binary = atob(value);
    const bytes = new Uint8Array(binary.length);

    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }

    return bytes;
}

export function aadFor(table: string, column: string): string {
    return `${MAGIC}:${table}:${column}`;
}

/**
 * Import raw key bytes as an AES-GCM key.
 *
 * Non-extractable by default: an extractable key can be read out and exfiltrated
 * by any XSS, which is exactly what holding the key client-side is meant to avoid.
 */
export async function importDek(
    raw: Uint8Array<ArrayBuffer>,
    extractable = false,
): Promise<CryptoKey> {
    return crypto.subtle.importKey('raw', raw, 'AES-GCM', extractable, [
        'encrypt',
        'decrypt',
    ]);
}

export async function encrypt(
    plaintext: string,
    dek: CryptoKey,
    aad: string,
): Promise<string> {
    const iv = crypto.getRandomValues(new Uint8Array(IV_BYTES));

    const sealed = new Uint8Array(
        await crypto.subtle.encrypt(
            {
                name: 'AES-GCM',
                iv,
                additionalData: new TextEncoder().encode(aad),
                tagLength: TAG_BYTES * 8,
            },
            dek,
            new TextEncoder().encode(plaintext),
        ),
    );

    // Web Crypto already appends the tag, which is why PHP concatenates it too.
    const blob = new Uint8Array(MAGIC.length + iv.length + sealed.length);
    blob.set(new TextEncoder().encode(MAGIC), 0);
    blob.set(iv, MAGIC.length);
    blob.set(sealed, MAGIC.length + iv.length);

    return bytesToBase64(blob);
}

export async function decrypt(
    blob: string,
    dek: CryptoKey,
    aad: string,
): Promise<string> {
    let raw: Uint8Array<ArrayBuffer>;

    try {
        raw = base64ToBytes(blob);
    } catch {
        throw new Error('Malformed ciphertext.');
    }

    if (raw.length < MAGIC.length + IV_BYTES + TAG_BYTES) {
        throw new Error('Malformed ciphertext.');
    }

    if (new TextDecoder().decode(raw.slice(0, MAGIC.length)) !== MAGIC) {
        throw new Error('Malformed ciphertext.');
    }

    const iv = raw.slice(MAGIC.length, MAGIC.length + IV_BYTES);
    const sealed = raw.slice(MAGIC.length + IV_BYTES);

    const plaintext = await crypto.subtle.decrypt(
        {
            name: 'AES-GCM',
            iv,
            additionalData: new TextEncoder().encode(aad),
            tagLength: TAG_BYTES * 8,
        },
        dek,
        sealed,
    );

    return new TextDecoder().decode(plaintext);
}

export function looksEncrypted(value: string | null | undefined): boolean {
    if (value === null || value === undefined || value === '') {
        return false;
    }

    try {
        return atob(value).startsWith(MAGIC);
    } catch {
        return false;
    }
}
