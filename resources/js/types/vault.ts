/**
 * A value the server could not decrypt, shipped to the browser as ciphertext.
 *
 * Produced by App\Support\Encryption\EncryptedValue::jsonSerialize().
 */
export type Ciphertext = {
    readonly __enc: 1;
    /** The cp1 blob. */
    readonly c: string;
    /** Column name, used to rebuild the AAD. */
    readonly f: string;
};

/**
 * A prop that carries either a readable value or ciphertext, depending on whether
 * the viewing user has the vault armed.
 *
 * Widening a page prop to this is the single most useful safety step in the whole
 * client effort: vue-tsc then points at every render site that has to handle both.
 */
export type Encrypted<T> = T | Ciphertext;

export function isCiphertext(value: unknown): value is Ciphertext {
    return (
        typeof value === 'object' &&
        value !== null &&
        (value as Ciphertext).__enc === 1 &&
        typeof (value as Ciphertext).c === 'string'
    );
}

/** What the server tells the client about its vault, on every page. */
export type VaultDescriptor = {
    armed: boolean;
    kdf: string;
    iterations: number;
    salt: string;
    recoverySalt: string;
    fingerprint: string;
    wrappedPassphrase: string;
    wrappedRecovery: string;
};
