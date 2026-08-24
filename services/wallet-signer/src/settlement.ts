import {
    Contract,
    FallbackProvider,
    HDNodeWallet,
    Interface,
    JsonRpcProvider,
    Mnemonic,
    Transaction,
    Wallet,
    type Provider,
    type TransactionReceipt,
    type TransactionRequest,
    getAddress,
    solidityPacked,
} from 'ethers';
import { config, type NetworkConfig } from './config.js';
import { OperationStore } from './store.js';
import type { AssetName, Operation, SettlementRequest } from './types.js';

const erc20Abi = [
    'function balanceOf(address) view returns (uint256)',
    'function allowance(address,address) view returns (uint256)',
    'function approve(address,uint256) returns (bool)',
    'function transfer(address,uint256) returns (bool)',
    'function decimals() view returns (uint8)',
];
const quoterAbi = [
    'function quoteExactInput(bytes path,uint256 amountIn) returns (uint256 amountOut,uint160[] sqrtPriceX96AfterList,uint32[] initializedTicksCrossedList,uint256 gasEstimate)',
];
const routerAbi = [
    'function exactInput((bytes path,address recipient,uint256 amountIn,uint256 amountOutMinimum)) payable returns (uint256 amountOut)',
    'function unwrapWETH9(uint256 amountMinimum,address recipient) payable',
    'function multicall(bytes[] data) payable returns (bytes[] results)',
];
const feedAbi = [
    'function decimals() view returns (uint8)',
    'function latestRoundData() view returns (uint80,int256,uint256,uint256,uint80)',
];

export class SettlementRunner {
    private running = Promise.resolve();

    public constructor(
        private readonly store: OperationStore,
        private readonly mnemonic: () => string | undefined,
    ) {}

    public enqueue(operation: Operation): void {
        this.running = this.running.then(() => this.run(operation)).catch(() => undefined);
    }

    public async validate(request: SettlementRequest): Promise<void> {
        if (!Number.isSafeInteger(request.derivationIndex) || request.derivationIndex < 0) throw new Error('Invalid derivation index.');
        if (request.keyVersion !== config.keyVersion) throw new Error('Unknown key version.');

        const phrase = this.mnemonic();
        if (!phrase) throw new Error('Signer is locked.');
        const root = HDNodeWallet.fromMnemonic(Mnemonic.fromPhrase(phrase), 'm');
        const derived = root.derivePath(`m/44'/60'/0'/0/${request.derivationIndex}`);
        if (derived.address.toLowerCase() !== getAddress(request.depositAddress).toLowerCase()) throw new Error('Deposit address does not match derivation path.');

        const network: NetworkConfig = config.networks[request.network];
        if (!network || request.chainId !== network.chainId) throw new Error('Unknown network or chain ID.');
        if (network.rpcUrls.length === 0 || network.vault === '') throw new Error('Network is not configured for settlement.');

        // A recovery carries no payment, so none of the payment invariants below
        // apply to it. Proving the address came off our own derivation path is
        // the whole check, and its funds go to the risk vault because nothing
        // ever screened them.
        if (request.kind === 'recovery') return;

        if (!request.chainVerified) throw new Error('Payment has not been chain verified.');
        if (request.screeningRisk === 'flagged' && !request.riskAuthorized) throw new Error('Flagged settlement is not authorized.');
        if (!['no_match', 'flagged'].includes(request.screeningRisk)) throw new Error('Screening result is not settleable.');
        if (!/^\d+$/.test(request.verifiedAmount) || BigInt(request.verifiedAmount) <= 0n) throw new Error('Invalid verified amount.');
        if (request.network === 'ethereum' && request.asset !== 'eth') throw new Error('Ethereum accepts ETH only.');
        if (request.asset !== 'eth' && network.tokens[request.asset]?.toLowerCase() !== request.tokenContract?.toLowerCase()) throw new Error('Token is not allowlisted.');
    }

    private async run(operation: Operation): Promise<void> {
        try {
            operation.status = 'processing';
            operation.updatedAt = new Date().toISOString();
            await this.store.put(operation);
            await this.validate(operation);
            const phrase = this.mnemonic();
            if (!phrase) throw new Error('Signer is locked.');
            const root = HDNodeWallet.fromMnemonic(Mnemonic.fromPhrase(phrase), 'm');
            const network: NetworkConfig = config.networks[operation.network];
            const provider = this.provider(network);
            const actualNetwork = await provider.getNetwork();
            if (Number(actualNetwork.chainId) !== network.chainId) throw new Error('RPC returned the wrong chain ID.');
            const deposit = new Wallet(root.derivePath(`m/44'/60'/0'/0/${operation.derivationIndex}`).privateKey, provider);

            if (operation.kind === 'recovery') {
                await this.recover(operation, deposit, root, provider, network);
            } else if (operation.asset === 'eth') {
                await this.sweep(operation, deposit, network);
            } else {
                await this.swapAndSweep(operation, deposit, root, provider, network);
            }

            operation.status = 'completed';
            operation.stage = 'completed';
            operation.updatedAt = new Date().toISOString();
            await this.store.put(operation);
        } catch (error) {
            operation.status = this.needsReview(error) ? 'needs_review' : 'retryable_failure';
            operation.failureCode = this.errorCode(error);
            operation.failureReason = error instanceof Error ? error.message : 'Unknown settlement failure.';
            operation.updatedAt = new Date().toISOString();
            await this.store.put(operation);
        }
    }

    private async swapAndSweep(operation: Operation, deposit: Wallet, root: HDNodeWallet, provider: FallbackProvider, network: NetworkConfig): Promise<void> {
        if (!network.router || !network.quoter || !network.weth || !operation.tokenContract) throw new Error('swap_not_configured');
        await this.assertCode(provider, [network.router, network.quoter, network.weth, operation.tokenContract]);
        const token = new Contract(operation.tokenContract, erc20Abi, deposit);
        const asset = operation.asset as 'usdt' | 'usdc';
        const expected = BigInt(operation.verifiedAmount);
        const balance = BigInt(await token.getFunction('balanceOf').staticCall(deposit.address));
        operation.remainingTokenBalance = balance.toString();

        // Less than the payment verified against means the deposit is not what
        // we were told it was, and that is worth a person's attention. Anything
        // at or above it settles in full: an overpayment, a dust transfer, and
        // somebody adding a single wei to strand the sweep are all just balance
        // to be moved.
        if (balance < expected) throw new Error('token_balance_mismatch');

        if (!operation.settleAmount) {
            operation.settleAmount = balance.toString();
            await this.store.put(operation);
        }

        const amount = BigInt(operation.settleAmount);
        const quote = await this.bestQuote(network, provider, asset, amount);
        await this.assertReferencePrice(network, provider, asset, amount, quote.amountOut);
        operation.quotedEth = quote.amountOut.toString();
        operation.minimumEth = (quote.amountOut * (10_000n - config.maxSlippageBps) / 10_000n).toString();
        operation.quoteExpiresAt = new Date(Date.now() + config.quoteLifetimeSeconds * 1000).toISOString();
        await this.store.put(operation);

        await token.getFunction('approve').staticCall(network.router, amount);
        await this.fundGas(operation, deposit, root, provider, 70_000n + quote.gasEstimate + 100_000n);

        const allowance = BigInt(await token.getFunction('allowance').staticCall(deposit.address, network.router));
        if (allowance !== amount) {
            if (allowance !== 0n) throw new Error('unexpected_allowance');
            await this.broadcast(operation, 'approval', deposit, await token.getFunction('approve').populateTransaction(network.router, amount));
        }

        const routerInterface = new Interface(routerAbi);
        const swapCall = routerInterface.encodeFunctionData('exactInput', [{
            path: quote.path,
            recipient: network.router,
            amountIn: amount,
            amountOutMinimum: BigInt(operation.minimumEth),
        }]);
        const unwrapCall = routerInterface.encodeFunctionData('unwrapWETH9', [BigInt(operation.minimumEth), deposit.address]);
        if (!operation.quoteExpiresAt || new Date(operation.quoteExpiresAt).getTime() <= Date.now()) throw new Error('stale_quote');
        await this.broadcast(operation, 'swap', deposit, {
            to: network.router,
            data: routerInterface.encodeFunctionData('multicall', [[swapCall, unwrapCall]]),
        });

        const remainingToken = BigInt(await token.getFunction('balanceOf').staticCall(deposit.address));
        operation.remainingTokenBalance = remainingToken.toString();
        operation.receivedEth = (await provider.getBalance(deposit.address)).toString();

        // The ether reaches the vault before any residue is reported, so a token
        // that landed mid-swap parks a review rather than the money.
        await this.sweep(operation, deposit, network);

        if (remainingToken !== 0n) throw new Error('token_balance_not_zero');
    }

    /**
     * Move whatever is stranded at a derived address somewhere safe.
     *
     * Tokens are transferred as they are rather than swapped: recovery is about
     * getting funds out of an address nothing else will ever touch again, not
     * about pricing them.
     */
    private async recover(operation: Operation, deposit: Wallet, root: HDNodeWallet, provider: FallbackProvider, network: NetworkConfig): Promise<void> {
        const destination = this.destination(operation, network);
        const balances: Array<{ asset: AssetName; contract: string; balance: bigint }> = [];

        for (const [name, contract] of Object.entries(network.tokens)) {
            if (!contract) continue;
            const token = new Contract(contract, erc20Abi, provider);
            const balance = BigInt(await token.getFunction('balanceOf').staticCall(deposit.address));
            if (balance > 0n) balances.push({ asset: name as AssetName, contract, balance });
        }

        if (balances.length > 0) {
            await this.fundGas(operation, deposit, root, provider, 70_000n * BigInt(balances.length));
        }

        for (const entry of balances) {
            const token = new Contract(entry.contract, erc20Abi, deposit);
            await this.broadcast(
                operation,
                `recover_${entry.asset}`,
                deposit,
                await token.getFunction('transfer').populateTransaction(destination, entry.balance),
            );
        }

        let residue = 0n;
        for (const entry of balances) {
            const token = new Contract(entry.contract, erc20Abi, provider);
            residue += BigInt(await token.getFunction('balanceOf').staticCall(deposit.address));
        }
        operation.remainingTokenBalance = residue.toString();

        await this.sweep(operation, deposit, network);

        if (residue !== 0n) throw new Error('token_balance_not_zero');
    }

    private async fundGas(operation: Operation, deposit: Wallet, root: HDNodeWallet, provider: FallbackProvider, gasUnits: bigint): Promise<void> {
        const feeData = await provider.getFeeData();
        const feePerGas = feeData.maxFeePerGas ?? feeData.gasPrice;
        if (!feePerGas) throw new Error('fee_data_unavailable');
        const topup = gasUnits * feePerGas * config.gasBufferBps / 10_000n;
        if (topup > config.maxGasTopupWei) throw new Error('gas_topup_limit');
        const gasWallet = new Wallet(root.derivePath("m/44'/60'/1'/0/0").privateKey, provider);

        if (!operation.gasTopupWei) {
            await this.assertGasLimits(topup);
            const gasBalance = await provider.getBalance(gasWallet.address);
            if (gasBalance < topup) throw new Error('gas_wallet_low_balance');
            operation.gasTopupWei = topup.toString();
            await this.store.put(operation);
        }

        await this.broadcast(operation, 'gas_topup', gasWallet, { to: deposit.address, value: BigInt(operation.gasTopupWei) });
    }

    private async sweep(operation: Operation, deposit: Wallet, network: NetworkConfig): Promise<void> {
        const provider = deposit.provider;
        if (!provider) throw new Error('provider_unavailable');
        const destination = this.destination(operation, network);
        const feeData = await provider.getFeeData();
        const feePerGas = feeData.maxFeePerGas ?? feeData.gasPrice;
        if (!feePerGas) throw new Error('fee_data_unavailable');
        const gasLimit = await this.sweepGasLimit(provider, deposit.address, destination);
        const fee = gasLimit * feePerGas * config.gasBufferBps / 10_000n;
        let balance = await provider.getBalance(deposit.address);
        if (balance <= fee) throw new Error('balance_below_final_fee');
        await this.broadcast(operation, 'vault_sweep', deposit, { to: destination, value: balance - fee, gasLimit });
        operation.vaultReceipt = operation.transactionHashes.vault_sweep;
        balance = await provider.getBalance(deposit.address);

        if (balance > config.dustWei && balance > fee) {
            await this.broadcast(operation, 'cleanup_sweep', deposit, { to: destination, value: balance - fee, gasLimit });
            balance = await provider.getBalance(deposit.address);
        }
        operation.remainingEthWei = balance.toString();
    }

    /**
     * What a plain value transfer to this destination actually costs.
     *
     * Never the 21,000 a transfer between two accounts costs. A Safe's
     * `receive()` emits an event and runs well past it, and Arbitrum folds an
     * L1 component into the units it charges, so a fixed limit is an
     * out-of-gas revert on both counts.
     */
    private async sweepGasLimit(provider: Provider, from: string, to: string): Promise<bigint> {
        try {
            const estimate = await provider.estimateGas({ from, to, value: 1n });
            const buffered = estimate * config.gasBufferBps / 10_000n;
            return buffered > 21_000n ? buffered : 21_000n;
        } catch {
            // Unused gas is refunded, so a generous ceiling costs nothing beyond
            // a slightly larger reserve held back from the sweep.
            return 100_000n;
        }
    }

    /**
     * Where this operation's funds belong.
     *
     * Only a clean screening result reaches the main vault. A flagged payment an
     * administrator accepted and a recovery nothing ever screened both go to the
     * risk vault, so the answer to "what is in the vault" stays "screened funds"
     * rather than "screened funds and whatever else we decided to keep".
     */
    private destination(operation: Operation, network: NetworkConfig): string {
        return operation.screeningRisk === 'no_match' ? network.vault : network.riskVault;
    }

    private async bestQuote(network: NetworkConfig, provider: FallbackProvider, asset: 'usdt' | 'usdc', amount: bigint): Promise<{ path: string; amountOut: bigint; gasEstimate: bigint }> {
        const token = network.tokens[asset];
        if (!token || !network.weth || !network.quoter) throw new Error('quote_not_configured');
        const quoter = new Contract(network.quoter, quoterAbi, provider);
        const paths = config.feeTiers.map((fee) => solidityPacked(['address', 'uint24', 'address'], [token, fee, network.weth]));
        if (asset === 'usdt' && network.tokens.usdc) {
            for (const firstFee of config.feeTiers) {
                for (const secondFee of config.feeTiers) {
                    paths.push(solidityPacked(['address', 'uint24', 'address', 'uint24', 'address'], [token, firstFee, network.tokens.usdc, secondFee, network.weth]));
                }
            }
        }

        let best: { path: string; amountOut: bigint; gasEstimate: bigint } | undefined;
        for (const path of paths) {
            try {
                const result = await quoter.getFunction('quoteExactInput').staticCall(path, amount);
                const amountOut = BigInt(result[0]);
                if (!best || amountOut > best.amountOut) best = { path, amountOut, gasEstimate: BigInt(result[3]) };
            } catch {
                continue;
            }
        }
        if (!best || best.amountOut <= 0n) throw new Error('missing_liquidity');
        return best;
    }

    private async assertReferencePrice(network: NetworkConfig, provider: FallbackProvider, asset: 'usdt' | 'usdc', amount: bigint, quotedEth: bigint): Promise<void> {
        const stableFeed = network.priceFeeds[asset];
        const ethFeed = network.priceFeeds.eth;
        if (!stableFeed || !ethFeed || !network.tokens[asset]) throw new Error('price_feed_not_configured');
        await this.assertCode(provider, [stableFeed, ethFeed]);
        const read = async (feedAddress: string): Promise<{ value: bigint; decimals: bigint; updatedAt: bigint }> => {
            const feed = new Contract(feedAddress, feedAbi, provider);
            const [decimals, round] = await Promise.all([
                feed.getFunction('decimals').staticCall(),
                feed.getFunction('latestRoundData').staticCall(),
            ]);
            return { value: BigInt(round[1]), decimals: BigInt(decimals), updatedAt: BigInt(round[3]) };
        };
        const [stable, eth] = await Promise.all([read(stableFeed), read(ethFeed)]);
        const now = BigInt(Math.floor(Date.now() / 1000));
        if (stable.value <= 0n || eth.value <= 0n || now - stable.updatedAt > 3600n || now - eth.updatedAt > 3600n) throw new Error('stale_price_feed');
        const token = new Contract(network.tokens[asset], erc20Abi, provider);
        const tokenDecimals = BigInt(await token.getFunction('decimals').staticCall());
        const expected = amount * stable.value * 10n ** eth.decimals * 10n ** 18n
            / (10n ** tokenDecimals * eth.value * 10n ** stable.decimals);
        const deviation = quotedEth > expected ? quotedEth - expected : expected - quotedEth;
        if (deviation * 10_000n > expected * config.maxPriceDeviationBps) throw new Error('reference_price_deviation');
    }

    private async broadcast(operation: Operation, stage: string, signer: Wallet, transaction: TransactionRequest): Promise<void> {
        const provider = signer.provider;
        if (!provider) throw new Error('provider_unavailable');

        let raw = operation.signedTransactions[stage];

        if (raw && await this.isUnusable(provider, signer, raw)) {
            delete operation.signedTransactions[stage];
            delete operation.transactionHashes[stage];
            operation.updatedAt = new Date().toISOString();
            await this.store.put(operation);
            raw = undefined;
        }

        if (!raw) {
            const populated = await signer.populateTransaction(transaction);
            raw = await signer.signTransaction(populated);
            const signedHash = Transaction.from(raw).hash;
            if (!signedHash) throw new Error(`${stage}_hash_unavailable`);
            operation.signedTransactions[stage] = raw;
            operation.transactionHashes[stage] = signedHash;
            operation.stage = `${stage}_signed`;
            operation.updatedAt = new Date().toISOString();
            await this.store.put(operation);
        }

        const transactionHash = Transaction.from(raw).hash;
        if (!transactionHash) throw new Error(`${stage}_hash_unavailable`);

        let receipt = await provider.getTransactionReceipt(transactionHash);
        if (!receipt) {
            try {
                await provider.broadcastTransaction(raw);
            } catch (error) {
                if (!await provider.getTransaction(transactionHash)) throw error;
            }
            operation.stage = `${stage}_broadcast`;
            operation.updatedAt = new Date().toISOString();
            await this.store.put(operation);
            receipt = await provider.waitForTransaction(transactionHash, 1, config.confirmationTimeoutSeconds * 1000);
        }
        if (!receipt || receipt.status !== 1) throw new Error(`${stage}_reverted`);
        operation.stage = `${stage}_confirmed`;
        operation.updatedAt = new Date().toISOString();
        await this.store.put(operation);
    }

    /**
     * Whether a stored signature can never confirm and has to be replaced.
     *
     * Two cases land here, and both used to wedge an operation permanently
     * because nothing ever cleared the cache that produced them. A transaction
     * that was mined and reverted keeps returning the same failed receipt, so
     * re-broadcasting it only re-reads the failure. A transaction dropped from
     * the mempool after its nonce was spent elsewhere — which the shared gas
     * wallet makes entirely possible — can never be accepted again.
     */
    private async isUnusable(provider: Provider, signer: Wallet, raw: string): Promise<boolean> {
        const parsed = Transaction.from(raw);
        const hash = parsed.hash;
        if (!hash) return true;

        const receipt: TransactionReceipt | null = await provider.getTransactionReceipt(hash);
        if (receipt) return receipt.status !== 1;

        if (await provider.getTransaction(hash)) return false;

        return await provider.getTransactionCount(signer.address, 'latest') > parsed.nonce;
    }

    private async assertCode(provider: FallbackProvider, addresses: string[]): Promise<void> {
        for (const address of addresses) {
            const code = await provider.getCode(address);
            if (code === '0x') throw new Error(`contract_bytecode_missing:${address}`);
        }
    }

    private async assertGasLimits(topup: bigint): Promise<void> {
        const operations = await this.store.list();
        const now = Date.now();
        const spent = (since: number): bigint => operations
            .filter((item) => new Date(item.createdAt).getTime() >= since)
            .reduce((total, item) => total + BigInt(item.gasTopupWei ?? '0'), 0n);
        const hourly = BigInt(process.env.MAX_HOURLY_GAS_WEI ?? '10000000000000000');
        const daily = BigInt(process.env.MAX_DAILY_GAS_WEI ?? '50000000000000000');
        if (spent(now - 3_600_000) + topup > hourly || spent(now - 86_400_000) + topup > daily) throw new Error('gas_spending_limit');
    }

    private provider(network: NetworkConfig): FallbackProvider {
        return new FallbackProvider(network.rpcUrls.map((url) => new JsonRpcProvider(url, network.chainId, { staticNetwork: true })));
    }

    private needsReview(error: unknown): boolean {
        const message = error instanceof Error ? error.message : '';
        return ['token_balance_mismatch', 'unexpected_allowance', 'token_balance_not_zero'].some((code) => message.includes(code));
    }

    private errorCode(error: unknown): string {
        const message = error instanceof Error ? error.message : 'unknown';
        return message.split(':')[0]?.slice(0, 100) || 'unknown';
    }
}
