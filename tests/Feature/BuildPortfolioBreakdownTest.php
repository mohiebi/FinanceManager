<?php

use App\Actions\Investments\BuildExposureBreakdown;
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
function seedPortfolioFixture(bool $seedEntries = true): array
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

    if ($seedEntries) {
        seedPortfolioEntries($user, $assets, $vectors['entries']);
    }

    return [$user, $assets];
}

/**
 * @param  array<int, InvestmentAsset>  $assets
 * @param  array<int, array<string, mixed>>  $entries
 */
function seedPortfolioEntries(User $user, array $assets, array $entries): void
{
    foreach ($entries as $entry) {
        Investment::query()->create([
            'user_id' => $user->id,
            'investment_asset_id' => $assets[$entry['investment_asset_id']]->id,
            'asset_type' => $assets[$entry['investment_asset_id']]->slug,
            'kind' => $entry['kind'],
            'quantity' => $entry['quantity'],
            'cost_basis' => $entry['cost_basis'],
            'cost_basis_currency' => $entry['cost_basis_currency'],
            'sale_price' => $entry['sale_price'],
            'sale_price_currency' => $entry['sale_price_currency'],
            'occurred_at' => '2026-07-01',
        ]);
    }
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
        ->toBe([
            'id',
            'investment_asset_id',
            // Plaintext, and the only thing telling the browser which rows are
            // disposals — the sign is inside the ciphertext.
            'kind',
            // Also plaintext, and what savings goals use to date the baseline.
            'occurred_at',
            'quantity',
            'cost_basis',
            'cost_basis_currency',
            'sale_price',
            'sale_price_currency',
        ]);

    expect($payload['assets'][0]['id'])->toBe($assets[1]->id)
        ->and($payload['assets'][0]['price'])->toBe(5000000.0)
        ->and($payload['assets'][0]['price_available'])->toBeTrue();
});

test('a partial sale leaves the remaining cost basis untouched', function () {
    $vectors = portfolioVectors();
    $disposal = $vectors['disposal'];

    // Same fixture the browser port asserts — see tests/js/portfolio.test.ts.
    [$user, $assets] = seedPortfolioFixture(seedEntries: false);
    seedPortfolioEntries($user, $assets, $disposal['entries']);

    $builder = app(BuildPortfolioBreakdown::class);
    $result = $builder->handle($builder->entriesFor($user), Currency::from($disposal['target']));

    expect($result['assets'])->toHaveCount(1);

    foreach ($disposal['asset'] as $field => $value) {
        $actual = $result['assets'][0][$field];

        expect($actual)->toBe(portfolioExpectation($actual, $value), "disposal: {$field}");
    }

    foreach ($disposal['summary'] as $field => $value) {
        $actual = $result['summary'][$field];

        expect($actual)->toBe(portfolioExpectation($actual, $value), "disposal: summary.{$field}");
    }
});

test('selling everything closes the position without inventing a profit', function () {
    [$user, $assets] = seedPortfolioFixture(seedEntries: false);

    seedPortfolioEntries($user, $assets, [
        [
            'investment_asset_id' => 1,
            'kind' => 'buy',
            'quantity' => 2,
            'cost_basis' => 4000000,
            'cost_basis_currency' => 'toman',
            'sale_price' => null,
            'sale_price_currency' => null,
        ],
        [
            'investment_asset_id' => 1,
            'kind' => 'sell',
            'quantity' => -2,
            'cost_basis' => 4000000,
            'cost_basis_currency' => 'toman',
            'sale_price' => 5000000,
            'sale_price_currency' => 'toman',
        ],
    ]);

    $builder = app(BuildPortfolioBreakdown::class);
    $result = $builder->handle($builder->entriesFor($user), Currency::Toman);

    // Nothing held, so there is no unrealised figure to report — but the gain that
    // was actually banked has to survive.
    expect($result['assets'][0]['quantity'])->toBe(0.0)
        ->and($result['assets'][0]['pnl'])->toBeNull()
        ->and($result['summary']['total_pnl'])->toBeNull()
        ->and($result['summary']['total_realised_pnl'])->toBe(2000000.0)
        ->and($result['summary']['has_realised_data'])->toBeTrue();
});

/**
 * Two holdings that are one bet.
 *
 * Half coins track parity gold, so the pair has to roll up into a single gold
 * exposure — the whole reason the columns exist. Asserted against the same
 * fixture the browser port is asserted against; see tests/js/portfolio.test.ts.
 */
test('the exposure roll-up matches the browser port', function () {
    $vectors = portfolioVectors()['exposure'];

    config(['services.tgju.enabled' => true]);
    Cache::put('asset-prices.tgju', [], now()->addMinutes(5));

    $user = User::factory()->create();
    $assets = [];

    foreach ($vectors['assets'] as $asset) {
        $assets[$asset['id']] = InvestmentAsset::query()->create([
            'user_id' => $user->id,
            'name' => $asset['label'],
            'slug' => $asset['key'],
            'unit' => $asset['unit'],
            'color' => $asset['color'],
            'asset_class' => $asset['asset_class'],
            'underlying_asset_id' => $asset['underlying_asset_id'] === null
                ? null
                : $assets[$asset['underlying_asset_id']]->id,
            'underlying_ratio' => $asset['underlying_ratio'],
            'price_source_type' => 'manual',
            'price_source_config' => ['price' => $asset['price']],
        ]);
    }

    seedPortfolioEntries($user, $assets, $vectors['entries']);

    $builder = app(BuildPortfolioBreakdown::class);
    $currency = Currency::from($vectors['target']);
    $breakdown = $builder->handle($builder->entriesFor($user), $currency);
    $result = app(BuildExposureBreakdown::class)->handle(
        $breakdown['assets'],
        $builder->formatter($currency),
    );

    // Order is part of the contract — largest exposure first.
    expect(array_column($result['exposures'], 'label'))
        ->toBe(array_column($vectors['exposures'], 'label'))
        ->and(array_column($result['classes'], 'key'))
        ->toBe(array_column($vectors['classes'], 'key'))
        ->and($result['has_unpriced_assets'])->toBe($vectors['has_unpriced_assets']);

    foreach ($vectors['exposures'] as $index => $expected) {
        $actualGroup = $result['exposures'][$index];

        expect(array_column($actualGroup['members'], 'slug'))
            ->toBe($expected['member_slugs'], "exposure {$expected['label']}: members");

        // exposure_id and member_slugs are checked above or are database ids the
        // fixture cannot know — the browser asserts the id, which is its own.
        $comparable = array_diff_key($expected, ['member_slugs' => null, 'exposure_id' => null]);

        foreach ($comparable as $field => $value) {
            $actual = $actualGroup[$field];

            expect($actual)->toBe(
                portfolioExpectation($actual, $value),
                "exposure {$expected['label']}.{$field}",
            );
        }
    }

    foreach ($vectors['classes'] as $index => $expected) {
        foreach ($expected as $field => $value) {
            $actual = $result['classes'][$index][$field];

            expect($actual)->toBe(
                portfolioExpectation($actual, $value),
                "class {$expected['key']}.{$field}",
            );
        }
    }
});
