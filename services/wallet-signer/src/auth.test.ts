import assert from 'node:assert/strict';
import { createHash, createHmac, randomUUID } from 'node:crypto';
import { mkdtemp, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';
import { RequestAuthenticator } from './auth.js';

test('HMAC authentication rejects nonce replay and stale timestamps', async () => {
    const directory = await mkdtemp(join(tmpdir(), 'signer-auth-'));
    const secret = 'a'.repeat(64);
    const secretFile = join(directory, 'secret');
    await writeFile(secretFile, secret);
    const authenticator = new RequestAuthenticator(secretFile, join(directory, 'nonces.json'));
    await authenticator.initialize();
    const method = 'POST';
    const path = '/v1/addresses/derive-batch';
    const body = '{"startIndex":0,"count":1}';
    const timestamp = String(Math.floor(Date.now() / 1000));
    const nonce = randomUUID();
    const canonical = [timestamp, nonce, method, path, createHash('sha256').update(body).digest('hex')].join('\n');
    const signature = createHmac('sha256', secret).update(canonical).digest('hex');
    const headers = { 'x-signer-timestamp': timestamp, 'x-signer-nonce': nonce, 'x-signer-signature': signature };
    assert.equal(await authenticator.verify(headers, method, path, body), true);
    assert.equal(await authenticator.verify(headers, method, path, body), false);
    assert.equal(await authenticator.verify({ ...headers, 'x-signer-timestamp': '1', 'x-signer-nonce': randomUUID() }, method, path, body), false);
});
