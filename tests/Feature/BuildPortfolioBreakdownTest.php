<?php

use App\Actions\Investments\BuildPortfolioBreakdown;
use App\Enums\Currency;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * The server's portfolio breakdown, checked against the fixture the browser's
 * port is checked against.
 *
 * With the vault armed the same maths runs in resources/js/lib/portfolio.ts, so
 * two implementations exist. This fixture is the only thing keeping them honest —
 * see tests/js/portfolio.test.ts.
 */
function portfolioVectors(): array
{
    return json_decode(
        file_get_contents(base_path('tests/fixtures/portfolio-vectors.json')),
        true,
    );
}

/**
 * JSON has one number type; PHP has two. Bridges `3` against `3.0` without
 * loosening the comparison for strings, booleans or null.
 */
function portfolioExpectation(mixed $actual, mixed $expected): mixed
{
    return is_float($actual) && is_int($expected) ? (float) $expected : $expected;
}

/**
 * @return array{0: User, 1: array<int, InvestmentAsset>}
 */
function seedPortfolioFixture(): array
{
    $vectors = portfolioVectors();

    config(['services.tgju.enabled' => true]);
    Cache::put('asset-prices.tgju', [
        'usd' => $vectors['rates']['tomanPerUsd'],
        'eur' => $vectors['rates']['tomanPerEur'],
    ], now()->addMinutes(5));

    $user = User::factory()->create();
    $assets = [];

    // Manual price sources, so the numbers under test never depend on a scrape.
    foreach ($vectors['assets'] as $asset) {
        $assets[$asset['id']] = InvestmentAsset::query()->create([
            'user_id' => $user->id,
            'name' => $asset['label'],
            'slug' => $asset['key'],
            'unit' => $asset['unit'],
            'color' => $asset['color'],
            'price_source_type' => 'manual',
            'price_source_config' => ['price' => $asset['price']],
        ]);
    }

    foreach ($vectors['entries'] as $entry) {
        Investment::query()->create([
            'user_id' => $user->id,
            'investment_asset_id' => $assets[$entry['investment_asset_id']]->id,
            'asset_type' => $assets[$entry['investment_asset_id']]->slug,
            'quantity' => $entry['quantity'],
            'cost_basis' => $entry['cost_basis'],
            'cost_basis_currency' => $entry['cost_basis_currency'],
            'occurred_at' => '2026-07-01',
        ]);
    }

    return [$user, $assets];
}

test('the breakdown matches the browser port', function () {
    $vectors = portfolioVectors();
    [$user] = seedPortfolioFixture();

    $builder = app(BuildPortfolioBreakdown::class);
    $entries = $builder->entriesFor($user);

    foreach ($vectors['cases'] as $case) {
        $result = $builder->handle($entries, Currency::from($case['target']));

        // Order is part of the contract — highest current value first.
        expect(array_column($result['assets'], 'key'))
            ->toBe(array_column($case['assets'], 'key'), $case['target']);

        foreach ($case['assets'] as $index => $expected) {
            foreach ($expected as $field => $value) {
                $actual = $result['assets'][$index][$field];

                expect($actual)->toBe(
                    portfolioExpectation($actual, $value),
                    "{$case['target']}: {$expected['key']}.{$field}",
                );
            }
        }

        foreach ($case['summary'] as $field => $value) {
            $actual = $result['summary'][$field];

            expect($actual)->toBe(
                portfolioExpectation($actual, $value),
                "{$case['target']}: summary.{$field}",
            );
        }
    }
});

test('the dashboard snapshot matches the browser port', function () {
    [$user] = seedPortfolioFixture();

    $snapshot = app(BuildPortfolioBreakdown::class)->snapshot($user, Currency::Toman);

    expect($snapshot['net_worth_formatted'])->toBe('24,000,000')
        ->and($snapshot['asset_count'])->toBe(2)
        ->and(array_map(
            fn (array $asset): array => [$asset['key'], (int) $asset['share']],
            $snapshot['top_assets'],
        ))->toBe([
            ['parity-gold', 63],
            ['parity-dollar', 38],
        ]);
});

test('the client payload carries the holdings and the public prices, and nothing else', function () {
    [$user, $assets] = seedPortfolioFixture();

    $payload = app(BuildPortfolioBreakdown::class)
        ->clientPayload($user, Currency::Toman);

    $vectors = portfolioVectors();

    expect($payload['entries'])->toHaveCount(count($vectors['entries']))
        ->and($payload['assets'])->toHaveCount(count($vectors['assets']))
        ->and($payload['rates'])->toBe([
            'tomanPerUsd' => (float) $vectors['rates']['tomanPerUsd'],
            'tomanPerEur' => (float) $vectors['rates']['tomanPerEur'],
        ]);

    // The prices are market data, so they travel; the holdings are the user's, so
    // only what the browser cannot recompute is sent.
    expect(array_keys($payload['entries'][0]))
        ->toBe(['id', 'investment_asset_id', 'quantity', 'cost_basis', 'cost_basis_currency']);

    expect($payload['assets'][0]['id'])->toBe($assets[1]->id)
        ->and($payload['assets'][0]['price'])->toBe(5000000.0)
        ->and($payload['assets'][0]['price_available'])->toBeTrue();
});
