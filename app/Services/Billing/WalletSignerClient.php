<?php

namespace App\Services\Billing;

use App\Enums\PaymentNetwork;
use App\Exceptions\SignerUnavailable;
use App\Models\DepositAddress;
use App\Models\SubscriptionPayment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final readonly class WalletSignerClient
{
    /** @return array<string, mixed> */
    public function health(): array
    {
        return $this->request('GET', '/health');
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
        $timestamp = (string) now()->getTimestamp();
        $nonce = (string) Str::uuid();
        $secret = $this->secret();
        $canonical = implode("\n", [$timestamp, $nonce, $method, $path, hash('sha256', $body)]);
        $signature = hash_hmac('sha256', $canonical, $secret);

        $request = $this->http()->withHeaders([
            'X-Signer-Timestamp' => $timestamp,
            'X-Signer-Nonce' => $nonce,
            'X-Signer-Signature' => $signature,
        ]);

        $response = $method === 'GET'
            ? $request->get($this->url().$path)
            : $request->withBody($body, 'application/json')->send($method, $this->url().$path);

        if (! $response->successful() || ! is_array($response->json())) {
            throw new SignerUnavailable("Wallet signer returned HTTP {$response->status()} for {$path}.");
        }

        return $response->json();
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('billing.signer.timeout', 30))
            ->connectTimeout((int) config('billing.signer.connect_timeout', 3))
            ->retry(2, 200, throw: false);
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
