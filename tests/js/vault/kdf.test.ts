import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

import {
    derivePassphraseBits,
    deriveRecoveryBits,
} from '../../../resources/js/lib/vault/kdf.ts';

const vectors = JSON.parse(
    readFileSync(
        new URL('../../fixtures/crypto-vectors.json', import.meta.url),
        'utf8',
    ),
);

function hexToBytes(hex: string): Uint8Array {
    const bytes = new Uint8Array(hex.length / 2);

    for (let i = 0; i < bytes.length; i++) {
        bytes[i] = parseInt(hex.slice(i * 2, i * 2 + 2), 16);
    }

    return bytes;
}

function bytesToHex(bytes: Uint8Array): string {
    return [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
}

test('PBKDF2 agrees with PHP', async () => {
    assert.ok(vectors.pbkdf2_sha256.length > 0);

    for (const vector of vectors.pbkdf2_sha256) {
        const bits = await derivePassphraseBits(
            vector.passphrase,
            hexToBytes(vector.salt_hex),
            vector.iterations,
        );

        assert.equal(bytesToHex(bits), vector.derived_hex);
    }
});

test('HKDF agrees with PHP', async () => {
    assert.ok(vectors.hkdf_sha256.length > 0);

    for (const vector of vectors.hkdf_sha256) {
        const bits = await deriveRecoveryBits(
            hexToBytes(vector.ikm_hex),
            hexToBytes(vector.salt_hex),
            vector.info,
        );

        assert.equal(bytesToHex(bits), vector.derived_hex);
    }
});

test('a different passphrase derives a different key', async () => {
    const salt = crypto.getRandomValues(new Uint8Array(16));

    const first = await derivePassphraseBits('correct horse', salt, 1000);
    const second = await derivePassphraseBits('correct horsf', salt, 1000);

    assert.notEqual(bytesToHex(first), bytesToHex(second));
});

test('a different salt derives a different key', async () => {
    const first = await derivePassphraseBits(
        'same passphrase',
        new Uint8Array(16).fill(1),
        1000,
    );
    const second = await derivePassphraseBits(
        'same passphrase',
        new Uint8Array(16).fill(2),
        1000,
    );

    assert.notEqual(bytesToHex(first), bytesToHex(second));
});

test('passphrases are unicode-normalised before derivation', async () => {
    const salt = new Uint8Array(16).fill(7);

    // The same grapheme, composed vs decomposed. Without NFKC these produce
    // different keys and a passphrase typed on one platform will not open a vault
    // created on another.
    const composed = await derivePassphraseBits('café', salt, 1000);
    const decomposed = await derivePassphraseBits('café', salt, 1000);

    assert.equal(bytesToHex(composed), bytesToHex(decomposed));
});
