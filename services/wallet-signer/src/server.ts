import { createServer as createHttpServer, type IncomingMessage, type ServerResponse } from 'node:http';
import { createServer as createSocketServer } from 'node:net';
import { rm } from 'node:fs/promises';
import { HDNodeWallet, JsonRpcProvider, Mnemonic, Wallet } from 'ethers';
import { RequestAuthenticator } from './auth.js';
import { config } from './config.js';
import { derivePublicAddresses, readKeystore, unlockKeystore } from './keystore.js';
import { SettlementRunner } from './settlement.js';
import { OperationStore } from './store.js';
import type { NetworkName, Operation, SettlementRequest } from './types.js';

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

/**
 * What the vaults look like from here.
 *
 * Reported rather than assumed because a Safe that was never deployed on a
 * given chain is an address with no code, and sweeping to it is a one-way trip.
 * `billing:verify-settlement` refuses to pass while any configured vault has no
 * bytecode behind it.
 */
const vaultSnapshot = async (): Promise<Record<string, unknown>> => {
    const entries = await Promise.all((Object.keys(config.networks) as NetworkName[]).map(async (name) => {
        const network = config.networks[name];
        if (network.vault === '' || network.rpcUrls.length === 0) {
            return [name, { configured: false }];
        }

        try {
            const provider = new JsonRpcProvider(network.rpcUrls[0], network.chainId, { staticNetwork: true });
            const [vaultCode, riskVaultCode] = await Promise.all([
                provider.getCode(network.vault),
                provider.getCode(network.riskVault),
            ]);
            return [name, {
                configured: true,
                vault: network.vault,
                riskVault: network.riskVault,
                segregated: network.riskVault !== network.vault,
                vaultHasCode: vaultCode !== '0x',
                riskVaultHasCode: riskVaultCode !== '0x',
            }];
        } catch {
            return [name, { configured: true, vault: network.vault, riskVault: network.riskVault, unreachable: true }];
        }
    }));

    return Object.fromEntries(entries);
};

const healthSnapshot = async (): Promise<Record<string, unknown>> => {
    const operations = await store.list();
    const now = Date.now();
    const spent = (since: number): bigint => operations
        .filter((operation) => new Date(operation.createdAt).getTime() >= since)
        .reduce((total, operation) => total + BigInt(operation.gasTopupWei ?? '0'), 0n);
    let gasWallet: Record<string, string> | null = null;

    if (unlockedMnemonic && config.networks.arbitrum.rpcUrls.length > 0) {
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

    return {
        ok: true,
        locked: unlockedMnemonic === undefined,
        keyVersion: keystore.keyVersion,
        gasWallet,
        vaults: await vaultSnapshot(),
        stuckOperations: operations.filter((operation) => operation.status === 'needs_review').length,
    };
};

const validRequest = (value: unknown): value is SettlementRequest => {
    if (!value || typeof value !== 'object') return false;
    const item = value as Record<string, unknown>;
    return item.kind === 'settlement'
        && typeof item.operationId === 'string' && typeof item.paymentId === 'string'
        && typeof item.network === 'string' && typeof item.chainId === 'number'
        && typeof item.derivationIndex === 'number' && typeof item.depositAddress === 'string'
        && typeof item.keyVersion === 'string' && typeof item.asset === 'string'
        && typeof item.verifiedAmount === 'string' && typeof item.chainVerified === 'boolean'
        && typeof item.screeningRisk === 'string' && typeof item.riskAuthorized === 'boolean';
};

/**
 * A recovery names an address and nothing else.
 *
 * It cannot name a destination — that comes from this service's own
 * configuration — so the worst an attacker with the HMAC secret can do here is
 * move our funds into our own risk vault ahead of schedule.
 */
const validRecovery = (value: unknown): value is Omit<SettlementRequest, 'kind'> & { reason: string } => {
    if (!value || typeof value !== 'object') return false;
    const item = value as Record<string, unknown>;
    return typeof item.operationId === 'string' && typeof item.network === 'string'
        && typeof item.chainId === 'number' && typeof item.derivationIndex === 'number'
        && typeof item.depositAddress === 'string' && typeof item.keyVersion === 'string'
        && typeof item.reason === 'string' && item.reason.length >= 3 && item.reason.length <= 500;
};

const accept = async (response: ServerResponse, request: SettlementRequest): Promise<void> => {
    const existing = await store.get(request.operationId);

    if (existing) {
        if (existing.paymentId !== request.paymentId || existing.kind !== request.kind) {
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

    await runner.validate(request);
    const now = new Date().toISOString();
    const operation: Operation = {
        ...request,
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
            await accept(response, payload);
            return;
        }

        if (method === 'POST' && path === '/v1/recoveries') {
            const payload: unknown = JSON.parse(body);
            if (!validRecovery(payload)) {
                json(response, 422, { error: 'invalid_recovery' });
                return;
            }
            await accept(response, {
                kind: 'recovery',
                operationId: payload.operationId,
                paymentId: payload.reason,
                network: payload.network,
                chainId: payload.chainId,
                derivationIndex: payload.derivationIndex,
                keyVersion: payload.keyVersion,
                depositAddress: payload.depositAddress,
                asset: 'eth',
                tokenContract: null,
                verifiedAmount: '0',
                chainVerified: false,
                screeningRisk: 'unscreened',
                riskAuthorized: false,
            });
            return;
        }

        const settlementMatch = path.match(/^\/v1\/(?:settlements|recoveries)\/([0-9a-f-]{36})$/i);
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
