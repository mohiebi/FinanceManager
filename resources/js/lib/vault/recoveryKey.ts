/**
 * The vault recovery key: 32 random bytes, shown to the user once.
 *
 * Rendered in Crockford base32 — no I, L, O or U, so it cannot be misread as 1,
 * 0 or mistyped into a slur — and grouped for transcription.
 */
const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
const GROUP_SIZE = 5;
const BYTES = 32;

export function generateRecoveryKey(): Uint8Array<ArrayBuffer> {
    return crypto.getRandomValues(new Uint8Array(BYTES));
}

export function formatRecoveryKey(bytes: Uint8Array<ArrayBuffer>): string {
    let bits = 0;
    let value = 0;
    let output = '';

    for (const byte of bytes) {
        value = (value << 8) | byte;
        bits += 8;

        while (bits >= 5) {
            output += ALPHABET[(value >>> (bits - 5)) & 31];
            bits -= 5;
        }
    }

    if (bits > 0) {
        output += ALPHABET[(value << (5 - bits)) & 31];
    }

    return (output.match(new RegExp(`.{1,${GROUP_SIZE}}`, 'g')) ?? []).join(
        '-',
    );
}

/**
 * Parse a typed recovery key back to bytes.
 *
 * Tolerant on input because this is the panic path: case, spacing, dashes and
 * the classic 0/O and 1/I confusions are all forgiven.
 */
export function parseRecoveryKey(
    input: string,
): Uint8Array<ArrayBuffer> | null {
    const cleaned = input
        .toUpperCase()
        .replace(/[\s-]/g, '')
        .replace(/O/g, '0')
        .replace(/[IL]/g, '1');

    let bits = 0;
    let value = 0;
    const bytes: number[] = [];

    for (const character of cleaned) {
        const index = ALPHABET.indexOf(character);

        if (index === -1) {
            return null;
        }

        value = (value << 5) | index;
        bits += 5;

        if (bits >= 8) {
            bytes.push((value >>> (bits - 8)) & 255);
            bits -= 8;
        }
    }

    return bytes.length === BYTES ? new Uint8Array(bytes) : null;
}

/**
 * A passphrase the user cannot get wrong.
 *
 * PBKDF2's weakness is guessability, so offering a generated passphrase is worth
 * more than any iteration count: six words from this list is ~77 bits of entropy,
 * which is unbreakable regardless of the KDF.
 */
const WORDS = [
    'anchor',
    'basket',
    'candle',
    'dolphin',
    'ember',
    'falcon',
    'garden',
    'harbor',
    'island',
    'jacket',
    'kettle',
    'lantern',
    'meadow',
    'nutmeg',
    'orchard',
    'pepper',
    'quartz',
    'ribbon',
    'saddle',
    'timber',
    'umbrella',
    'velvet',
    'walnut',
    'yellow',
    'almond',
    'bridge',
    'copper',
    'dragon',
    'engine',
    'forest',
    'gravel',
    'hollow',
    'indigo',
    'jungle',
    'kernel',
    'ladder',
    'marble',
    'nectar',
    'oyster',
    'planet',
    'quiver',
    'rocket',
    'silver',
    'tunnel',
    'urchin',
    'violet',
    'willow',
    'zephyr',
];

export function generatePassphrase(words = 6): string {
    const picks = crypto.getRandomValues(new Uint32Array(words));

    return [...picks].map((pick) => WORDS[pick % WORDS.length]).join(' ');
}
