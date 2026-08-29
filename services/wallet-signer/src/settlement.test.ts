import assert from 'node:assert/strict';
import { test } from 'node:test';
import { Transaction, Wallet } from 'ethers';
import { SettlementRunner } from './settlement.js';

/**
 * Reaching the private helpers is deliberate.
 *
 * Both behaviours below are about state the runner keeps between attempts, and
 * exercising them through `run()` would mean standing up a chain. What matters
 * is the decision, not the plumbing around it.
 */
type Internals = {
    isUnusable(provider: unknown, signer: unknown, raw: string): Promise<boolean>;
    sweepGasLimit(provider: unknown, from: string, to: string): Promise<bigint>;
    recoveryGasUnits(provider: unknown, from: string, to: string, transfers: unknown[]): Promise<bigint>;
    destination(operation: unknown, network: unknown): string;
};

const runner = (): Internals => new SettlementRunner({} as never, () => undefined) as unknown as Internals;

const signedTransaction = async (nonce: number): Promise<string> => {
    const wallet = new Wallet('0x'.padEnd(66, '1'));
    return wallet.signTransaction({ to: wallet.address, value: 1n, nonce, gasLimit: 21_000n, gasPrice: 1_000_000_000n, chainId: 42161 });
};

test('a mined-and-reverted transaction is discarded instead of re-read forever', async () => {
    const raw = await signedTransaction(4);
    const provider = {
        getTransactionReceipt: async () => ({ status: 0 }),
        getTransaction: async () => null,
        getTransactionCount: async () => 4,
    };

    assert.equal(await runner().isUnusable(provider, { address: '0x' }, raw), true);
});

test('a confirmed transaction is kept', async () => {
    const raw = await signedTransaction(4);
    const provider = {
        getTransactionReceipt: async () => ({ status: 1 }),
        getTransaction: async () => null,
        getTransactionCount: async () => 5,
    };

    assert.equal(await runner().isUnusable(provider, { address: '0x' }, raw), false);
});

test('a transaction still pending in the mempool is kept', async () => {
    const raw = await signedTransaction(4);
    const provider = {
        getTransactionReceipt: async () => null,
        getTransaction: async () => ({ hash: Transaction.from(raw).hash }),
        getTransactionCount: async () => 4,
    };

    assert.equal(await runner().isUnusable(provider, { address: '0x' }, raw), false);
});

test('a dropped transaction whose nonce was spent elsewhere is discarded', async () => {
    // The shared gas wallet makes this ordinary rather than exotic, and it used
    // to wedge the operation permanently: the cached signature could never be
    // accepted again, and nothing ever cleared it.
    const raw = await signedTransaction(4);
    const provider = {
        getTransactionReceipt: async () => null,
        getTransaction: async () => null,
        getTransactionCount: async () => 5,
    };

    assert.equal(await runner().isUnusable(provider, { address: '0x' }, raw), true);
});

test('the sweep gas limit follows the estimate rather than a hardcoded 21,000', async () => {
    // A Safe's receive() emits an event and runs well past 21,000, and Arbitrum
    // folds an L1 component into the units it charges. A fixed limit is an
    // out-of-gas revert on both counts.
    const safe = { estimateGas: async () => 34_000n };
    assert.equal(await runner().sweepGasLimit(safe, '0xfrom', '0xto'), 42_500n);

    const account = { estimateGas: async () => 21_000n };
    assert.equal(await runner().sweepGasLimit(account, '0xfrom', '0xto'), 26_250n);

    // Unused gas is refunded, so an unreachable estimate falls back generously
    // rather than guessing low and reverting.
    const broken = { estimateGas: async () => { throw new Error('unavailable'); } };
    assert.equal(await runner().sweepGasLimit(broken, '0xfrom', '0xto'), 100_000n);
});

test('recovery gas includes every token transfer and the final ETH sweep', async () => {
    const provider = {
        estimateGas: async (transaction: { to: string }) => transaction.to === '0xriskvault' ? 34_000n : 50_000n,
    };
    const transfers = [{ to: '0xtoken-one' }, { to: '0xtoken-two' }];

    // 34,000 buffered by 25% for the vault sweep, plus both token transfers.
    assert.equal(
        await runner().recoveryGasUnits(provider, '0xfrom', '0xriskvault', transfers),
        142_500n,
    );
});

test('only a clean screening result reaches the main vault', async () => {
    const network = { vault: '0xvault', riskVault: '0xriskvault' };
    const destination = (screeningRisk: string): string => runner().destination({ screeningRisk }, network);

    assert.equal(destination('no_match'), '0xvault');
    assert.equal(destination('flagged'), '0xriskvault');
    assert.equal(destination('unscreened'), '0xriskvault');
});

test('every operation is refused without a separate risk vault', async () => {
    assert.throws(
        () => runner().destination({ screeningRisk: 'flagged' }, { vault: '0xvault', riskVault: '' }),
        /risk_vault_not_segregated/,
    );
    assert.throws(
        () => runner().destination({ screeningRisk: 'unscreened' }, { vault: '0xvault', riskVault: '0xvault' }),
        /risk_vault_not_segregated/,
    );
    assert.throws(
        () => runner().destination({ screeningRisk: 'no_match' }, { vault: '0xvault', riskVault: '' }),
        /risk_vault_not_segregated/,
    );
});

/**
 * The resume behaviours below run through `enqueue`/`resume` with a stub store,
 * because what they assert is which operations the runner picks up and how many
 * times — not what happens on a chain once it does.
 */
type QueueInternals = {
    enqueue(operation: unknown): void;
    resume(): Promise<number>;
    run(operation: unknown): Promise<void>;
};

const operation = (operationId: string, status: string): Record<string, unknown> => ({
    operationId,
    status,
    kind: 'settlement',
    transactionHashes: {},
    signedTransactions: {},
});

const queueRunner = (stored: Array<Record<string, unknown>>): { runner: QueueInternals; ran: string[] } => {
    const ran: string[] = [];
    const store = { list: async () => stored, put: async () => undefined };
    const instance = new SettlementRunner(store as never, () => undefined) as unknown as QueueInternals;

    // Stand in for the real run so the assertions are about scheduling only.
    instance.run = async (item: unknown) => {
        ran.push((item as { operationId: string }).operationId);
    };

    return { runner: instance, ran };
};

test('operations orphaned by a restart are picked up when the signer is unlocked', async () => {
    // The queue lives in memory and the store does not, so a restart used to
    // leave these recorded as processing with nothing ever driving them again —
    // and a buyer's funds still sitting at a deposit address.
    const { runner: instance, ran } = queueRunner([
        operation('a', 'processing'),
        operation('b', 'submitted'),
        operation('c', 'retryable_failure'),
        operation('d', 'needs_review'),
        operation('e', 'completed'),
        operation('f', 'failed'),
    ]);

    assert.equal(await instance.resume(), 4);
    await new Promise((resolve) => setImmediate(resolve));

    assert.deepEqual(ran, ['a', 'b', 'c', 'd']);
});

test('an operation already in flight is never enqueued twice', async () => {
    // Two things now ask for an operation to move — a re-submission and resume
    // after unlock — and driving one twice concurrently would have each pass
    // re-reading balances the other is midway through changing.
    const { runner: instance, ran } = queueRunner([operation('a', 'processing')]);

    instance.enqueue(operation('a', 'processing'));
    await instance.resume();
    instance.enqueue(operation('a', 'processing'));
    await new Promise((resolve) => setImmediate(resolve));

    assert.deepEqual(ran, ['a']);
});

test('an operation that has finished running can be enqueued again', async () => {
    // The guard is against concurrent runs, not against ever retrying: a
    // retryable failure has to be able to come back.
    const { runner: instance, ran } = queueRunner([]);

    instance.enqueue(operation('a', 'submitted'));
    await new Promise((resolve) => setImmediate(resolve));
    instance.enqueue(operation('a', 'retryable_failure'));
    await new Promise((resolve) => setImmediate(resolve));

    assert.deepEqual(ran, ['a', 'a']);
});
