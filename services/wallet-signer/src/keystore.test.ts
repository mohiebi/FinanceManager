import assert from 'node:assert/strict';
import { mkdtemp } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';
import { deriveDepositAddress, derivePublicAddresses, initializeKeystore, unlockKeystore } from './keystore.js';

test('deposit derivation is deterministic and encrypted keystore unlocks', async () => {
    const directory = await mkdtemp(join(tmpdir(), 'signer-keystore-'));
    const path = join(directory, 'keystore.json');
    const mnemonic = await initializeKeystore(path, 'correct horse battery staple', 'test-v1');
    assert.equal(mnemonic.trim().split(/\s+/).length, 24);
    assert.equal(await unlockKeystore(path, 'correct horse battery staple'), mnemonic);
    const batch = await derivePublicAddresses(path, 7, 2);
    assert.equal(batch.keyVersion, 'test-v1');
    assert.equal(batch.addresses[0]?.address, deriveDepositAddress(mnemonic, 7));
    assert.notEqual(batch.addresses[0]?.address, batch.addresses[1]?.address);
    await assert.rejects(unlockKeystore(path, 'incorrect password'));
});
