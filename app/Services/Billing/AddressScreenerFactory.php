<?php

namespace App\Services\Billing;

use App\Contracts\Billing\AddressScreener;

final readonly class AddressScreenerFactory
{
    /**
     * The full screen a real payment gets: sanctions on both ends of the
     * transfer, then the operator's own risk list.
     */
    public function configured(): AddressScreener
    {
        if (! config('billing.screening.enabled', false)) {
            return new NullAddressScreener;
        }

        return new CompositeAddressScreener([
            new SenderAndDepositSanctionsScreener($this->sanctions()),
            new AdminRiskListScreener,
        ]);
    }

    /**
     * Sanctions only, with the operator's own risk list deliberately left out.
     *
     * This is what the buyer-facing wallet pre-check runs. The admin risk list
     * is a private judgement about specific addresses, and an endpoint that
     * answers "is this one on it?" about any address a caller names is an
     * enumeration tool for precisely the people it was written to catch — and a
     * way for them to find a wallet that passes before they ever pay. The
     * public sanctions oracle leaks nothing, because it is public.
     */
    public function publicSanctionsOnly(): AddressScreener
    {
        if (! config('billing.screening.enabled', false)) {
            return new NullAddressScreener;
        }

        return $this->sanctions();
    }

    private function sanctions(): AddressScreener
    {
        return match ((string) config('billing.screening.driver', 'oracle')) {
            'chainalysis' => new FallbackAddressScreener([new ChainalysisSanctionsScreener, new OnChainSanctionsOracleScreener]),
            'oracle' => new OnChainSanctionsOracleScreener,
            default => new NullAddressScreener,
        };
    }
}
