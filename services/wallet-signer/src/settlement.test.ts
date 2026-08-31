import assert from 'node:assert/strict';
import { test } from 'node:test';
import { Transaction, Wallet, keccak256 } from 'ethers';
import { SettlementRunner } from './settlement.js';

/**
 * Reaching the private helpers is deliberate.
 *
 * Both behaviours below are about state the runner keeps between attempts, and
 * exercising them through `run()` would mean standing up a chain. What matters
 * is the decision, not the plumbing around it.
 */
type Internals = {
    transactionDisposition(provider: unknown, signer: unknown, raw: string, signedAt: string, stage: string): Promise<string>;
    replacementTransaction(provider: unknown, transaction: unknown, previous: Transaction, replacementNonce: number | undefined): Promise<Record<string, unknown>>;
    requiredGasTopup(operation: unknown, targetBalance: bigint, currentBalance: bigint): { topup: bigint; cumulative: bigint } | null;
    stageConfirmed(provider: unknown, operation: unknown, stage: string): Promise<boolean>;
    assertCode(network: unknown, provider: unknown, addresses: string[]): Promise<void>;
    sweep(operation: unknown, deposit: unknown, network: unknown): Promise<void>;
    sweepGasLimit(provider: unknown, from: string, to: string): Promise<bigint>;
    recoveryGasUnits(provider: unknown, from: string, to: string, transfers: unknown[]): Promise<bigint>;
    destination(operation: unknown, network: unknown): string;
};

const runner = (): Internals => new SettlementRunner({} as never, () => undefined) as unknown as Internals;

const signedTransaction = async (nonce: number): Promise<string> => {
    const wallet = new Wallet('0x'.padEnd(66, '1'));

    return wallet.signTransaction({
        to: wallet.address,
        value: 1n,
        nonce,
        gasLimit: 21_000n,
        gasPrice: 1_000_000_000n,
        chainId: 42161,
    });
};

test('a mined-and-reverted transaction is replaced at the next nonce', async () => {
    const raw = await signedTransaction(4);
    const provider = {
        getTransactionReceipt: async () => ({ status: 0 }),
        getTransaction: async () => null,
        getTransactionCount: async () => 4,
    };

    assert.equal(await runner().transactionDisposition(provider, { address: '0x' }, raw, new Date().toISOString(), 'approval'), 'replace_next_nonce');
});

test('a confirmed transaction is kept', async () => {
    const raw = await signedTransaction(4);
    const provider = {
        getTransactionReceipt: async () => ({ status: 1 }),
        getTransaction: async () => null,
        getTransactionCount: async () => 5,
    };

    assert.equal(await runner().transactionDisposition(provider, { address: '0x' }, raw, new Date().toISOString(), 'approval'), 'keep');
});

test('a transaction still pending in the mempool is kept', async () => {
    const raw = await signedTransaction(4);
    const provider = {
        getTransactionReceipt: async () => null,
        getTransaction: async () => ({ hash: Transaction.from(raw).hash }),
        getTransactionCount: async () => 4,
    };

    assert.equal(await runner().transactionDisposition(provider, { address: '0x' }, raw, new Date().toISOString(), 'approval'), 'keep');
});

test('a dropped transaction whose nonce was spent elsewhere moves to the next nonce', async () => {
    // The shared gas wallet makes this ordinary rather than exotic, and it used
    // to wedge the operation permanently: the cached signature could never be
    // accepted again, and nothing ever cleared it.
    const raw = await signedTransaction(4);
    const provider = {
        getTransactionReceipt: async () => null,
        getTransaction: async () => null,
        getTransactionCount: async () => 5,
    };

    assert.equal(await runner().transactionDisposition(provider, { address: '0x' }, raw, new Date().toISOString(), 'approval'), 'replace_next_nonce');
});

test('a dropped transaction with an unspent nonce is fee bumped at the same nonce', async () => {
    const raw = await signedTransaction(4);
    const provider = {
        getTransactionReceipt: async () => null,
        getTransaction: async () => null,
        getTransactionCount: async () => 4,
    };

    assert.equal(await runner().transactionDisposition(provider, { address: '0x' }, raw, new Date().toISOString(), 'approval'), 'replace_same_nonce');
});

test('a pending swap older than its quote is replaced with freshly supplied calldata', async () => {
    const raw = await signedTransaction(4);
    const provider = {
        getTransactionReceipt: async () => null,
        getTransaction: async () => ({ hash: Transaction.from(raw).hash }),
        getTransactionCount: async () => 4,
        getFeeData: async () => ({
            gasPrice: 2_000_000_000n,
            maxFeePerGas: null,
            maxPriorityFeePerGas: null,
        }),
    };
    const internals = runner();

    assert.equal(await internals.transactionDisposition(provider, { address: '0x' }, raw, new Date(0).toISOString(), 'swap'), 'replace_same_nonce');

    const replacement = await internals.replacementTransaction(provider, { to: '0x0000000000000000000000000000000000000001', data: '0x1234' }, Transaction.from(raw), 4);

    assert.equal(replacement.nonce, 4);
    assert.equal(replacement.data, '0x1234');
    assert.ok((replacement.gasPrice as bigint) > 1_000_000_000n);
});

test('a retry funds only the missing gas and counts it against the cumulative cap', () => {
    const internals = runner();

    assert.deepEqual(internals.requiredGasTopup({ gasTopupWei: '100' }, 1_000n, 400n), {
        topup: 600n,
        cumulative: 700n,
    });
    assert.equal(internals.requiredGasTopup({ gasTopupWei: '700' }, 1_000n, 1_000n), null);
});

test('a confirmed vault sweep resumes successfully even when only dust remains', async () => {
    const store = { put: async () => undefined };
    const instance = new SettlementRunner(store as never, () => undefined) as unknown as Internals;
    const operation = {
        screeningRisk: 'no_match',
        transactionHashes: { vault_sweep: `0x${'a'.repeat(64)}` },
        signedTransactions: {},
    };
    const provider = {
        getFeeData: async () => ({ gasPrice: 1n, maxFeePerGas: null }),
        estimateGas: async () => 21_000n,
        getBalance: async () => 1n,
        getTransactionReceipt: async () => ({ status: 1 }),
    };

    await instance.sweep(operation, { provider, address: '0xdeposit' }, { vault: '0xvault', riskVault: '0xriskvault' });

    assert.equal((operation as { vaultReceipt?: string }).vaultReceipt, `0x${'a'.repeat(64)}`);
});

test('allowlisted contracts require an exact configured runtime bytecode hash', async () => {
    const code = '0x6000';
    const address = '0x0000000000000000000000000000000000000001';
    const provider = { getCode: async () => code };
    const internals = runner();

    await internals.assertCode({ expectedCodeHashes: { [address]: keccak256(code) } }, provider, [address]);
    await assert.rejects(internals.assertCode({ expectedCodeHashes: {} }, provider, [address]), /contract_bytecode_hash_unconfigured/);
    await assert.rejects(internals.assertCode({ expectedCodeHashes: { [address]: `0x${'0'.repeat(64)}` } }, provider, [address]), /contract_bytecode_mismatch/);
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
    const broken = {
        estimateGas: async () => {
            throw new Error('unavailable');
        },
    };
    assert.equal(await runner().sweepGasLimit(broken, '0xfrom', '0xto'), 100_000n);
});

test('recovery gas includes every token transfer and the final ETH sweep', async () => {
    const provider = {
        estimateGas: async (transaction: { to: string }) => (transaction.to === '0xriskvault' ? 34_000n : 50_000n),
    };
    const transfers = [{ to: '0xtoken-one' }, { to: '0xtoken-two' }];

    // 34,000 buffered by 25% for the vault sweep, plus both token transfers.
    assert.equal(await runner().recoveryGasUnits(provider, '0xfrom', '0xriskvault', transfers), 142_500n);
});

test('only a clean screening result reaches the main vault', async () => {
    const network = { vault: '0xvault', riskVault: '0xriskvault' };
    const destination = (screeningRisk: string): string => runner().destination({ screeningRisk }, network);

    assert.equal(destination('no_match'), '0xvault');
    assert.equal(destination('flagged'), '0xriskvault');
    assert.equal(destination('unscreened'), '0xriskvault');
});

test('every operation is refused without a separate risk vault', async () => {
    assert.throws(() => runner().destination({ screeningRisk: 'flagged' }, { vault: '0xvault', riskVault: '' }), /risk_vault_not_segregated/);
    assert.throws(() => runner().destination({ screeningRisk: 'unscreened' }, { vault: '0xvault', riskVault: '0xvault' }), /risk_vault_not_segregated/);
    assert.throws(() => runner().destination({ screeningRisk: 'no_match' }, { vault: '0xvault', riskVault: '' }), /risk_vault_not_segregated/);
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

type SwapInternals = {
    swapIsPending(settleAmount: bigint, balance: bigint): boolean;
};

const swapRunner = (): SwapInternals => new SettlementRunner({} as never, () => undefined) as unknown as SwapInternals;

test('a settlement whose swap already confirmed resumes at the sweep', async () => {
    // The regression this pins: an attempt that failed after its swap confirmed
    // came back to a zero token balance, which the old equality check rejected
    // as a mismatch — parking the ether the swap had just bought behind a
    // needs_review that no retry could ever clear.
    assert.equal(swapRunner().swapIsPending(5_000_000n, 0n), false);
});

test('a settlement whose swap never ran still performs it', () => {
    assert.equal(swapRunner().swapIsPending(5_000_000n, 5_000_000n), true);
});

test('a balance the settlement cannot account for is still refused', () => {
    // Funds that arrived after the verified transfer must not be swept into a
    // settlement priced on a different amount, in either direction.
    for (const balance of [4_999_999n, 5_000_001n, 9_000_000n]) {
        assert.throws(() => swapRunner().swapIsPending(5_000_000n, balance), /token_balance_mismatch/);
    }
});
