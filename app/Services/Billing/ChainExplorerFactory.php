<?php

namespace App\Services\Billing;

use App\Contracts\ChainExplorer;
use App\Enums\PaymentNetwork;

/**
 * Resolves the driver that can read a given chain.
 *
 * Every EVM chain shares one driver, so this stays a single arm until a
 * non-EVM chain arrives — at which point it gains a second, and nothing else
 * in the billing code changes.
 */
final readonly class ChainExplorerFactory
{
    public function for(PaymentNetwork $network): ChainExplorer
    {
        return match ($network) {
            // Etherscan is optional and the driver treats it as such: without a
            // key everything still works, minus the ability to see ether moved
            // inside a contract and minus a second opinion when the node is down.
            // Every EVM chain shares one driver, and Etherscan's V2 API takes the
            // chain as a parameter — so one key covers all of them too.
            PaymentNetwork::Ethereum,
            PaymentNetwork::Arbitrum => new EvmJsonRpcExplorer($network, new EtherscanClient($network)),
        };
    }
}
