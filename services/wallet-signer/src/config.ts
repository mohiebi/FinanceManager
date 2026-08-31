import { getAddress } from 'ethers';
import type { AssetName, NetworkName } from './types.js';

/**
 * Everything here is optional at boot and checked at use.
 *
 * A network with no endpoints or no vault is simply one this signer cannot
 * settle on, and {@see SettlementRunner.validate} refuses it by name. Demanding
 * every value up front meant an operator running ether on one chain could not
 * start the service at all without inventing addresses for the other.
 */
const optional = (name: string): string => process.env[name]?.trim() ?? '';

const address = (name: string): string => {
    const value = optional(name);

    return value === '' ? '' : getAddress(value).toLowerCase();
};

const csv = (name: string): string[] =>
    optional(name)
        .split(',')
        .map((value) => value.trim())
        .filter(Boolean);

const token = (name: string): string | undefined => address(name) || undefined;

const codeHash = (name: string): string | undefined => {
    const value = optional(name).toLowerCase();

    if (value === '') {
        return undefined;
    }

    if (!/^0x[0-9a-f]{64}$/.test(value)) {
        throw new Error(`${name} must be a 32-byte hexadecimal code hash.`);
    }

    return value;
};

export type NetworkConfig = {
    chainId: number;
    rpcUrls: string[];
    /** Empty when this network is not configured for settlement. */
    vault: string;
    riskVault: string;
    weth?: string;
    router?: string;
    quoter?: string;
    tokens: Partial<Record<AssetName, string>>;
    priceFeeds: Partial<Record<AssetName, string>>;
    expectedCodeHashes: Record<string, string>;
};

const ethereumVault = address('ETHEREUM_SAFE_VAULT_ADDRESS');
const arbitrumVault = address('ARBITRUM_SAFE_VAULT_ADDRESS');
const arbitrumWeth = token('ARBITRUM_WETH_ADDRESS');
const arbitrumRouter = token('ARBITRUM_UNISWAP_ROUTER_ADDRESS');
const arbitrumQuoter = token('ARBITRUM_UNISWAP_QUOTER_ADDRESS');
const arbitrumUsdt = token('ARBITRUM_USDT_ADDRESS');
const arbitrumUsdc = token('ARBITRUM_USDC_ADDRESS');
const arbitrumEthFeed = token('ARBITRUM_ETH_USD_FEED');
const arbitrumUsdtFeed = token('ARBITRUM_USDT_USD_FEED');
const arbitrumUsdcFeed = token('ARBITRUM_USDC_USD_FEED');

const defined = <T extends Record<string, string | undefined>>(values: T): Partial<Record<AssetName, string>> =>
    Object.fromEntries(Object.entries(values).filter(([, value]) => Boolean(value))) as Partial<Record<AssetName, string>>;

const expectedHashes = (values: Array<[string | undefined, string | undefined]>): Record<string, string> =>
    Object.fromEntries(values.filter((entry): entry is [string, string] => Boolean(entry[0]) && Boolean(entry[1])));

export const config = {
    port: Number(process.env.PORT ?? 8080),
    keystorePath: process.env.KEYSTORE_PATH ?? '/data/keystore.json',
    operationsPath: process.env.OPERATIONS_PATH ?? '/operations/operations.json',
    controlSocket: process.env.CONTROL_SOCKET ?? '/tmp/wallet-signer-control.sock',
    hmacSecretFile: process.env.HMAC_SECRET_FILE ?? '/run/secrets/wallet_signer_hmac',
    keyVersion: process.env.KEY_VERSION ?? 'v1',
    maxSlippageBps: BigInt(process.env.MAX_SLIPPAGE_BPS ?? '100'),
    maxPriceDeviationBps: BigInt(process.env.MAX_PRICE_DEVIATION_BPS ?? '200'),
    quoteLifetimeSeconds: Number(process.env.QUOTE_LIFETIME_SECONDS ?? '60'),

    /*
     * How long one broadcast may wait for its receipt.
     *
     * Operations run one at a time so the deposit and gas wallet nonces stay
     * ordered, which means an unbounded wait is not one stuck settlement but
     * every settlement stuck behind it. On expiry the attempt becomes a
     * retryable failure and the next one re-reads the receipt.
     */
    confirmationTimeoutSeconds: Number(process.env.CONFIRMATION_TIMEOUT_SECONDS ?? '120'),

    gasBufferBps: BigInt(10_000 + Number(process.env.GAS_BUFFER_PERCENT ?? '25') * 100),
    maxGasTopupWei: BigInt(process.env.MAX_GAS_TOPUP_WEI ?? '3000000000000000'),
    dustWei: BigInt(process.env.MAX_REMAINING_NATIVE_WEI ?? '10000000000000'),
    feeTiers: (process.env.UNISWAP_FEE_TIERS ?? '100,500,3000').split(',').map(Number),
    networks: {
        ethereum: {
            chainId: 1,
            rpcUrls: csv('ETHEREUM_RPC_URLS'),
            vault: ethereumVault,
            riskVault: address('ETHEREUM_RISK_VAULT_ADDRESS'),
            tokens: {},
            priceFeeds: {},
            expectedCodeHashes: {},
        },
        arbitrum: {
            chainId: 42161,
            rpcUrls: csv('ARBITRUM_RPC_URLS'),
            vault: arbitrumVault,
            riskVault: address('ARBITRUM_RISK_VAULT_ADDRESS'),
            weth: arbitrumWeth,
            router: arbitrumRouter,
            quoter: arbitrumQuoter,
            tokens: defined({
                usdt: arbitrumUsdt,
                usdc: arbitrumUsdc,
            }),
            priceFeeds: defined({
                eth: arbitrumEthFeed,
                usdt: arbitrumUsdtFeed,
                usdc: arbitrumUsdcFeed,
            }),
            expectedCodeHashes: expectedHashes([
                [arbitrumWeth, codeHash('ARBITRUM_WETH_CODE_HASH')],
                [arbitrumRouter, codeHash('ARBITRUM_UNISWAP_ROUTER_CODE_HASH')],
                [arbitrumQuoter, codeHash('ARBITRUM_UNISWAP_QUOTER_CODE_HASH')],
                [arbitrumUsdt, codeHash('ARBITRUM_USDT_CODE_HASH')],
                [arbitrumUsdc, codeHash('ARBITRUM_USDC_CODE_HASH')],
                [arbitrumEthFeed, codeHash('ARBITRUM_ETH_USD_FEED_CODE_HASH')],
                [arbitrumUsdtFeed, codeHash('ARBITRUM_USDT_USD_FEED_CODE_HASH')],
                [arbitrumUsdcFeed, codeHash('ARBITRUM_USDC_USD_FEED_CODE_HASH')],
            ]),
        },
    } satisfies Record<NetworkName, NetworkConfig>,
};
