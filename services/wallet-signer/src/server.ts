import { createServer as createHttpServer, type IncomingMessage, type ServerResponse } from 'node:http';
import { createServer as createSocketServer } from 'node:net';
import { readFile, rm } from 'node:fs/promises';
import { HDNodeWallet, JsonRpcProvider, Mnemonic, Wallet } from 'ethers';
import { RequestAuthenticator } from './auth.js';
import { config } from './config.js';
import { derivePublicAddresses, readKeystore, unlockKeystore } from './keystore.js';
import { SettlementRunner } from './settlement.js';
import { OperationStore } from './store.js';
import type { Operation, SettlementRequest } from './types.js';

let unlockedMnemonic: string | undefined;
const store = new OperationStore(config.operationsPath);
const runner = new SettlementRunner(store, () => unlockedMnemonic);
const auth = new RequestAuthenticator(config.hmacSecretFile, '/operations/nonces.json');

const json = (response: ServerResponse, status: number, value: unknown): void => {
    response.writeHead(status, { 'content-type': 'application/json', 'cache-control': 'no-store' });
    response.end(JSON.stringify(value));
};

const readBody = async (request: IncomingMessage): Promise<string> => {
    const chunks: Buffer[] = [];
    let length = 0;
    for await (const chunk of request) {
        const value = Buffer.from(chunk);
        length += value.length;
        if (length > 65_536) throw new Error('Request body is too large.');
        chunks.push(value);
    }
    return Buffer.concat(chunks).toString('utf8');
};

const publicOperation = (operation: Operation): Omit<Operation, 'signedTransactions'> => {
    const { signedTransactions: _signedTransactions, ...safe } = operation;
    return safe;
};

const healthSnapshot = async (): Promise<Record<string, unknown>> => {
    const operations = await store.list();
    const now = Date.now();
    const spent = (since: number): bigint => operations
        .filter((operation) => new Date(operation.createdAt).getTime() >= since)
        .reduce((total, operation) => total + BigInt(operation.gasTopupWei ?? '0'), 0n);
    let gasWallet: Record<string, string> | null = null;

    if (unlockedMnemonic) {
        const root = HDNodeWallet.fromMnemonic(Mnemonic.fromPhrase(unlockedMnemonic), 'm');
        const provider = new JsonRpcProvider(config.networks.arbitrum.rpcUrls[0], config.networks.arbitrum.chainId, { staticNetwork: true });
        const wallet = new Wallet(root.derivePath("m/44'/60'/1'/0/0").privateKey, provider);
        gasWallet = {
            address: wallet.address.toLowerCase(),
            balanceWei: (await provider.getBalance(wallet.address)).toString(),
            spentHourlyWei: spent(now - 3_600_000).toString(),
            spentDailyWei: spent(now - 86_400_000).toString(),
        };
    }

    return { ok: true, locked: unlockedMnemonic === undefined, keyVersion: keystore.keyVersion, gasWallet };
};

const validRequest = (value: unknown): value is SettlementRequest => {
    if (!value || typeof value !== 'object') return false;
    const item = value as Record<string, unknown>;
    return typeof item.operationId === 'string' && typeof item.paymentId === 'string'
        && typeof item.network === 'string' && typeof item.chainId === 'number'
        && typeof item.derivationIndex === 'number' && typeof item.depositAddress === 'string'
        && typeof item.asset === 'string' && typeof item.verifiedAmount === 'string';
};

await auth.initialize();
const keystore = await readKeystore(config.keystorePath);
if (keystore.keyVersion !== config.keyVersion) throw new Error('Configured key version does not match the keystore.');

const server = createHttpServer(async (request, response) => {
    try {
        const method = request.method ?? 'GET';
        const path = new URL(request.url ?? '/', 'http://signer').pathname;
        const body = method === 'GET' ? '' : await readBody(request);

        if (!await auth.verify(request.headers, method, path, body)) {
            json(response, 401, { error: 'unauthorized' });
            return;
        }

        if (method === 'GET' && path === '/health') {
            json(response, 200, await healthSnapshot());
            return;
        }

        if (method === 'POST' && path === '/v1/addresses/derive-batch') {
            const payload = JSON.parse(body) as { startIndex?: unknown; count?: unknown };
            if (!Number.isSafeInteger(payload.startIndex) || Number(payload.startIndex) < 0 || !Number.isSafeInteger(payload.count) || Number(payload.count) < 1 || Number(payload.count) > 250) {
                json(response, 422, { error: 'invalid_derivation_range' });
                return;
            }
            const result = await derivePublicAddresses(config.keystorePath, Number(payload.startIndex), Number(payload.count));
            json(response, 200, { key_version: result.keyVersion, start_index: payload.startIndex, addresses: result.addresses });
            return;
        }

        if (method === 'POST' && path === '/v1/settlements') {
            const payload: unknown = JSON.parse(body);
            if (!validRequest(payload)) {
                json(response, 422, { error: 'invalid_settlement' });
                return;
            }
            const existing = await store.get(payload.operationId);
            if (existing) {
                if (existing.paymentId !== payload.paymentId) {
                    json(response, 409, { error: 'operation_id_conflict' });
                    return;
                }
                if (existing.status === 'retryable_failure') {
                    existing.status = 'submitted';
                    existing.failureCode = undefined;
                    existing.failureReason = undefined;
                    existing.updatedAt = new Date().toISOString();
                    await store.put(existing);
                    runner.enqueue(existing);
                }
                json(response, 200, publicOperation(existing));
                return;
            }
            await runner.validate(payload);
            const now = new Date().toISOString();
            const operation: Operation = {
                ...payload,
                status: 'submitted',
                stage: 'accepted',
                createdAt: now,
                updatedAt: now,
                transactionHashes: {},
                signedTransactions: {},
            };
            await store.put(operation);
            runner.enqueue(operation);
            json(response, 202, publicOperation(operation));
            return;
        }

        const settlementMatch = path.match(/^\/v1\/settlements\/([0-9a-f-]{36})$/i);
        if (method === 'GET' && settlementMatch?.[1]) {
            const operation = await store.get(settlementMatch[1]);
            json(response, operation ? 200 : 404, operation ? publicOperation(operation) : { error: 'not_found' });
            return;
        }

        json(response, 404, { error: 'not_found' });
    } catch (error) {
        json(response, 500, { error: 'internal_error', message: error instanceof Error ? error.message : 'Unknown error.' });
    }
});

await rm(config.controlSocket, { force: true });
const control = createSocketServer((socket) => {
    const chunks: Buffer[] = [];
    socket.on('data', (chunk) => chunks.push(Buffer.from(chunk)));
    socket.on('end', async () => {
        try {
            const command = JSON.parse(Buffer.concat(chunks).toString('utf8')) as { command?: string; passphrase?: string };
            if (command.command !== 'unlock' || typeof command.passphrase !== 'string') throw new Error('Invalid control command.');
            const mnemonic = await unlockKeystore(config.keystorePath, command.passphrase);
            HDNodeWallet.fromMnemonic(Mnemonic.fromPhrase(mnemonic), 'm');
            unlockedMnemonic = mnemonic;
            socket.end(JSON.stringify({ ok: true }));
        } catch {
            socket.end(JSON.stringify({ ok: false }));
        }
    });
});

control.listen(config.controlSocket);
server.listen(config.port, '0.0.0.0');

const shutdown = (): void => {
    unlockedMnemonic = undefined;
    control.close();
    server.close();
};
process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);
