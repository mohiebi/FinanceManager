<?php

use App\Models\InvestmentAsset;
use App\Services\AssetPriceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('custom assets support manual prices', function () {
    $asset = pricedAsset('manual', ['price' => 1234.5]);

    expect(app(AssetPriceService::class)->priceFor($asset))->toBe(1234.5);
});

test('custom assets support safe formulas', function () {
    $asset = pricedAsset('formula', ['formula' => '(2 + 3) * 4']);

    expect(app(AssetPriceService::class)->priceFor($asset))->toBe(20.0);
});

test('invalid formulas return zero', function () {
    $missingVariable = pricedAsset('formula', ['formula' => 'missing * 2']);
    $divideByZero = pricedAsset('formula', ['formula' => '10 / (5 - 5)']);
    $service = app(AssetPriceService::class);

    expect($service->priceFor($missingVariable))->toBe(0.0)
        ->and($service->priceFor($divideByZero))->toBe(0.0);
});

test('custom assets support json url extraction', function () {
    Cache::flush();

    Http::fake([
        'https://api.example.com/price' => Http::response([
            'data' => ['price' => '1,250'],
        ]),
    ]);

    $asset = pricedAsset('json', [
        'url' => 'https://api.example.com/price',
        'path' => 'data.price',
        'divide_by' => 2,
        'formula' => 'value * 3',
    ]);

    expect(app(AssetPriceService::class)->priceFor($asset))->toBe(1875.0);
});

test('custom assets support xml xpath extraction', function () {
    Cache::flush();

    Http::fake([
        'https://api.example.com/feed.xml' => Http::response('<root><price>2,400</price></root>'),
    ]);

    $asset = pricedAsset('xml', [
        'url' => 'https://api.example.com/feed.xml',
        'xpath' => '//price',
    ]);

    expect(app(AssetPriceService::class)->priceFor($asset))->toBe(2400.0);
});

test('custom asset urls reject private hosts', function () {
    Cache::flush();
    Http::fake();

    $asset = pricedAsset('json', [
        'url' => 'http://127.0.0.1/price',
        'path' => 'price',
    ]);

    expect(app(AssetPriceService::class)->priceFor($asset))->toBe(0.0);

    Http::assertNothingSent();
});

/**
 * @param  array<string, mixed>  $config
 */
function pricedAsset(string $sourceType, array $config): InvestmentAsset
{
    return InvestmentAsset::query()->create([
        'name' => 'Priced asset '.str()->random(8),
        'unit' => 'unit',
        'color' => '#02CD86',
        'price_source_type' => $sourceType,
        'price_source_config' => $config,
    ]);
}
