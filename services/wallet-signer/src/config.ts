import { getAddress } from 'ethers';
import type { AssetName, NetworkName } from './types.js';

const required = (name: string): string => {
    const value = process.env[name]?.trim();
    if (!value) throw new Error(`${name} is required.`);
    return value;
};

const address = (name: string): string => getAddress(required(name)).toLowerCase();
const csv = (name: string): string[] => required(name).split(',').map((value) => value.trim()).filter(Boolean);

export type NetworkConfig = {
    chainId: number;
    rpcUrls: string[];
    vault: string;
    weth?: string;
    router?: string;
    quoter?: string;
    tokens: Partial<Record<AssetName, string>>;
    priceFeeds: Partial<Record<AssetName, string>>;
};

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
    gasBufferBps: BigInt(10_000 + Number(process.env.GAS_BUFFER_PERCENT ?? '25') * 100),
    maxGasTopupWei: BigInt(process.env.MAX_GAS_TOPUP_WEI ?? '3000000000000000'),
    dustWei: BigInt(process.env.MAX_REMAINING_NATIVE_WEI ?? '10000000000000'),
    feeTiers: (process.env.UNISWAP_FEE_TIERS ?? '100,500,3000').split(',').map(Number),
    networks: {
        ethereum: {
            chainId: 1,
            rpcUrls: csv('ETHEREUM_RPC_URLS'),
            vault: address('ETHEREUM_SAFE_VAULT_ADDRESS'),
            tokens: {},
            priceFeeds: {},
        },
        arbitrum: {
            chainId: 42161,
            rpcUrls: csv('ARBITRUM_RPC_URLS'),
            vault: address('ARBITRUM_SAFE_VAULT_ADDRESS'),
            weth: address('ARBITRUM_WETH_ADDRESS'),
            router: address('ARBITRUM_UNISWAP_ROUTER_ADDRESS'),
            quoter: address('ARBITRUM_UNISWAP_QUOTER_ADDRESS'),
            tokens: {
                usdt: address('ARBITRUM_USDT_ADDRESS'),
                usdc: address('ARBITRUM_USDC_ADDRESS'),
            },
            priceFeeds: {
                eth: address('ARBITRUM_ETH_USD_FEED'),
                usdt: address('ARBITRUM_USDT_USD_FEED'),
                usdc: address('ARBITRUM_USDC_USD_FEED'),
            },
        },
    } satisfies Record<NetworkName, NetworkConfig>,
};
