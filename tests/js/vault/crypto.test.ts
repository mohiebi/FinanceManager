import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

import {
    aadFor,
    decrypt,
    encrypt,
    importDek,
    looksEncrypted,
} from '../../../resources/js/lib/vault/crypto.ts';

/**
 * The same fixture tests/Unit/UserCryptoTest.php asserts against. If PHP and the
 * browser ever disagree about the wire format, both suites fail and name the
 * exact vector — rather than a user discovering it when their data will not open.
 */
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

test('it decrypts every blob PHP produced', async () => {
    assert.ok(vectors.encryption.length > 0);

    for (const vector of vectors.encryption) {
        const dek = await importDek(hexToBytes(vector.dek_hex));

        assert.equal(
            await decrypt(vector.blob, dek, vector.aad),
            vector.plaintext,
            `vector: ${vector.name}`,
        );
    }
});

test('it round-trips its own output', async () => {
    const dek = await importDek(crypto.getRandomValues(new Uint8Array(32)));
    const aad = aadFor('transactions', 'title');

    const blob = await encrypt('Weekly groceries', dek, aad);

    assert.equal(await decrypt(blob, dek, aad), 'Weekly groceries');
});

test('the same plaintext encrypts differently every time', async () => {
    const dek = await importDek(crypto.getRandomValues(new Uint8Array(32)));
    const aad = aadFor('transactions', 'amount');

    const first = await encrypt('1250.00', dek, aad);
    const second = await encrypt('1250.00', dek, aad);

    assert.notEqual(first, second);
    assert.equal(await decrypt(first, dek, aad), '1250.00');
    assert.equal(await decrypt(second, dek, aad), '1250.00');
});

test('a ciphertext bound to one column will not open as another', async () => {
    const dek = await importDek(crypto.getRandomValues(new Uint8Array(32)));

    const blob = await encrypt('Rent', dek, aadFor('transactions', 'title'));

    await assert.rejects(() =>
        decrypt(blob, dek, aadFor('transactions', 'amount')),
    );
});

test('another key will not open it', async () => {
    const aad = aadFor('transactions', 'title');
    const mine = await importDek(crypto.getRandomValues(new Uint8Array(32)));
    const theirs = await importDek(crypto.getRandomValues(new Uint8Array(32)));

    const blob = await encrypt('Rent', mine, aad);

    await assert.rejects(() => decrypt(blob, theirs, aad));
});

test('tampering is detected', async () => {
    const dek = await importDek(crypto.getRandomValues(new Uint8Array(32)));
    const aad = aadFor('transactions', 'title');
    const blob = await encrypt('Rent', dek, aad);

    const raw = atob(blob).split('');
    raw[raw.length - 1] = String.fromCharCode(
        raw[raw.length - 1]!.charCodeAt(0) ^ 0x01,
    );

    await assert.rejects(() => decrypt(btoa(raw.join('')), dek, aad));
});

test('malformed input is rejected rather than misread', async () => {
    const dek = await importDek(crypto.getRandomValues(new Uint8Array(32)));
    const aad = aadFor('transactions', 'title');

    await assert.rejects(() => decrypt('not base64 !!!', dek, aad));
    await assert.rejects(() => decrypt(btoa('xx9short'), dek, aad));
    await assert.rejects(() => decrypt(btoa('zzz' + 'a'.repeat(40)), dek, aad));
});

test('it recognises its own blobs and passes over plaintext', async () => {
    const dek = await importDek(crypto.getRandomValues(new Uint8Array(32)));
    const blob = await encrypt('Rent', dek, aadFor('bills', 'title'));

    assert.equal(looksEncrypted(blob), true);
    assert.equal(looksEncrypted('Rent'), false);
    assert.equal(looksEncrypted(''), false);
    assert.equal(looksEncrypted(null), false);
});

test('the aad is namespaced by table and column', () => {
    assert.equal(aadFor('transactions', 'amount'), 'cp1:transactions:amount');
    assert.equal(aadFor('bills', 'title'), 'cp1:bills:title');
});
