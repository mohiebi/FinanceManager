<?php

use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Switch billing on for a test, with a payable Ethereum rail.
 *
 * Billing ships off, and the network ships off inside it, so nothing that
 * exercises a payment works without this. The address and RPC endpoint are
 * fabricated: no test ever reaches a real node, and the quote source is left
 * disabled so a stablecoin test never touches the network at all.
 *
 * @param  array<string, mixed>  $overrides
 */
function enableBilling(array $overrides = []): void
{
    config()->set([
        'billing.enabled' => true,
        'billing.evm_address' => TEST_RECEIVING_ADDRESS,
        'billing.networks.ethereum.enabled' => true,
        'billing.networks.ethereum.address' => TEST_RECEIVING_ADDRESS,
        'billing.networks.ethereum.rpc_url' => 'https://ethereum.test/rpc',
        'billing.quote.enabled' => false,
        ...$overrides,
    ]);
}

/**
 * The address every billing test pays to.
 */
const TEST_RECEIVING_ADDRESS = '0x1111111111111111111111111111111111111111';

/** keccak256("Transfer(address,address,uint256)"). */
const TEST_TRANSFER_TOPIC = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

/**
 * Stand in for an EVM JSON-RPC endpoint describing one transaction.
 *
 * No test ever reaches a real node. Amounts are given as hex, the way a chain
 * actually reports them, and the expected decimal is derived from the same hex
 * with TokenAmount — so a fixture and the payment it settles cannot drift.
 *
 * Etherscan is answered by the same closure rather than a second Http::fake(),
 * which would replace this one instead of composing with it. Pass `internal`
 * for an execution trace, or `etherscan` for a callable that answers it however
 * the test needs.
 *
 * @param  array<string, mixed>  $options
 */
function fakeEvmChain(array $options = []): void
{
    $options = [
        'found' => true,
        'succeeded' => true,
        'block' => 21_000_000,
        'confirmations' => 12,
        'chain_id' => 1,
        'timestamp' => null,
        'from' => '0x2222222222222222222222222222222222222222',
        'tx_to' => TEST_RECEIVING_ADDRESS,
        'value' => '0x0',
        'logs' => [],
        'internal' => [],
        'etherscan' => null,
        ...$options,
    ];

    $minedAt = $options['timestamp'] ?? now();

    Http::fake(function (Request $request) use ($options, $minedAt) {
        if (str_contains($request->url(), 'etherscan')) {
            if (is_callable($options['etherscan'])) {
                return ($options['etherscan'])($request);
            }

            // Etherscan reports "nothing found" with the same status it uses for
            // real errors, so the fixture has to reproduce that quirk faithfully.
            return Http::response($options['internal'] === []
                ? ['status' => '0', 'message' => 'No transactions found', 'result' => []]
                : ['status' => '1', 'message' => 'OK', 'result' => $options['internal']]);
        }

        $results = [];

        foreach ($request->data() as $call) {
            $results[] = [
                'jsonrpc' => '2.0',
                'id' => $call['id'],
                'result' => match ($call['method']) {
                    'eth_getTransactionByHash' => $options['found'] ? [
                        'hash' => $call['params'][0],
                        'from' => $options['from'],
                        'to' => $options['tx_to'],
                        'value' => $options['value'],
                        'blockNumber' => '0x'.dechex($options['block']),
                    ] : null,
                    'eth_getTransactionReceipt' => $options['found'] ? [
                        'status' => $options['succeeded'] ? '0x1' : '0x0',
                        'blockNumber' => '0x'.dechex($options['block']),
                        'logs' => $options['logs'],
                    ] : null,
                    'eth_blockNumber' => '0x'.dechex($options['block'] + max(0, $options['confirmations'] - 1)),
                    'eth_chainId' => '0x'.dechex($options['chain_id']),
                    'eth_getBlockByNumber' => ['timestamp' => '0x'.dechex($minedAt->getTimestamp())],
                    default => null,
                },
            ];
        }

        return Http::response($results);
    });
}

/**
 * One ERC-20 Transfer log, as a receipt carries it.
 */
function evmTransferLog(string $contract, string $to, string $amountHex, ?string $from = null): array
{
    $pad = fn (string $address): string => '0x'.str_pad(mb_substr($address, 2), 64, '0', STR_PAD_LEFT);

    return [
        'address' => $contract,
        'topics' => [
            TEST_TRANSFER_TOPIC,
            $pad($from ?? '0x2222222222222222222222222222222222222222'),
            $pad($to),
        ],
        'data' => $amountHex,
    ];
}

/**
 * Assert a transaction exists whose *decrypted* values match.
 *
 * assertDatabaseHas cannot be used for amount, title or description any more:
 * those columns hold ciphertext with a fresh IV per row, so no literal will ever
 * match. Comparison has to happen after the model decrypts.
 *
 * @param  array<string, mixed>  $expected
 */
function assertTransactionExists(array $expected): void
{
    $match = Transaction::query()
        ->when(
            isset($expected['user_id']),
            fn ($query) => $query->where('user_id', $expected['user_id']),
        )
        ->get()
        ->first(function (Transaction $transaction) use ($expected): bool {
            foreach ($expected as $attribute => $value) {
                $actual = $transaction->{$attribute};

                if ($actual instanceof BackedEnum) {
                    $actual = $actual->value;
                }

                if ($actual instanceof CarbonInterface) {
                    $actual = $actual->toDateString();
                }

                if ((string) $actual !== (string) $value) {
                    return false;
                }
            }

            return true;
        });

    expect($match)->not->toBeNull(
        'No transaction matched '.json_encode($expected, JSON_UNESCAPED_UNICODE),
    );
}
