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
            PaymentNetwork::Ethereum => new EvmJsonRpcExplorer($network),
        };
    }
}
