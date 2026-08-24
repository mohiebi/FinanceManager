<?php

namespace App\Services\Billing;

use App\Enums\PaymentNetwork;

/**
 * Accepts only a genuine screening quorum.
 *
 * Two URLs on one provider do not create independence, and a screening URL
 * shared with payment verification gives one provider both votes. Distinct
 * hosts are an enforceable deployment boundary without putting provider names
 * or credentials in application code.
 */
final readonly class ScreeningRpcPolicy
{
    /** @return array<int, string> */
    public function endpointsFor(PaymentNetwork $network): array
    {
        $configured = config("billing.screening.rpc_urls.{$network->value}", []);

        if (! is_array($configured)) {
            return [];
        }

        $endpoints = array_values(array_unique(array_filter(array_map(
            static fn (mixed $url): string => trim((string) $url),
            $configured,
        ))));

        if (count($endpoints) < 2) {
            return [];
        }

        $screeningHosts = $this->hosts($endpoints);

        if (count($screeningHosts) < 2) {
            return [];
        }

        if (array_intersect($screeningHosts, $this->hosts($network->rpcUrls())) !== []) {
            return [];
        }

        return $endpoints;
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<int, string>
     */
    private function hosts(array $urls): array
    {
        $hosts = array_map(
            static fn (string $url): string => mb_strtolower((string) parse_url($url, PHP_URL_HOST)),
            $urls,
        );

        return array_values(array_unique(array_filter($hosts)));
    }
}
