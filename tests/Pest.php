<?php

use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
