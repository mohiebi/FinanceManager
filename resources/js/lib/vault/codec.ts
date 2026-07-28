/**
 * Decodes a decrypted plaintext back into the shape the server would have
 * returned, mirroring App\Casts\UserEncrypted::decode().
 */
export type EncryptedFieldType = 'string' | 'decimal' | 'json';

export function decode(
    plaintext: string,
    type: EncryptedFieldType = 'string',
): unknown {
    if (type === 'json') {
        return JSON.parse(plaintext);
    }

    // Decimals stay strings, exactly as the PHP cast returns them, so nothing
    // downstream sees a different shape depending on whether the vault is on.
    return plaintext;
}

/**
 * The inverse, for values the browser encrypts before submitting.
 */
export function encode(
    value: unknown,
    type: EncryptedFieldType = 'string',
): string {
    if (type === 'json') {
        return JSON.stringify(value);
    }

    if (type === 'decimal') {
        return Number(value).toFixed(2);
    }

    return String(value);
}
