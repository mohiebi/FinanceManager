<?php

use App\Actions\Transactions\CurrencyConverter;
use App\Enums\Currency;
use Illuminate\Support\Facades\Cache;

test('it returns zero when live prices are unavailable', function () {
    Cache::flush();
    config(['services.tgju.enabled' => false]);

    $converter = app(CurrencyConverter::class);

    expect($converter->convert(1, Currency::Usd, Currency::Toman))->toBe(0.0)
        ->and($converter->convert(1500000, Currency::Toman, Currency::Usd))->toBe(0.0);
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
