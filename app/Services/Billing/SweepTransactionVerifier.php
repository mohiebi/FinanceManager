<?php

namespace App\Services\Billing;

use App\Enums\SettlementAsset;
use App\Models\DepositAddress;
use Illuminate\Support\Facades\Http;
use Throwable;

final readonly class SweepTransactionVerifier
{
    private const BALANCE_OF_SELECTOR = '70a08231';

    /** Return null when every invariant is proven, otherwise a stable reason. */
    public function rejectionReason(
        DepositAddress $depositAddress,
        string $sweepTransactionHash,
        ?string $conversionTransactionHash,
    ): ?string {
        $payment = $depositAddress->payment;

        if ($payment === null) {
            return 'missing_payment';
        }

        $network = $depositAddress->network;
        $rpcUrl = $network->rpcUrl();
        $treasury = $network->normalizeAddress((string) config("billing.sweep.treasury.{$network->value}"));

        if ($rpcUrl === null || preg_match('/^0x[0-9a-f]{40}$/', $treasury) !== 1) {
            return 'treasury_not_configured';
        }

        if ($payment->asset === SettlementAsset::Eth && $conversionTransactionHash !== null) {
            return 'unexpected_conversion';
        }

        if ($payment->asset !== SettlementAsset::Eth && $conversionTransactionHash === null) {
            return 'conversion_required';
        }

        $calls = [
            ['jsonrpc' => '2.0', 'id' => 0, 'method' => 'eth_chainId', 'params' => []],
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'eth_blockNumber', 'params' => []],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'eth_getTransactionByHash', 'params' => [$sweepTransactionHash]],
            ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'eth_getTransactionReceipt', 'params' => [$sweepTransactionHash]],
            ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'eth_getBalance', 'params' => [$depositAddress->address, 'latest']],
        ];

        $conversionTransactionId = null;
        $conversionReceiptId = null;
        $nextId = 5;

        if ($conversionTransactionHash !== null) {
            $conversionTransactionId = $nextId++;
            $conversionReceiptId = $nextId++;
            $calls[] = ['jsonrpc' => '2.0', 'id' => $conversionTransactionId, 'method' => 'eth_getTransactionByHash', 'params' => [$conversionTransactionHash]];
            $calls[] = ['jsonrpc' => '2.0', 'id' => $conversionReceiptId, 'method' => 'eth_getTransactionReceipt', 'params' => [$conversionTransactionHash]];
        }

        $tokenBalanceIds = [];
        $addressWord = str_pad(mb_substr($depositAddress->address, 2), 64, '0', STR_PAD_LEFT);

        foreach ($network->assets() as $asset) {
            $contract = $asset->contractOn($network);

            if ($contract === null) {
                continue;
            }

            $tokenBalanceIds[] = $nextId;
            $calls[] = [
                'jsonrpc' => '2.0',
                'id' => $nextId++,
                'method' => 'eth_call',
                'params' => [[
                    'to' => $contract,
                    'data' => '0x'.self::BALANCE_OF_SELECTOR.$addressWord,
                ], 'latest'],
            ];
        }

        try {
            $response = Http::timeout((int) config("billing.networks.{$network->value}.timeout", 8))
                ->connectTimeout((int) config("billing.networks.{$network->value}.connect_timeout", 4))
                ->withOptions(['allow_redirects' => false])
                ->asJson()
                ->post($rpcUrl, $calls);
        } catch (Throwable) {
            return 'rpc_unavailable';
        }

        if (! $response->successful()) {
            return 'rpc_unavailable';
        }

        $body = $response->json();

        if (! is_array($body) || ! array_is_list($body)) {
            return 'malformed_rpc_response';
        }

        $results = [];

        foreach ($body as $entry) {
            if (! is_array($entry) || isset($entry['error']) || ! array_key_exists('result', $entry)) {
                return 'rpc_error';
            }

            $results[(int) ($entry['id'] ?? -1)] = $entry['result'];
        }

        if ($this->hexInteger($results[0] ?? null) !== $network->chainId()) {
            return 'wrong_chain';
        }

        $currentBlock = $this->hexInteger($results[1] ?? null);

        if ($currentBlock === null) {
            return 'invalid_block';
        }

        $sweepReason = $this->transactionRejectionReason(
            transaction: $results[2] ?? null,
            receipt: $results[3] ?? null,
            expectedSender: $depositAddress->address,
            expectedRecipient: $treasury,
            currentBlock: $currentBlock,
            requiredConfirmations: $network->confirmationsRequired(),
        );

        if ($sweepReason !== null) {
            return 'sweep_'.$sweepReason;
        }

        if ($conversionTransactionId !== null && $conversionReceiptId !== null) {
            $conversionReason = $this->transactionRejectionReason(
                transaction: $results[$conversionTransactionId] ?? null,
                receipt: $results[$conversionReceiptId] ?? null,
                expectedSender: $depositAddress->address,
                expectedRecipient: null,
                currentBlock: $currentBlock,
                requiredConfirmations: $network->confirmationsRequired(),
            );

            if ($conversionReason !== null) {
                return 'conversion_'.$conversionReason;
            }
        }

        foreach ($tokenBalanceIds as $id) {
            if (! $this->isZeroHex($results[$id] ?? null)) {
                return 'token_balance_remaining';
            }
        }

        if (! $this->hexAtMost(
            $results[4] ?? null,
            (string) config('billing.sweep.max_remaining_native_wei', '10000000000000'),
        )) {
            return 'native_dust_too_high';
        }

        return null;
    }

    private function transactionRejectionReason(
        mixed $transaction,
        mixed $receipt,
        string $expectedSender,
        ?string $expectedRecipient,
        int $currentBlock,
        int $requiredConfirmations,
    ): ?string {
        if (! is_array($transaction) || ! is_array($receipt)) {
            return 'not_found';
        }

        if (mb_strtolower((string) ($transaction['from'] ?? '')) !== mb_strtolower($expectedSender)) {
            return 'wrong_sender';
        }

        if (
            $expectedRecipient !== null
            && mb_strtolower((string) ($transaction['to'] ?? '')) !== mb_strtolower($expectedRecipient)
        ) {
            return 'wrong_recipient';
        }

        if (($receipt['status'] ?? null) !== '0x1') {
            return 'failed';
        }

        $blockNumber = $this->hexInteger($receipt['blockNumber'] ?? null);

        if ($blockNumber === null || ($currentBlock - $blockNumber + 1) < $requiredConfirmations) {
            return 'unconfirmed';
        }

        return null;
    }

    private function hexInteger(mixed $value): ?int
    {
        if (! is_string($value) || preg_match('/^0x[0-9a-fA-F]+$/', $value) !== 1) {
            return null;
        }

        return (int) hexdec(mb_substr($value, 2));
    }

    private function isZeroHex(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^0x[0-9a-fA-F]+$/', $value) === 1
            && trim(mb_substr($value, 2), '0') === '';
    }

    private function hexAtMost(mixed $hexValue, string $decimalMaximum): bool
    {
        if (! is_string($hexValue) || preg_match('/^0x[0-9a-fA-F]+$/', $hexValue) !== 1) {
            return false;
        }

        $actual = ltrim(mb_strtolower(mb_substr($hexValue, 2)), '0') ?: '0';
        $maximum = ltrim(mb_strtolower(dechex((int) $decimalMaximum)), '0') ?: '0';

        return strlen($actual) < strlen($maximum)
            || (strlen($actual) === strlen($maximum) && strcmp($actual, $maximum) <= 0);
    }
}
