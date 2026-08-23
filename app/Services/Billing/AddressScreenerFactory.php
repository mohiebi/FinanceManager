<?php

namespace App\Services\Billing;

use App\Contracts\Billing\AddressScreener;

final readonly class AddressScreenerFactory
{
    public function configured(): AddressScreener
    {
        if (! config('billing.screening.enabled', false)) {
            return new NullAddressScreener;
        }

        $sanctions = match ((string) config('billing.screening.driver', 'oracle')) {
            'chainalysis' => new FallbackAddressScreener([new ChainalysisSanctionsScreener, new OnChainSanctionsOracleScreener]),
            'oracle' => new OnChainSanctionsOracleScreener,
            default => new NullAddressScreener,
        };

        return new CompositeAddressScreener([
            new SenderAndDepositSanctionsScreener($sanctions),
            new AdminRiskListScreener,
        ]);
    }
}
