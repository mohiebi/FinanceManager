<?php

namespace App\Contracts;

use App\Exceptions\ExplorerUnavailable;
use App\Models\SubscriptionPayment;
use App\Support\Billing\TokenTransfer;

interface ChainExplorer
{
    /**
     * Look up the transaction a payment has claimed.
     *
     * Returns null when the transaction is not on the chain yet, which is an
     * ordinary state for a hash submitted seconds after broadcast — and is
     * deliberately different from throwing, which means we could not ask.
     *
     * @throws ExplorerUnavailable when the chain cannot be reached or answers unusably
     */
    public function transferFor(SubscriptionPayment $payment): ?TokenTransfer;
}
