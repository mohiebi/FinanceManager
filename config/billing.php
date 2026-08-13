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

    /*
    | The receiving address. One address serves every EVM chain — same private
    | key, same address — so adding a chain does not mean managing a new wallet.
    | A per-network override exists for anyone who would rather segregate them.
    */
    'evm_address' => env('BILLING_EVM_ADDRESS'),

    'networks' => [

        'ethereum' => [
            'enabled' => env('BILLING_ETHEREUM_ENABLED', false),
            'chain_id' => 1,
            'address' => env('BILLING_ETHEREUM_ADDRESS', env('BILLING_EVM_ADDRESS')),

            // Any JSON-RPC endpoint: Alchemy, Infura, a public node or your own.
            // Use a keyed provider in production — public endpoints rate-limit
            // hard enough that the verification job spends its retries on 429s.
            'rpc_url' => env('BILLING_ETHEREUM_RPC_URL'),

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
                    'contract' => null,
                    'decimals' => 18,
                ],
                'usdt' => [
                    'contract' => env('BILLING_ETHEREUM_USDT_CONTRACT', '0xdac17f958d2ee523a2206206994597c13d831ec7'),
                    'decimals' => 6,
                ],
                'usdc' => [
                    'contract' => env('BILLING_ETHEREUM_USDC_CONTRACT', '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48'),
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

];
