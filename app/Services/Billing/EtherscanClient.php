<?php

namespace App\Services\Billing;

use App\Enums\PaymentNetwork;
use App\Exceptions\ExplorerUnavailable;
use App\Support\Billing\TokenAmount;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Etherscan's V2 multichain API, used for the two things a plain node cannot do.
 *
 * The first is the reason this exists: **internal transfers**. Ether forwarded
 * by a contract — an exchange withdrawal, a Safe, an account-abstraction wallet
 * — moves without appearing in a transaction's `to`/`value` and without
 * emitting a log, so a receipt simply cannot show it. Etherscan indexes the
 * execution trace and can. Without this, every such payment is real money that
 * has to be approved by hand.
 *
 * The second is redundancy: its `proxy` module is JSON-RPC over HTTP, so it
 * doubles as a fallback when our own endpoint is unreachable.
 *
 * One API key covers every supported chain — the chain is a query parameter —
 * which is what keeps adding a network a config-only change here too.
 */
final readonly class EtherscanClient
{
    /**
     * Etherscan answers "nothing found" with the same status code it uses for
     * real errors, distinguished only by this message. Treating the two alike
     * would turn "this transaction moved no ether internally" into an outage.
     */
    private const EMPTY_RESULT_MESSAGE = 'No transactions found';

    public function __construct(private PaymentNetwork $network) {}

    public function isConfigured(): bool
    {
        return (bool) config('billing.etherscan.enabled', false)
            && $this->apiKey() !== null
            && $this->url() !== null;
    }

    /**
     * Native currency credited to an address by internal transfers within one
     * transaction, in base units.
     *
     * Failed internal calls are skipped: the trace records the attempt, but no
     * value moved.
     *
     * @throws ExplorerUnavailable
     */
    public function internalCreditTo(string $txHash, string $address): string
    {
        $transfers = $this->get([
            'module' => 'account',
            'action' => 'txlistinternal',
            'txhash' => $txHash,
        ]);

        if (! is_array($transfers)) {
            return '0';
        }

        $credited = '0';

        foreach ($transfers as $transfer) {
            if (! is_array($transfer)) {
                continue;
            }

            if (($transfer['isError'] ?? '0') !== '0') {
                continue;
            }

            if (mb_strtolower((string) ($transfer['to'] ?? '')) !== $address) {
                continue;
            }

            // Already a decimal string in wei, so it goes straight into
            // TokenAmount rather than through any numeric cast.
            $credited = TokenAmount::add($credited, (string) ($transfer['value'] ?? '0'));
        }

        return $credited;
    }

    /**
     * Call a JSON-RPC method through Etherscan's proxy module.
     *
     * Only the handful of methods the verifier needs are mapped. An unmapped
     * method is a programming error rather than a runtime condition, but it is
     * still reported as unavailability so it can never settle a payment.
     *
     * @param  array<int, mixed>  $params
     *
     * @throws ExplorerUnavailable
     */
    public function rpc(string $method, array $params = []): mixed
    {
        $query = match ($method) {
            'eth_getTransactionByHash', 'eth_getTransactionReceipt' => [
                'action' => $method,
                'txhash' => (string) ($params[0] ?? ''),
            ],
            'eth_blockNumber' => ['action' => $method],
            'eth_getBlockByNumber' => [
                'action' => $method,
                'tag' => (string) ($params[0] ?? '0x0'),
                'boolean' => 'false',
            ],
            // Etherscan has no eth_chainId, and needs none: the chain is a query
            // parameter, so an answer from here is from the right chain by
            // construction.
            'eth_chainId' => null,
            default => throw new ExplorerUnavailable("Etherscan cannot answer {$method}."),
        };

        if ($query === null) {
            return '0x'.dechex($this->network->chainId());
        }

        return $this->get(['module' => 'proxy', ...$query]);
    }

    /**
     * @param  array<string, string>  $query
     *
     * @throws ExplorerUnavailable
     */
    private function get(array $query): mixed
    {
        $url = $this->url();
        $apiKey = $this->apiKey();

        if ($url === null || $apiKey === null) {
            throw new ExplorerUnavailable('Etherscan is not configured.');
        }

        try {
            $response = Http::timeout((int) config('billing.etherscan.timeout', 8))
                ->connectTimeout((int) config('billing.etherscan.connect_timeout', 4))
                ->withOptions(['allow_redirects' => false])
                ->get($url, [
                    'chainid' => $this->network->chainId(),
                    ...$query,
                    'apikey' => $apiKey,
                ]);
        } catch (Throwable $exception) {
            throw new ExplorerUnavailable('Could not reach Etherscan.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new ExplorerUnavailable("Etherscan answered {$response->status()}.");
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new ExplorerUnavailable('Etherscan returned an unreadable body.');
        }

        // The proxy module speaks JSON-RPC; everything else speaks Etherscan's
        // own status/message/result envelope.
        if (array_key_exists('jsonrpc', $body)) {
            if (isset($body['error'])) {
                $message = is_array($body['error']) ? ($body['error']['message'] ?? 'unknown') : 'unknown';

                throw new ExplorerUnavailable("Etherscan returned an error: {$message}.");
            }

            return $body['result'] ?? null;
        }

        if (($body['status'] ?? null) === '1') {
            return $body['result'] ?? null;
        }

        if (str_contains((string) ($body['message'] ?? ''), self::EMPTY_RESULT_MESSAGE)) {
            return [];
        }

        // Rate limits and invalid keys land here. Both are our problem, and both
        // must stay retryable rather than becoming a verdict about the payment.
        throw new ExplorerUnavailable(
            'Etherscan refused the request: '.((string) ($body['message'] ?? 'unknown')).'.'
        );
    }

    private function url(): ?string
    {
        $url = config('billing.etherscan.url');

        return is_string($url) && $url !== '' ? $url : null;
    }

    private function apiKey(): ?string
    {
        $key = config('billing.etherscan.api_key');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
