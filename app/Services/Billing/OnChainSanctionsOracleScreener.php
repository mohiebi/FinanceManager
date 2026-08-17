<?php

namespace App\Services\Billing;

use App\Contracts\Billing\AddressScreener;
use App\Enums\ScreeningRisk;
use App\Support\Billing\ScreeningResult;
use App\Support\Billing\ScreeningSubject;
use Illuminate\Support\Facades\Http;
use Throwable;

final readonly class OnChainSanctionsOracleScreener implements AddressScreener
{
    /** keccak256("isSanctioned(address)") first four bytes. */
    private const IS_SANCTIONED_SELECTOR = 'df592f7d';

    public function screen(ScreeningSubject $subject): ScreeningResult
    {
        $network = $subject->network;
        $url = $network->rpcUrl();
        $contract = mb_strtolower(trim((string) config("billing.screening.oracle.contracts.{$network->value}")));
        $sender = $network->normalizeAddress($subject->senderAddress);

        if (
            $url === null
            || preg_match('/^0x[0-9a-f]{40}$/', $contract) !== 1
            || preg_match('/^0x[0-9a-f]{40}$/', $sender) !== 1
        ) {
            return ScreeningResult::unknown('chainalysis_oracle', 'not_configured');
        }

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
            return ScreeningResult::unknown('chainalysis_oracle', 'request_failed');
        }

        if (! $response->successful()) {
            return ScreeningResult::unknown('chainalysis_oracle', 'http_'.$response->status());
        }

        $body = $response->json();

        if (! is_array($body) || ! array_is_list($body)) {
            return ScreeningResult::unknown('chainalysis_oracle', 'malformed_response');
        }

        $results = [];

        foreach ($body as $entry) {
            if (! is_array($entry) || isset($entry['error'])) {
                return ScreeningResult::unknown('chainalysis_oracle', 'rpc_error');
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
            return ScreeningResult::unknown('chainalysis_oracle', 'wrong_chain');
        }

        if (
            ! is_string($code)
            || preg_match('/^0x[0-9a-fA-F]*$/', $code) !== 1
            || trim(mb_substr($code, 2), '0') === ''
        ) {
            return ScreeningResult::unknown('chainalysis_oracle', 'contract_unavailable');
        }

        if (! is_string($verdict) || preg_match('/^0x[0-9a-fA-F]{64}$/', $verdict) !== 1) {
            return ScreeningResult::unknown('chainalysis_oracle', 'invalid_verdict');
        }

        $encodedBoolean = mb_strtolower(mb_substr($verdict, 2));
        $encodedFalse = str_repeat('0', 64);
        $encodedTrue = str_repeat('0', 63).'1';

        if (! in_array($encodedBoolean, [$encodedFalse, $encodedTrue], true)) {
            return ScreeningResult::unknown('chainalysis_oracle', 'invalid_verdict');
        }

        $sanctioned = $encodedBoolean === $encodedTrue;

        return new ScreeningResult(
            risk: $sanctioned ? ScreeningRisk::Sanctioned : ScreeningRisk::NoMatch,
            provider: 'chainalysis_oracle',
            categories: $sanctioned ? ['sanctions'] : [],
            screenedAt: now()->toImmutable(),
        );
    }
}
