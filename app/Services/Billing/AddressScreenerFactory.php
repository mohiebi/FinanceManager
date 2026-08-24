<?php

namespace App\Services\Billing;

use App\Contracts\Billing\AddressScreener;

final readonly class AddressScreenerFactory
{
    public function __construct(private OnChainSanctionsOracleScreener $onChainSanctions) {}

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

    /** The advisory pre-check exposes only the verdict, never risk-list details. */
    public function publicPrecheck(): AddressScreener
    {
        if (! config('billing.screening.enabled', false)) {
            return new NullAddressScreener;
        }

        return new CompositeAddressScreener([
            $this->sanctions(),
            new AdminRiskListScreener,
        ]);
    }

    private function sanctions(): AddressScreener
    {
        return match ((string) config('billing.screening.driver', 'oracle')) {
            'chainalysis' => new FallbackAddressScreener([new ChainalysisSanctionsScreener, $this->onChainSanctions]),
            'oracle' => $this->onChainSanctions,
            default => new NullAddressScreener,
        };
    }
}
