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

test('only a clean screening result reaches the main vault', async () => {
    const network = { vault: '0xvault', riskVault: '0xriskvault' };
    const destination = (screeningRisk: string): string => runner().destination({ screeningRisk }, network);

    assert.equal(destination('no_match'), '0xvault');
    assert.equal(destination('flagged'), '0xriskvault');
    assert.equal(destination('unscreened'), '0xriskvault');
});
