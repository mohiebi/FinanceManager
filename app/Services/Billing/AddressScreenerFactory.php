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

        $oracle = new OnChainSanctionsOracleScreener;
        $http = new ChainalysisSanctionsScreener;

        return match ((string) config('billing.screening.driver', 'oracle')) {
            'chainalysis' => new FallbackAddressScreener([$http, $oracle]),
            'oracle' => new FallbackAddressScreener([$oracle, $http]),
            default => new NullAddressScreener,
        };
    }
}
