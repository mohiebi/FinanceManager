<?php

use App\Actions\Transactions\CurrencyConverter;
use App\Enums\Currency;
use Illuminate\Support\Facades\Cache;

test('it agrees with the shared money vectors the browser also asserts', function () {
    Cache::flush();
    config(['services.tgju.enabled' => true]);

    $vectors = json_decode(
        file_get_contents(base_path('tests/fixtures/money-vectors.json')),
        true,
    );

    Cache::put('asset-prices.tgju', [
        'usd' => $vectors['rates']['tomanPerUsd'],
        'eur' => $vectors['rates']['tomanPerEur'],
    ], now()->addMinutes(5));

    $converter = app(CurrencyConverter::class);

    // The same file is asserted by tests/js/money.test.ts. Two implementations of
    // money maths is a drift risk; a shared fixture makes it a checked invariant.
    foreach ($vectors['conversions'] as $vector) {
        expect($converter->format(
            $vector['amount'],
            Currency::from($vector['from']),
            Currency::from($vector['to']),
        ))->toBe($vector['expected'], "{$vector['amount']} {$vector['from']} -> {$vector['to']}");
    }
});

test('it returns zero when live prices are unavailable', function () {
    Cache::flush();
    config(['services.tgju.enabled' => false]);

    $converter = app(CurrencyConverter::class);

    expect($converter->convert(1, Currency::Usd, Currency::Toman))->toBe(0.0)
        ->and($converter->convert(1500000, Currency::Toman, Currency::Usd))->toBe(0.0);
});

test('it returns the original amount when source and target currencies match', function () {
    Cache::flush();
    config(['services.tgju.enabled' => false]);

    $converter = app(CurrencyConverter::class);

    expect($converter->convert(1500000, Currency::Toman, Currency::Toman))->toBe(1500000.0)
        ->and($converter->format(42, Currency::Eur, Currency::Eur))->toBe('42.00');
});

test('it converts using the same live rate the Investments/Portfolio pages use', function () {
    Cache::flush();

    config(['services.tgju.enabled' => true]);

    // Seed the same cache key AssetPriceService reads from, so this test
    // exercises CurrencyConverter without re-testing the tgju scrape itself.
    Cache::put('asset-prices.tgju', [
        'usd' => 172100.0,
        'eur' => 186468.31,
    ], now()->addMinutes(5));

    $converter = app(CurrencyConverter::class);

    expect($converter->convert(615, Currency::Usd, Currency::Toman))->toBe(105841500.0);
});
