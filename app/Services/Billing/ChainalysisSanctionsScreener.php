<?php

namespace App\Services\Billing;

use App\Contracts\Billing\AddressScreener;
use App\Enums\ScreeningRisk;
use App\Support\Billing\ScreeningResult;
use App\Support\Billing\ScreeningSubject;
use Illuminate\Support\Facades\Http;
use Throwable;

final readonly class ChainalysisSanctionsScreener implements AddressScreener
{
    public function screen(ScreeningSubject $subject): ScreeningResult
    {
        $key = trim((string) config('billing.screening.chainalysis.api_key'));
        $baseUrl = rtrim((string) config('billing.screening.chainalysis.url'), '/');

        if (! config('billing.screening.chainalysis.enabled', false) || $key === '' || $baseUrl === '') {
            return ScreeningResult::unknown('chainalysis_http', 'not_configured');
        }

        try {
            $response = Http::timeout((int) config('billing.screening.timeout', 8))
                ->connectTimeout((int) config('billing.screening.connect_timeout', 4))
                ->withOptions(['allow_redirects' => false])
                ->withHeaders(['X-API-Key' => $key])
                ->acceptJson()
                ->get($baseUrl.'/'.$subject->senderAddress);
        } catch (Throwable) {
            return ScreeningResult::unknown('chainalysis_http', 'request_failed');
        }

        if (! $response->successful()) {
            return ScreeningResult::unknown('chainalysis_http', 'http_'.$response->status());
        }

        $body = $response->json();
        $identifications = is_array($body) ? ($body['identifications'] ?? null) : null;

        if (! is_array($identifications)) {
            return ScreeningResult::unknown('chainalysis_http', 'malformed_response');
        }

        $responseAddress = is_array($body) ? ($body['address'] ?? null) : null;
        $responseChainId = is_array($body) ? ($body['chainId'] ?? $body['chain_id'] ?? null) : null;
        $responseNetwork = is_array($body) ? ($body['network'] ?? null) : null;

        if (
            (is_string($responseAddress) && mb_strtolower($responseAddress) !== mb_strtolower($subject->senderAddress))
            || ($responseChainId !== null && (int) $responseChainId !== $subject->network->chainId())
            || (is_string($responseNetwork) && mb_strtolower($responseNetwork) !== $subject->network->value)
        ) {
            return ScreeningResult::unknown('chainalysis_http', 'wrong_chain_or_address');
        }

        if ($identifications === []) {
            return new ScreeningResult(ScreeningRisk::NoMatch, 'chainalysis_http', screenedAt: now()->toImmutable());
        }

        $categories = collect($identifications)
            ->filter(fn (mixed $identification): bool => is_array($identification))
            ->map(fn (array $identification): string => mb_strtolower((string) ($identification['category'] ?? 'sanctions')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $reference = is_array($identifications[0] ?? null)
            ? (string) (($identifications[0]['name'] ?? null) ?: ($identifications[0]['description'] ?? ''))
            : null;

        return new ScreeningResult(
            risk: ScreeningRisk::Sanctioned,
            provider: 'chainalysis_http',
            categories: $categories === [] ? ['sanctions'] : $categories,
            providerReference: $reference === '' ? null : $reference,
            screenedAt: now()->toImmutable(),
        );
    }
}
