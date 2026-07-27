import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
    aadFor,
    base64ToBytes,
    decrypt,
    encrypt,
    importDek,
} from '../../../resources/js/lib/vault/crypto.ts';
import {
    derivePassphraseKek,
    deriveRecoveryKek,
} from '../../../resources/js/lib/vault/kdf.ts';
import {
    formatRecoveryKey,
    generateRecoveryKey,
    parseRecoveryKey,
} from '../../../resources/js/lib/vault/recoveryKey.ts';

/**
 * The exact arm-then-unlock sequence the UI performs, end to end.
 *
 * Written after a user could not unlock a vault they had just armed: this pins
 * whether the fault is in the crypto or in what reaches it.
 */
const DEK_AAD = aadFor('vault', 'dek');

// Low iterations purely so the test is quick; the derivation path is identical.
const ITERATIONS = 1000;

async function fingerprintOf(raw: Uint8Array<ArrayBuffer>): Promise<string> {
    const digest = new Uint8Array(await crypto.subtle.digest('SHA-256', raw));

    return [...digest].map((b) => b.toString(16).padStart(2, '0')).join('');
}

/** What VaultSection does when arming. */
async function arm(passphrase: string) {
    const dek = btoa(
        String.fromCharCode(...crypto.getRandomValues(new Uint8Array(32))),
    );

    const salt = crypto.getRandomValues(new Uint8Array(16));
    const recoverySalt = crypto.getRandomValues(new Uint8Array(16));
    const recovery = generateRecoveryKey();

    const passphraseKek = await derivePassphraseKek(passphrase, salt, ITERATIONS);
    const recoveryKek = await deriveRecoveryKek(recovery, recoverySalt);

    return {
        dek,
        recoveryDisplay: formatRecoveryKey(recovery),
        descriptor: {
            salt: btoa(String.fromCharCode(...salt)),
            recoverySalt: btoa(String.fromCharCode(...recoverySalt)),
            iterations: ITERATIONS,
            fingerprint: await fingerprintOf(base64ToBytes(dek)),
            wrappedPassphrase: await encrypt(dek, passphraseKek, DEK_AAD),
            wrappedRecovery: await encrypt(dek, recoveryKek, DEK_AAD),
        },
    };
}

/** What useVault does when unlocking. */
async function unlockWithPassphrase(
    descriptor: Awaited<ReturnType<typeof arm>>['descriptor'],
    passphrase: string,
): Promise<string> {
    const kek = await derivePassphraseKek(
        passphrase,
        base64ToBytes(descriptor.salt),
        descriptor.iterations,
    );

    const raw = await decrypt(descriptor.wrappedPassphrase, kek, DEK_AAD);

    assert.equal(
        await fingerprintOf(base64ToBytes(raw)),
        descriptor.fingerprint,
        'fingerprint mismatch',
    );

    return raw;
}

test('a vault armed with a passphrase unlocks with that same passphrase', async () => {
    const passphrase = 'garden planet harbor island violet willow';
    const { dek, descriptor } = await arm(passphrase);

    assert.equal(await unlockWithPassphrase(descriptor, passphrase), dek);
});

test('the recovery key opens the same vault', async () => {
    const passphrase = 'garden planet harbor island violet willow';
    const { dek, descriptor, recoveryDisplay } = await arm(passphrase);

    const parsed = parseRecoveryKey(recoveryDisplay);
    assert.ok(parsed !== null, 'recovery key should parse back');

    const kek = await deriveRecoveryKek(
        parsed,
        base64ToBytes(descriptor.recoverySalt),
    );

    assert.equal(await decrypt(descriptor.wrappedRecovery, kek, DEK_AAD), dek);
});

test('the unwrapped key is usable for real field decryption', async () => {
    const passphrase = 'garden planet harbor island violet willow';
    const { descriptor } = await arm(passphrase);

    const raw = await unlockWithPassphrase(descriptor, passphrase);
    const dek = await importDek(base64ToBytes(raw));
    const aad = aadFor('transactions', 'title');

    assert.equal(
        await decrypt(await encrypt('Therapy session', dek, aad), dek, aad),
        'Therapy session',
    );
});

/**
 * What useVault does when leaving the vault: re-derive the raw key and hand it
 * back. `exportKeyWith` accepts either wrapping, so this mirrors both branches.
 */
async function exportKeyWith(
    kek: CryptoKey,
    wrapped: string,
    fingerprint: string,
): Promise<string> {
    const raw = await decrypt(wrapped, kek, DEK_AAD);

    if ((await fingerprintOf(base64ToBytes(raw))) !== fingerprint) {
        throw new Error('That secret did not open the vault.');
    }

    return raw;
}

test('either secret unwinds the vault, and both yield the same key', async () => {
    const passphrase = 'garden planet harbor island violet willow';
    const { dek, descriptor, recoveryDisplay } = await arm(passphrase);

    const parsed = parseRecoveryKey(recoveryDisplay);
    assert.ok(parsed !== null);

    const viaPassphrase = await exportKeyWith(
        await derivePassphraseKek(
            passphrase,
            base64ToBytes(descriptor.salt),
            descriptor.iterations,
        ),
        descriptor.wrappedPassphrase,
        descriptor.fingerprint,
    );

    // Forgetting the passphrase used to mean being stuck in the vault forever,
    // even with the recovery key in hand.
    const viaRecovery = await exportKeyWith(
        await deriveRecoveryKek(parsed, base64ToBytes(descriptor.recoverySalt)),
        descriptor.wrappedRecovery,
        descriptor.fingerprint,
    );

    assert.equal(viaPassphrase, dek);
    assert.equal(viaRecovery, dek);
});

test('a recovery key from another vault cannot unwind this one', async () => {
    const { descriptor } = await arm('garden planet harbor island violet willow');
    const other = await arm('almond bridge copper dragon engine forest');

    const parsed = parseRecoveryKey(other.recoveryDisplay);
    assert.ok(parsed !== null);

    const kek = await deriveRecoveryKek(
        parsed,
        base64ToBytes(descriptor.recoverySalt),
    );

    await assert.rejects(() =>
        exportKeyWith(
            kek,
            descriptor.wrappedRecovery,
            descriptor.fingerprint,
        ),
    );
});

test('a wrong passphrase fails, rather than silently returning junk', async () => {
    const { descriptor } = await arm('correct horse battery staple');

    await assert.rejects(() =>
        unlockWithPassphrase(descriptor, 'wrong horse battery staple'),
    );
});

/**
 * Copying a passphrase out of an input routinely picks up a trailing space, and
 * an untrimmed one derives a completely different key with no clue as to why.
 */
test('surrounding whitespace is forgiven', async () => {
    const passphrase = 'garden planet harbor island violet willow';
    const { dek, descriptor } = await arm(passphrase);

    assert.equal(await unlockWithPassphrase(descriptor, ` ${passphrase}`), dek);
    assert.equal(await unlockWithPassphrase(descriptor, `${passphrase} `), dek);
    assert.equal(await unlockWithPassphrase(descriptor, `\n${passphrase}\t`), dek);
});

test('whitespace inside the passphrase still matters', async () => {
    const { descriptor } = await arm('garden planet harbor');

    // Only the edges are forgiven — collapsing inner spaces would quietly shrink
    // the space of possible passphrases.
    await assert.rejects(() =>
        unlockWithPassphrase(descriptor, 'garden  planet harbor'),
    );
});
