<?php

namespace App\Services\Billing;

use App\Enums\PaymentNetwork;
use App\Exceptions\SignerUnavailable;
use App\Models\DepositAddress;
use App\Models\SubscriptionPayment;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final readonly class WalletSignerClient
{
    /** @return array<string, mixed> */
    public function health(bool $deep = false): array
    {
        return $this->request('GET', $deep ? '/health?deep=1' : '/health');
    }

    /** @return array{key_version:string,start_index:int,addresses:array<int, array{index:int,address:string}>} */
    public function deriveBatch(int $startIndex, int $count): array
    {
        /** @var array{key_version:string,start_index:int,addresses:array<int, array{index:int,address:string}>} $response */
        $response = $this->request('POST', '/v1/addresses/derive-batch', [
            'startIndex' => $startIndex,
            'count' => $count,
        ]);

        return $response;
    }

    /** @return array<string, mixed> */
    public function startSettlement(SubscriptionPayment $payment, string $operationId): array
    {
        return $this->request('POST', '/v1/settlements', [
            'kind' => 'settlement',
            'operationId' => $operationId,
            'paymentId' => (string) $payment->getKey(),
            'network' => $payment->network->value,
            'chainId' => $payment->chain_id,
            'derivationIndex' => $payment->depositAddress?->derivation_index,
            'keyVersion' => $payment->depositAddress?->key_version,
            'depositAddress' => $payment->pay_to_address,
            'asset' => $payment->asset->value,
            'tokenContract' => $payment->token_contract,
            // What is actually at the address, not what we asked for. See
            // SubscriptionPayment::settlementBaseUnits().
            'verifiedAmount' => $payment->settlementBaseUnits(),
            'chainVerified' => $payment->chain_verified_at !== null,
            'screeningRisk' => $payment->screening_risk?->value ?? 'unscreened',
            'riskAuthorized' => $payment->riskCase?->authorized_at !== null,
        ]);
    }

    /**
     * Move whatever is stranded at an address no payment will ever settle.
     *
     * Names an address and a reason and nothing else. The destination is the
     * signer's own configured risk vault, so this widens what an attacker
     * holding the HMAC secret can schedule, never where the funds can go.
     *
     * @return array<string, mixed>
     */
    public function startRecovery(DepositAddress $depositAddress, PaymentNetwork $network, string $operationId, string $reason): array
    {
        return $this->request('POST', '/v1/recoveries', [
            'operationId' => $operationId,
            'network' => $network->value,
            'chainId' => $network->chainId(),
            'derivationIndex' => $depositAddress->derivation_index,
            'keyVersion' => $depositAddress->key_version,
            'depositAddress' => $depositAddress->address,
            'reason' => $reason,
        ]);
    }

    /** @return array<string, mixed> */
    public function settlement(string $operationId): array
    {
        return $this->request('GET', "/v1/settlements/{$operationId}");
    }

    /** @return array<string, mixed> */
    public function recovery(string $operationId): array
    {
        return $this->request('GET', "/v1/recoveries/{$operationId}");
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $body = $payload === [] ? '' : json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $secret = $this->secret();
        $url = $this->url().$path;

        /**
         * Sign one attempt.
         *
         * Called again before every retry rather than once for the whole call.
         * The signer records each nonce it accepts and refuses to see the same
         * one twice, so a retry carrying the first attempt's headers reads as a
         * replay and is refused with a 401 — which is exactly what happened
         * whenever an attempt reached the signer and only its response was lost
         * to a timeout. The settlement was then recorded as signer_unavailable,
         * blaming an outage for an operation the signer had in fact accepted.
         */
        $sign = function (PendingRequest $request) use ($method, $path, $body, $secret): void {
            $timestamp = (string) now()->getTimestamp();
            $nonce = (string) Str::uuid();
            $canonical = implode("\n", [$timestamp, $nonce, $method, $path, hash('sha256', $body)]);

            // replaceHeaders, not withHeaders: the latter merges recursively, so
            // re-signing would leave each header holding both the old value and
            // the new one and the signer would read the pair joined together.
            $request->replaceHeaders([
                'X-Signer-Timestamp' => $timestamp,
                'X-Signer-Nonce' => $nonce,
                'X-Signer-Signature' => hash_hmac('sha256', $canonical, $secret),
            ]);
        };

        $request = $this->http($sign);
        $sign($request);

        $response = $method === 'GET'
            ? $request->get($url)
            : $request->withBody($body, 'application/json')->send($method, $url);

        if (! $response->successful() || ! is_array($decoded = $response->json())) {
            throw new SignerUnavailable("Wallet signer returned HTTP {$response->status()} for {$path}.");
        }

        return $decoded;
    }

    /** @param  Closure(PendingRequest): void  $sign */
    private function http(Closure $sign): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('billing.signer.timeout', 30))
            ->connectTimeout((int) config('billing.signer.connect_timeout', 3))
            ->retry(2, 200, function (Throwable $exception, PendingRequest $request) use ($sign): bool {
                if (! $this->isWorthRetrying($exception)) {
                    return false;
                }

                $sign($request);

                return true;
            }, throw: false);
    }

    /**
     * Whether another attempt could plausibly land differently.
     *
     * A transport failure or the signer's own 5xx, and nothing else. Anything
     * in the 4xx range is a decision it has already reached — a malformed
     * request, a conflicting operation id, a signature it will not accept — so
     * repeating it only spends more nonces against a replay cache the signer
     * writes to disk, to be told the same thing three times.
     */
    private function isWorthRetrying(Throwable $exception): bool
    {
        if ($exception instanceof RequestException) {
            return $exception->response->serverError();
        }

        return $exception instanceof ConnectionException;
    }

    private function url(): string
    {
        $url = rtrim((string) config('billing.signer.url'), '/');

        if ($url === '') {
            throw new SignerUnavailable('Wallet signer URL is not configured.');
        }

        return $url;
    }

    private function secret(): string
    {
        $path = (string) config('billing.signer.secret_file');

        if ($path === '' || ! is_readable($path)) {
            throw new SignerUnavailable('Wallet signer secret file is not readable.');
        }

        $secret = trim((string) file_get_contents($path));

        if (strlen($secret) < 32) {
            throw new SignerUnavailable('Wallet signer secret is invalid.');
        }

        return $secret;
    }
}
