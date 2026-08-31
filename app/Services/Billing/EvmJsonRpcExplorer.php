<?php

namespace App\Services\Billing;

use App\Contracts\ChainExplorer;
use App\Enums\PaymentNetwork;
use App\Exceptions\ExplorerUnavailable;
use App\Models\SubscriptionPayment;
use App\Support\Billing\TokenAmount;
use App\Support\Billing\TokenTransfer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reads a transaction from any EVM chain over plain JSON-RPC.
 *
 * One class serves every EVM chain, because only the endpoint and the chain id
 * differ — adding Base, Arbitrum or BSC needs a config block and an enum case,
 * not another driver.
 *
 * No SSRF guard here, unlike AssetPriceService, and its absence is deliberate:
 * the endpoint comes from config and never from a user. The one user-controlled
 * value is the transaction hash, which is checked against a strict pattern
 * before it is allowed into a request body.
 */
final readonly class EvmJsonRpcExplorer implements ChainExplorer
{
    /**
     * keccak256("Transfer(address,address,uint256)") — the first topic of every
     * ERC-20 transfer log, on every token, forever.
     */
    private const TRANSFER_TOPIC = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

    public function __construct(
        private PaymentNetwork $network,
        private ?EtherscanClient $etherscan = null,
    ) {}

    public function transferFor(SubscriptionPayment $payment): ?TokenTransfer
    {
        $hash = (string) $payment->tx_hash;

        // Defence in depth before the hash reaches a request body. The action
        // and the form request both check this; none of them can be the only one.
        if (preg_match($this->network->txHashPattern(), $hash) !== 1) {
            throw new ExplorerUnavailable("Refusing to look up a malformed transaction hash for payment {$payment->id}.");
        }

        $batch = $this->call([
            ['method' => 'eth_getTransactionByHash', 'params' => [$hash]],
            ['method' => 'eth_getTransactionReceipt', 'params' => [$hash]],
            ['method' => 'eth_blockNumber', 'params' => []],
            ['method' => 'eth_chainId', 'params' => []],
        ]);

        [$transaction, $receipt, $head, $chainId] = $batch;

        $this->assertChainMatches($payment, $chainId);

        // Not mined yet. An ordinary state seconds after broadcast, and quite
        // different from being unable to ask.
        if (! is_array($transaction) || ! is_array($receipt)) {
            return null;
        }

        $blockNumber = $this->toInt($receipt['blockNumber'] ?? null);

        if ($blockNumber === null) {
            return null;
        }

        $logs = is_array($receipt['logs'] ?? null) ? $receipt['logs'] : [];

        return new TokenTransfer(
            txHash: $hash,
            // Anything but an explicit success is treated as failure: a receipt
            // with no status field is pre-Byzantium and has no business here.
            succeeded: ($receipt['status'] ?? null) === '0x1',
            blockNumber: $blockNumber,
            blockTimestamp: $this->blockTimestamp($blockNumber),
            confirmations: max(0, ($this->toInt($head) ?? $blockNumber) - $blockNumber + 1),
            fromAddress: $this->normalizeAddress($transaction['from'] ?? null),
            txTo: $this->normalizeAddress($transaction['to'] ?? null),
            creditedAmount: $payment->token_contract === null
                ? $this->nativeCredit($payment, $transaction)
                : $this->tokenCredit($payment, $logs),
            touchedContract: $logs !== [],
            creditedOtherToken: $this->sawForeignTokenTransfer($payment, $logs),
        );
    }

    /**
     * Value credited by an ether transfer, direct or internal.
     *
     * A direct send is visible in the transaction itself. Ether forwarded by a
     * contract is not: it appears in no receipt and emits no log, so a node
     * alone cannot see it. Where Etherscan is configured, the execution trace
     * is consulted and those payments settle by themselves; where it is not,
     * this returns nothing and the verifier routes the payment to a human
     * rather than calling it wrong.
     */
    private function nativeCredit(SubscriptionPayment $payment, array $transaction): string
    {
        $to = $this->normalizeAddress($transaction['to'] ?? null);
        $direct = $to !== null && $to === $payment->pay_to_address
            ? TokenAmount::fromHex((string) ($transaction['value'] ?? '0x0'))
            : '0';

        // Only worth asking when the transaction ran contract code. A plain
        // wallet-to-wallet send has no trace to look at, and the extra call
        // would buy nothing.
        if ($this->etherscan?->isConfigured() !== true || $to === $payment->pay_to_address) {
            return $direct;
        }

        return TokenAmount::add(
            $direct,
            $this->etherscan->internalCreditTo((string) $payment->tx_hash, (string) $payment->pay_to_address),
        );
    }

    /**
     * Value credited by ERC-20 Transfer logs.
     *
     * Read from the receipt rather than from the transaction's own recipient,
     * which is what makes exchange and smart-wallet withdrawals work: there,
     * `to` is a contract and we are only named inside a log.
     *
     * Every matching log is summed, because one transaction can legitimately
     * pay us more than once through a router or a batch send.
     *
     * @param  array<int, mixed>  $logs
     */
    private function tokenCredit(SubscriptionPayment $payment, array $logs): string
    {
        $credited = '0';

        foreach ($logs as $log) {
            if (! is_array($log)) {
                continue;
            }

            $topics = is_array($log['topics'] ?? null) ? $log['topics'] : [];

            // The contract check is the entire defence here. Anyone can deploy a
            // worthless token that emits a perfectly-shaped Transfer event to our
            // address for any amount they like; only the address that emitted it
            // distinguishes that from real money. Compared against the payment's
            // own snapshot, so a later config change cannot revalidate old rows.
            if ($this->normalizeAddress($log['address'] ?? null) !== $payment->token_contract) {
                continue;
            }

            if (count($topics) < 3 || mb_strtolower((string) $topics[0]) !== self::TRANSFER_TOPIC) {
                continue;
            }

            if ($this->addressFromTopic((string) $topics[2]) !== $payment->pay_to_address) {
                continue;
            }

            $credited = TokenAmount::add($credited, TokenAmount::fromHex((string) ($log['data'] ?? '0x0')));
        }

        return $credited;
    }

    /**
     * Whether a token other than the expected one was moved to our address.
     *
     * Only ever used to explain a failure. It must never widen what counts as
     * payment: a token anybody can mint for free would otherwise buy a
     * subscription.
     *
     * @param  array<int, mixed>  $logs
     */
    private function sawForeignTokenTransfer(SubscriptionPayment $payment, array $logs): bool
    {
        foreach ($logs as $log) {
            if (! is_array($log)) {
                continue;
            }

            $topics = is_array($log['topics'] ?? null) ? $log['topics'] : [];

            if (count($topics) < 3 || mb_strtolower((string) $topics[0]) !== self::TRANSFER_TOPIC) {
                continue;
            }

            if ($this->addressFromTopic((string) $topics[2]) !== $payment->pay_to_address) {
                continue;
            }

            if ($this->normalizeAddress($log['address'] ?? null) !== $payment->token_contract) {
                return true;
            }
        }

        return false;
    }

    /**
     * A chain id that disagrees with the payment means our endpoint is pointed
     * at the wrong network — an operator's mistake, never a buyer's.
     *
     * Treated as unavailability rather than as a rejected payment for exactly
     * that reason: a misconfiguration must not spend somebody's money.
     */
    private function assertChainMatches(SubscriptionPayment $payment, mixed $chainId): void
    {
        $actual = $this->toInt($chainId);

        if ($actual !== null && $payment->chain_id !== null && $actual !== $payment->chain_id) {
            throw new ExplorerUnavailable(
                "The {$this->network->value} endpoint reports chain {$actual}, but payment {$payment->id} is on chain {$payment->chain_id}."
            );
        }
    }

    private function blockTimestamp(int $blockNumber): CarbonImmutable
    {
        // A mined block's timestamp never changes, so this is cached hard — it is
        // the one value here worth not asking for twice.
        $timestamp = cache()->remember(
            "billing.block.{$this->network->value}.{$blockNumber}",
            now()->addDay(),
            function () use ($blockNumber): ?int {
                [$block] = $this->call([
                    ['method' => 'eth_getBlockByNumber', 'params' => ['0x'.dechex($blockNumber), false]],
                ]);

                return is_array($block) ? $this->toInt($block['timestamp'] ?? null) : null;
            },
        );

        if ($timestamp === null) {
            throw new ExplorerUnavailable("Could not read the timestamp of block {$blockNumber}.");
        }

        return CarbonImmutable::createFromTimestampUTC($timestamp);
    }

    /**
     * Send a JSON-RPC batch and return the results in the order asked.
     *
     * Falls back to Etherscan's proxy module when our own endpoint cannot
     * answer. Not redundancy for its own sake: a payment stranded by a
     * sustained outage is somebody's money sitting in limbo waiting on a human,
     * and a second opinion is cheaper than that.
     *
     * @param  array<int, array{method: string, params: array<int, mixed>}>  $calls
     * @return array<int, mixed>
     */
    private function call(array $calls): array
    {
        try {
            return $this->callRpc($calls);
        } catch (ExplorerUnavailable $exception) {
            if ($this->etherscan?->isConfigured() !== true) {
                throw $exception;
            }

            return $this->callViaEtherscan($calls);
        }
    }

    /**
     * @param  array<int, array{method: string, params: array<int, mixed>}>  $calls
     * @return array<int, mixed>
     */
    private function callViaEtherscan(array $calls): array
    {
        // One request per method: the proxy module takes no batches. Only ever
        // reached while the primary endpoint is down, so the extra round trips
        // buy a settled payment rather than costing anything in the normal case.
        return array_map(
            fn (array $call): mixed => $this->etherscan->rpc($call['method'], $call['params']),
            $calls,
        );
    }

    /**
     * @param  array<int, array{method: string, params: array<int, mixed>}>  $calls
     * @return array<int, mixed>
     */
    private function callRpc(array $calls): array
    {
        $urls = $this->network->rpcUrls();

        if ($urls === []) {
            throw new ExplorerUnavailable("No RPC endpoint configured for {$this->network->value}.");
        }

        $payload = [];

        foreach ($calls as $index => $call) {
            $payload[] = [
                'jsonrpc' => '2.0',
                'id' => $index,
                'method' => $call['method'],
                'params' => $call['params'],
            ];
        }

        $response = null;
        $lastException = null;
        $failures = [];

        foreach ($urls as $url) {
            // Host only, never the URL: a keyed provider carries its credential
            // in the path or query, and everything below reaches the log.
            $host = (string) parse_url($url, PHP_URL_HOST) ?: 'endpoint';

            try {
                $candidate = Http::timeout((int) config("billing.networks.{$this->network->value}.timeout", 8))
                    ->connectTimeout((int) config("billing.networks.{$this->network->value}.connect_timeout", 4))
                    ->withOptions(['allow_redirects' => false])
                    ->asJson()
                    ->post($url, $payload);

                if ($candidate->successful()) {
                    $response = $candidate;
                    break;
                }

                // Recorded rather than dropped. Failing over used to keep only
                // exceptions, so a run where every endpoint answered 429 raised
                // an error naming no status and carrying no previous — leaving
                // rate-limiting and a genuine outage looking identical, when the
                // first wants a keyed provider and the second wants debugging.
                $failures[] = "{$host} answered {$candidate->status()}";
            } catch (Throwable $exception) {
                $lastException = $exception;
                $failures[] = "{$host} was unreachable";
            }
        }

        if ($response === null) {
            throw new ExplorerUnavailable(
                "No healthy {$this->network->value} endpoint: ".implode('; ', $failures).'.',
                previous: $lastException,
            );
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new ExplorerUnavailable("The {$this->network->value} endpoint returned an unreadable body.");
        }

        // A single-element batch may come back as a bare object.
        if (array_is_list($body) === false) {
            $body = [$body];
        }

        $results = [];

        foreach ($body as $entry) {
            if (! is_array($entry)) {
                throw new ExplorerUnavailable("The {$this->network->value} endpoint returned an unreadable entry.");
            }

            // A JSON-RPC error is not a verdict about the payment — it means the
            // question could not be answered, which is retryable.
            if (isset($entry['error'])) {
                $message = is_array($entry['error']) ? ($entry['error']['message'] ?? 'unknown') : 'unknown';

                throw new ExplorerUnavailable("The {$this->network->value} endpoint returned an error: {$message}.");
            }

            $results[(int) ($entry['id'] ?? count($results))] = $entry['result'] ?? null;
        }

        ksort($results);

        return array_values($results + array_fill(0, count($calls), null));
    }

    /** The low 20 bytes of a 32-byte indexed address topic. */
    private function addressFromTopic(string $topic): ?string
    {
        $hex = mb_strtolower(trim($topic));

        return mb_strlen($hex) < 40 ? null : '0x'.mb_substr($hex, -40);
    }

    private function normalizeAddress(mixed $address): ?string
    {
        return is_string($address) && $address !== ''
            ? $this->network->normalizeAddress($address)
            : null;
    }

    /**
     * Hex quantities that genuinely fit an integer — block numbers and unix
     * timestamps. Never amounts, which go through TokenAmount.
     */
    private function toInt(mixed $value): ?int
    {
        if (! is_string($value) || ! str_starts_with($value, '0x')) {
            return null;
        }

        $digits = mb_substr($value, 2);

        return $digits !== '' && ctype_xdigit($digits) ? (int) hexdec($digits) : null;
    }
}
