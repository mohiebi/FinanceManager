import { createConnection } from 'node:net';
import { mkdir } from 'node:fs/promises';
import { dirname } from 'node:path';
import { initializeKeystore } from './keystore.js';

const readStdin = async (): Promise<string> => {
    const chunks: Buffer[] = [];
    for await (const chunk of process.stdin) chunks.push(Buffer.from(chunk));
    return Buffer.concat(chunks).toString('utf8').trim();
};

const command = process.argv[2];
const keystorePath = process.env.KEYSTORE_PATH ?? '/data/keystore.json';
const controlSocket = process.env.CONTROL_SOCKET ?? '/tmp/wallet-signer-control.sock';
const passphrase = await readStdin();

if (command === 'init') {
    await mkdir(dirname(keystorePath), { recursive: true });
    const mnemonic = await initializeKeystore(keystorePath, passphrase, process.env.KEY_VERSION ?? 'v1');
    process.stdout.write(`BACK UP THESE 24 WORDS OFFLINE NOW. THEY WILL NOT BE SHOWN AGAIN.\n${mnemonic}\n`);
} else if (command === 'unlock') {
    const result = await new Promise<string>((resolve, reject) => {
        const socket = createConnection(controlSocket);
        const chunks: Buffer[] = [];
        socket.on('connect', () => socket.end(JSON.stringify({ command: 'unlock', passphrase })));
        socket.on('data', (chunk) => chunks.push(Buffer.from(chunk)));
        socket.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
        socket.on('error', reject);
    });
    const parsed = JSON.parse(result) as { ok?: boolean; resumed?: number };
    if (!parsed.ok) throw new Error('Unlock failed.');
    process.stdout.write('Signer unlocked until this container stops.\n');
    if (parsed.resumed) process.stdout.write(`Resumed ${parsed.resumed} operation(s) left in flight by the last shutdown.\n`);
} else {
    throw new Error('Usage: cli init|unlock (passphrase on stdin)');
}
