<?php

/*
|--------------------------------------------------------------------------
| Subscription billing
|--------------------------------------------------------------------------
|
| Pro plans and the crypto rails they settle on. This lives outside
| config/services.php deliberately: almost none of it is a third-party
| credential. It is a price list, a set of chain parameters and the
| tolerances the verifier judges a payment against.
|
| Everything a payment depends on is snapshotted onto the payment row when
| the intent is created, so editing this file never changes the terms of a
| payment already in flight.
|
*/

return [

    /*
    | Master switch. While false the billing page 404s and its settings nav
    | entry disappears, so the whole feature can be built and deployed before
    | there is anything to sell.
    */
    'enabled' => env('BILLING_ENABLED', false),

    /*
    | Plans are priced in USD and settled in whichever asset the buyer picks.
    | No auto-renew exists — crypto cannot pull funds — so every plan is a
    | prepaid span that extends the buyer's current expiry.
    */
    'plans' => [
        'monthly' => [
            'months' => 1,
            'price_usd' => env('BILLING_PRICE_MONTHLY', '5.00'),
        ],
        'quarterly' => [
            'months' => 3,
            'price_usd' => env('BILLING_PRICE_QUARTERLY', '13.00'),
        ],
        'yearly' => [
            'months' => 12,
            'price_usd' => env('BILLING_PRICE_YEARLY', '45.00'),
            'highlighted' => true,
        ],
    ],

    'networks' => [

        'ethereum' => [
            'enabled' => env('BILLING_ETHEREUM_ENABLED', false),
            'chain_id' => 1,
            // Any JSON-RPC endpoint: Alchemy, Infura, a public node or your own.
            // Use a keyed provider in production — public endpoints rate-limit
            // hard enough that the verification job spends its retries on 429s.
            'rpc_url' => env('BILLING_ETHEREUM_RPC_URL'),
            'rpc_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('BILLING_ETHEREUM_RPC_URLS', env('BILLING_ETHEREUM_RPC_URL', '')))))),

            // ~12s per block, so this is roughly two and a half minutes.
            'confirmations' => env('BILLING_ETHEREUM_CONFIRMATIONS', 12),

            'explorer_tx_url' => env('BILLING_ETHEREUM_EXPLORER_TX_URL', 'https://etherscan.io/tx/'),

            'timeout' => env('BILLING_ETHEREUM_TIMEOUT', 8),
            'connect_timeout' => env('BILLING_ETHEREUM_CONNECT_TIMEOUT', 4),

            /*
            | Contracts are stored lowercased because every comparison lowercases
            | first — EIP-55 checksum casing is a display convention, not part of
            | the address.
            |
            | VERIFY BOTH ON ETHERSCAN BEFORE ENABLING THIS NETWORK. A wrong
            | contract rejects every payment in that asset, and wrong decimals
            | misread every amount by a factor of a million.
            */
            'assets' => [
                'eth' => [
                    'enabled' => env('BILLING_ETHEREUM_ETH_ENABLED', true),
                    'contract' => null,
                    'decimals' => 18,
                ],
                'usdt' => [
                    'enabled' => env('BILLING_ETHEREUM_USDT_ENABLED', false),
                    'contract' => env('BILLING_ETHEREUM_USDT_CONTRACT', '0xdac17f958d2ee523a2206206994597c13d831ec7'),
                    'decimals' => 6,
                ],
                'usdc' => [
                    'enabled' => env('BILLING_ETHEREUM_USDC_ENABLED', false),
                    'contract' => env('BILLING_ETHEREUM_USDC_CONTRACT', '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48'),
                    'decimals' => 6,
                ],
            ],
        ],

        'arbitrum' => [
            'enabled' => env('BILLING_ARBITRUM_ENABLED', false),
            'chain_id' => 42161,
            'rpc_url' => env('BILLING_ARBITRUM_RPC_URL', 'https://arb1.arbitrum.io/rpc'),
            'rpc_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('BILLING_ARBITRUM_RPC_URLS', env('BILLING_ARBITRUM_RPC_URL', 'https://arb1.arbitrum.io/rpc')))))),

            /*
            | Blocks arrive roughly four times a second and are sequenced rather
            | than mined, so this number does not mean what it means on Ethereum:
            | it says the sequencer has accepted the transaction, not that the
            | transaction has settled on the base chain. For subscription-sized
            | amounts that is the right trade — twenty blocks is about five
            | seconds, against several minutes on layer one.
            */
            'confirmations' => env('BILLING_ARBITRUM_CONFIRMATIONS', 20),

            'explorer_tx_url' => env('BILLING_ARBITRUM_EXPLORER_TX_URL', 'https://arbiscan.io/tx/'),

            'timeout' => env('BILLING_ARBITRUM_TIMEOUT', 8),
            'connect_timeout' => env('BILLING_ARBITRUM_CONNECT_TIMEOUT', 4),

            /*
            | VERIFY BOTH ON ARBISCAN BEFORE ENABLING. These are not the same
            | contracts as on Ethereum, and USDC in particular has two of them
            | in circulation: the native issue below, and a bridged USDC.e at
            | 0xff970a61a04b1ca14834a43f5de4533ebddb5cc8 which is a different
            | token. Accepting the wrong one means refusing real payments.
            */
            'assets' => [
                'eth' => [
                    'enabled' => env('BILLING_ARBITRUM_ETH_ENABLED', true),
                    'contract' => null,
                    'decimals' => 18,
                ],
                'usdt' => [
                    'enabled' => env('BILLING_ARBITRUM_USDT_ENABLED', true),
                    'contract' => env('BILLING_ARBITRUM_USDT_CONTRACT', '0xfd086bc7cd5c481dcc9c85ebe478a1c0b69fcbb9'),
                    'decimals' => 6,
                ],
                'usdc' => [
                    'enabled' => env('BILLING_ARBITRUM_USDC_ENABLED', true),
                    'contract' => env('BILLING_ARBITRUM_USDC_CONTRACT', '0xaf88d065e77c8cc2239327c5edb3a432268e5831'),
                    'decimals' => 6,
                ],
            ],
        ],

    ],

    /*
    | Etherscan's V2 multichain API. Entirely optional — everything works
    | without it — but it settles two things a node cannot.
    |
    | The first is ether forwarded by a contract, which most exchange
    | withdrawals are. Such a transfer appears in no receipt and emits no log,
    | so without this it lands in the manual review queue every single time,
    | despite being a perfectly real payment. With it, those settle by
    | themselves.
    |
    | The second is redundancy: the proxy module is JSON-RPC over HTTP, so it
    | stands in when our own endpoint is unreachable.
    |
    | One key covers every supported chain — the chain is a query parameter.
    */
    'etherscan' => [
        'enabled' => env('BILLING_ETHERSCAN_ENABLED', false),
        'url' => env('BILLING_ETHERSCAN_URL', 'https://api.etherscan.io/v2/api'),
        'api_key' => env('BILLING_ETHERSCAN_API_KEY'),
        'timeout' => env('BILLING_ETHERSCAN_TIMEOUT', 8),
        'connect_timeout' => env('BILLING_ETHERSCAN_CONNECT_TIMEOUT', 4),
    ],

    /*
    | Every new on-chain payment consumes one public address generated offline.
    | Addresses are never returned to the pool after being shown to a buyer.
    */
    'deposit_pool' => [
        'target' => env('BILLING_DEPOSIT_POOL_TARGET', 100),
        'low_address_warning' => env('BILLING_DEPOSIT_POOL_LOW_WARNING', 25),
        'max_assignments_per_user_per_day' => env('BILLING_DEPOSIT_POOL_USER_DAILY_LIMIT', 10),
    ],

    /*
    | Screening is fail-closed. Disabled or unreachable drivers return Unknown,
    | which keeps both entitlement and funds on hold instead of passing them.
    */
    'screening' => [
        'enabled' => env('BILLING_SCREENING_ENABLED', false),
        'driver' => env('BILLING_SCREENING_DRIVER', 'oracle'),
        'timeout' => env('BILLING_SCREENING_TIMEOUT', 8),
        'connect_timeout' => env('BILLING_SCREENING_CONNECT_TIMEOUT', 4),
        'oracle' => [
            'contracts' => [
                'ethereum' => env('BILLING_ETHEREUM_SANCTIONS_ORACLE', '0x40c57923924b5c5c5455c48d93317139addac8fb'),
                'arbitrum' => env('BILLING_ARBITRUM_SANCTIONS_ORACLE', '0x40c57923924b5c5c5455c48d93317139addac8fb'),
            ],
        ],
        'chainalysis' => [
            'enabled' => env('BILLING_CHAINALYSIS_ENABLED', false),
            'url' => env('BILLING_CHAINALYSIS_URL', 'https://public.chainalysis.com/api/v1/address'),
            'api_key' => env('BILLING_CHAINALYSIS_API_KEY'),
        ],
    ],

    'signer' => [
        'url' => env('BILLING_SIGNER_URL', 'http://wallet-signer:8080'),
        'secret_file' => env('BILLING_SIGNER_SECRET_FILE', '/run/secrets/wallet_signer_hmac'),
        'key_version' => env('BILLING_SIGNER_KEY_VERSION', 'v1'),
        'timeout' => env('BILLING_SIGNER_TIMEOUT', 30),
        'connect_timeout' => env('BILLING_SIGNER_CONNECT_TIMEOUT', 3),
    ],

    'settlement' => [
        'vaults' => [
            'ethereum' => env('BILLING_ETHEREUM_SAFE_VAULT_ADDRESS'),
            'arbitrum' => env('BILLING_ARBITRUM_SAFE_VAULT_ADDRESS'),
        ],
        'gas_buffer_percent' => env('BILLING_GAS_BUFFER_PERCENT', 25),
        'max_slippage_bps' => env('BILLING_MAX_SLIPPAGE_BPS', 100),
        'max_price_deviation_bps' => env('BILLING_MAX_PRICE_DEVIATION_BPS', 200),
        'quote_lifetime_seconds' => env('BILLING_QUOTE_LIFETIME_SECONDS', 60),
        'max_remaining_native_wei' => env('BILLING_MAX_REMAINING_NATIVE_WEI', '10000000000000'),
    ],

    'risk' => [
        'review_hours' => env('BILLING_FLAGGED_REVIEW_HOURS', 48),
        'max_flagged_addresses_per_user' => env('BILLING_MAX_FLAGGED_ADDRESSES_PER_USER', 3),
    ],

    /*
    | USD rate lookup for volatile settlement assets. Stablecoins never reach
    | this — they short-circuit to 1.00 without a network call.
    */
    'quote' => [
        'enabled' => env('BILLING_QUOTE_ENABLED', env('APP_ENV') !== 'testing'),
        'url' => env('BILLING_QUOTE_URL', 'https://api.coingecko.com/api/v3/simple/price'),
        'cache_seconds' => env('BILLING_QUOTE_CACHE_SECONDS', 300),
        'timeout' => env('BILLING_QUOTE_TIMEOUT', 6),
        'connect_timeout' => env('BILLING_QUOTE_CONNECT_TIMEOUT', 3),

        // How long a quoted amount stays payable. A volatile asset gets a short
        // window because we carry the price risk for its duration; a stablecoin
        // gets a day because there is no risk to carry.
        'lock_minutes' => [
            'volatile' => env('BILLING_QUOTE_LOCK_MINUTES', 30),
            'stable' => env('BILLING_STABLE_LOCK_MINUTES', 1440),
        ],
    ],

    /*
    | How long an unpaid intent stays open before it is expired by the sweep.
    */
    'payment_window_hours' => env('BILLING_PAYMENT_WINDOW_HOURS', 24),

    /*
    | A transaction older than this is refused even if it matches perfectly —
    | it belongs to some earlier intent, or to nothing at all.
    */
    'tx_max_age_hours' => env('BILLING_TX_MAX_AGE_HOURS', 72),

    /*
    | Slack for the gap between our clock and the chain's when deciding whether
    | a transaction predates the intent that claims it.
    */
    'clock_skew_seconds' => env('BILLING_CLOCK_SKEW_SECONDS', 120),

    /*
    | How many days before expiry the renewal reminder goes out. Load-bearing,
    | not decorative: nothing renews itself.
    */
    'expiry_warning_days' => env('BILLING_EXPIRY_WARNING_DAYS', 7),

    /*
    | How long one "a payment needs your decision" alert suppresses the next of
    | the same kind. An unreachable chain parks every payment in flight at once,
    | and a dozen identical emails is less useful than one that says how many
    | are waiting.
    */
    'review_alert_minutes' => env('BILLING_REVIEW_ALERT_MINUTES', 15),

];
