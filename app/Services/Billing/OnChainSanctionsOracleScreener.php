<?php

namespace App\Services\Billing;

use App\Contracts\Billing\AddressScreener;
use App\Enums\PaymentNetwork;
use App\Enums\ScreeningRisk;
use App\Support\Billing\ScreeningResult;
use App\Support\Billing\ScreeningSubject;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Chainalysis' free on-chain sanctions oracle, read through endpoints that are
 * deliberately not the ones which verify payments.
 *
 * The reason for the separation is that one provider answering both questions
 * gets to say "this payment confirmed" and "this sender is clean" in the same
 * breath, which reduces the entire screening layer to that provider's honesty.
 * Configure two or more and they must agree: a definitive answer is only
 * believed when every endpoint that responded returned the same one, and every
 * endpoint responded. Anything else is Unknown, which holds the funds and the
 * entitlement and tries again later.
 */
final readonly class OnChainSanctionsOracleScreener implements AddressScreener
{
    /** keccak256("isSanctioned(address)") first four bytes. */
    private const IS_SANCTIONED_SELECTOR = 'df592f7d';

    public function screen(ScreeningSubject $subject): ScreeningResult
    {
        $network = $subject->network;
        $endpoints = $this->endpointsFor($network);
        $contract = mb_strtolower(trim((string) config("billing.screening.oracle.contracts.{$network->value}")));
        $sender = $network->normalizeAddress($subject->senderAddress);

        if (
            $endpoints === []
            || preg_match('/^0x[0-9a-f]{40}$/', $contract) !== 1
            || preg_match('/^0x[0-9a-f]{40}$/', $sender) !== 1
        ) {
            return ScreeningResult::unknown('chainalysis_oracle', 'not_configured');
        }

        $verdicts = [];

        foreach ($endpoints as $url) {
            $verdict = $this->verdictFrom($url, $network, $contract, $sender);

            // One endpoint that cannot answer is enough to withhold a verdict.
            // A quorum that silently shrinks to whichever provider happens to be
            // reachable is not a quorum, and this is the control standing
            // between a sanctioned wallet and the vault.
            if ($verdict === null) {
                return ScreeningResult::unknown('chainalysis_oracle', 'endpoint_unavailable');
            }

            $verdicts[] = $verdict;
        }

        if (count(array_unique($verdicts, SORT_REGULAR)) !== 1) {
            return ScreeningResult::unknown('chainalysis_oracle', 'endpoint_disagreement');
        }

        $sanctioned = $verdicts[0];

        return new ScreeningResult(
            risk: $sanctioned ? ScreeningRisk::Sanctioned : ScreeningRisk::NoMatch,
            provider: 'chainalysis_oracle',
            categories: $sanctioned ? ['sanctions'] : [],
            screenedAt: now()->toImmutable(),
        );
    }

    /**
     * Dedicated endpoints when they are configured, the network's own otherwise.
     *
     * Falling back keeps a single-endpoint deployment working exactly as it did,
     * while making the independent case a configuration change rather than a
     * code change.
     *
     * @return array<int, string>
     */
    private function endpointsFor(PaymentNetwork $network): array
    {
        $configured = config("billing.screening.rpc_urls.{$network->value}", []);

        if (is_array($configured)) {
            $urls = array_values(array_filter(array_map(
                static fn (mixed $url): string => trim((string) $url),
                $configured,
            )));

            if ($urls !== []) {
                return $urls;
            }
        }

        return $network->rpcUrls();
    }

    /** True when sanctioned, false when clear, null when this endpoint could not say. */
    private function verdictFrom(string $url, PaymentNetwork $network, string $contract, string $sender): ?bool
    {
        $addressWord = str_pad(mb_substr($sender, 2), 64, '0', STR_PAD_LEFT);
        $payload = [
            ['jsonrpc' => '2.0', 'id' => 0, 'method' => 'eth_chainId', 'params' => []],
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'eth_getCode', 'params' => [$contract, 'latest']],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'eth_call', 'params' => [[
                'to' => $contract,
                'data' => '0x'.self::IS_SANCTIONED_SELECTOR.$addressWord,
            ], 'latest']],
        ];

        try {
            $response = Http::timeout((int) config('billing.screening.timeout', 8))
                ->connectTimeout((int) config('billing.screening.connect_timeout', 4))
                ->withOptions(['allow_redirects' => false])
                ->asJson()
                ->post($url, $payload);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->json();

        if (! is_array($body) || ! array_is_list($body)) {
            return null;
        }

        $results = [];

        foreach ($body as $entry) {
            if (! is_array($entry) || isset($entry['error'])) {
                return null;
            }

            $results[(int) ($entry['id'] ?? -1)] = $entry['result'] ?? null;
        }

        $chainIdResult = $results[0] ?? null;
        $chainId = is_string($chainIdResult) && preg_match('/^0x[0-9a-fA-F]+$/', $chainIdResult) === 1
            ? (int) hexdec(mb_substr($chainIdResult, 2))
            : null;
        $code = $results[1] ?? null;
        $verdict = $results[2] ?? null;

        if ($chainId !== $network->chainId()) {
            return null;
        }

        if (
            ! is_string($code)
            || preg_match('/^0x[0-9a-fA-F]*$/', $code) !== 1
            || trim(mb_substr($code, 2), '0') === ''
        ) {
            return null;
        }

        if (! is_string($verdict) || preg_match('/^0x[0-9a-fA-F]{64}$/', $verdict) !== 1) {
            return null;
        }

        $encodedBoolean = mb_strtolower(mb_substr($verdict, 2));
        $encodedFalse = str_repeat('0', 64);
        $encodedTrue = str_repeat('0', 63).'1';

        return match ($encodedBoolean) {
            $encodedTrue => true,
            $encodedFalse => false,
            default => null,
        };
    }
}
