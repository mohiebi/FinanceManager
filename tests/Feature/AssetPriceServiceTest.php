<?php

use App\Enums\AssetType;
use App\Enums\InvestmentAssetPriceSource;
use App\Models\InvestmentAsset;
use App\Models\User;
use App\Services\AssetPriceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('asset price service reads tgju prices from configured xpaths', function () {
    Cache::flush();

    config([
        'services.tgju.enabled' => true,
        'services.tgju.url' => 'https://www.tgju.org/',
        'services.tgju.fallback_url' => 'http://www.tgju.org/',
        'services.tgju.currency_url' => 'https://www.tgju.org/currency',
        'services.tgju.currency_fallback_url' => 'http://www.tgju.org/currency',
    ]);

    Http::fake([
        'https://www.tgju.org/' => Http::response(tgjuHtml([
            '/html/body/main/div[4]/div[8]/div[2]/div/div[1]/div[2]/div/div[1]/table/tbody/tr[1]/td[1]' => '1,000,000',
            '/html/body/main/div[1]/div[2]/div/ul/li[8]/span[1]/span' => '1,010,000',
            '/html/body/main/div[1]/div[2]/div/ul/li[4]/span[1]/span' => '120,000,000',
            '/html/body/main/div[1]/div[2]/div/ul/li[5]/span[1]/span' => '970,000,000',
            '/html/body/main/div[4]/div[3]/div[2]/table/tbody/tr[4]/td[1]' => '1,400,000',
            '/html/body/main/div[4]/div[3]/div[1]/table/tbody/tr[2]/td[1]' => '30.25',
            '/html/body/main/div[1]/div[2]/div/ul/li[2]/span[1]/span' => '2,350.50',
            '/html/body/main/div[1]/div[2]/div/ul/li[9]/span[1]/span' => '108,500',
        ])),
        'https://www.tgju.org/currency' => Http::response(tgjuHtml([
            '/html/body/main/div[4]/div/div/div[1]/table/tbody/tr[2]/td[1]' => '1,180,000',
        ])),
    ]);

    $service = app(AssetPriceService::class);

    expect($service->priceFor(AssetType::Usd))->toBe(100000.0)
        ->and($service->priceFor(AssetType::Gold))->toBe(12000000.0)
        ->and($service->priceFor(AssetType::Silver))->toBe(140000.0)
        ->and($service->priceFor(AssetType::Eur))->toBe(118000.0)
        ->and($service->priceFor(AssetType::Coin))->toBe(97000000.0)
        ->and($service->priceFor(AssetType::Bitcoin))->toBe(10850000000.0)
        ->and($service->tgjuPrices())->toMatchArray([
            'usdt' => 101000.0,
            'gold_900' => 14400000.0,
            'gold_ounce' => 2350.5,
            'silver_ounce_usd' => 30.25,
            'silver_ounce' => 3025000.0,
            'bitcoin_usd' => 108500.0,
        ]);
});

test('asset price service returns zero when tgju is unavailable and no price has ever been fetched', function () {
    Cache::flush();

    config([
        'services.tgju.enabled' => true,
        'services.tgju.url' => 'https://www.tgju.org/',
        'services.tgju.fallback_url' => 'http://www.tgju.org/',
        'services.tgju.currency_url' => 'https://www.tgju.org/currency',
        'services.tgju.currency_fallback_url' => 'http://www.tgju.org/currency',
    ]);

    Http::fake([
        'https://www.tgju.org/' => Http::response('', 500),
        'http://www.tgju.org/' => Http::response('', 500),
        'https://www.tgju.org/currency' => Http::response('', 500),
        'http://www.tgju.org/currency' => Http::response('', 500),
    ]);

    $service = app(AssetPriceService::class);

    expect($service->priceFor(AssetType::Usd))->toBe(0.0)
        ->and($service->priceFor(AssetType::Gold))->toBe(0.0)
        ->and($service->priceFor(AssetType::Silver))->toBe(0.0)
        ->and($service->priceFor(AssetType::Eur))->toBe(0.0)
        ->and($service->priceFor(AssetType::Coin))->toBe(0.0)
        ->and($service->priceFor(AssetType::Bitcoin))->toBe(0.0);
});

test('asset price service falls back to the last known prices when a fresh fetch fails', function () {
    Cache::flush();

    config([
        'services.tgju.enabled' => true,
        'services.tgju.url' => 'https://www.tgju.org/',
        'services.tgju.fallback_url' => 'http://www.tgju.org/',
        'services.tgju.currency_url' => 'https://www.tgju.org/currency',
        'services.tgju.currency_fallback_url' => 'http://www.tgju.org/currency',
    ]);

    // First call succeeds (establishing a "last known" price), every subsequent
    // call to the same URL fails — simulating tgju going down after a TTL expiry
    // or a manual sync forgetting the cache.
    Http::fake([
        'https://www.tgju.org/' => Http::sequence()
            ->push(tgjuHtml([
                '/html/body/main/div[4]/div[8]/div[2]/div/div[1]/div[2]/div/div[1]/table/tbody/tr[1]/td[1]' => '1,721,000',
            ]))
            ->push('', 500),
        'http://www.tgju.org/' => Http::response('', 500),
        'https://www.tgju.org/currency' => Http::response(tgjuHtml([])),
        'http://www.tgju.org/currency' => Http::response('', 500),
    ]);

    $service = app(AssetPriceService::class);

    expect($service->priceFor(AssetType::Usd))->toBe(172100.0);

    Cache::forget('asset-prices.tgju');

    expect($service->priceFor(AssetType::Usd))->toBe(172100.0)
        ->and($service->pricesAvailable())->toBeTrue();
});

test('asset price service preserves last known prices when a fresh fetch is only partially successful', function () {
    Cache::flush();

    config([
        'services.tgju.enabled' => true,
        'services.tgju.url' => 'https://www.tgju.org/',
        'services.tgju.fallback_url' => 'http://www.tgju.org/',
        'services.tgju.currency_url' => 'https://www.tgju.org/currency',
        'services.tgju.currency_fallback_url' => 'http://www.tgju.org/currency',
    ]);

    Cache::forever('asset-prices.tgju.last_known', [
        'usd' => 172100.0,
        'eur' => 198000.0,
    ]);

    Http::fake([
        'https://www.tgju.org/' => Http::response('', 500),
        'http://www.tgju.org/' => Http::response('', 500),
        'https://www.tgju.org/currency' => Http::response(tgjuHtml([
            '/html/body/main/div[4]/div/div/div[1]/table/tbody/tr[2]/td[1]' => '2,221,600',
        ])),
    ]);

    $prices = app(AssetPriceService::class)->tgjuPrices();

    expect($prices)
        ->toMatchArray([
            'usd' => 172100.0,
            'eur' => 222160.0,
        ])
        ->and(Cache::get('asset-prices.tgju.last_known'))->toMatchArray([
            'usd' => 172100.0,
            'eur' => 222160.0,
        ]);
});

test('lastSyncedAt stays null when the fetch fails', function () {
    Cache::flush();

    config([
        'services.tgju.enabled' => true,
        'services.tgju.url' => 'https://www.tgju.org/',
        'services.tgju.fallback_url' => 'http://www.tgju.org/',
        'services.tgju.currency_url' => 'https://www.tgju.org/currency',
        'services.tgju.currency_fallback_url' => 'http://www.tgju.org/currency',
    ]);

    Http::fake([
        'https://www.tgju.org/' => Http::response('', 500),
        'http://www.tgju.org/' => Http::response('', 500),
        'https://www.tgju.org/currency' => Http::response('', 500),
        'http://www.tgju.org/currency' => Http::response('', 500),
    ]);

    $service = app(AssetPriceService::class);
    $service->tgjuPrices();

    expect($service->lastSyncedAt())->toBeNull();
});

test('lastSyncedAt records a timestamp after a successful fetch', function () {
    Cache::flush();

    config([
        'services.tgju.enabled' => true,
        'services.tgju.url' => 'https://www.tgju.org/',
        'services.tgju.fallback_url' => 'http://www.tgju.org/',
        'services.tgju.currency_url' => 'https://www.tgju.org/currency',
        'services.tgju.currency_fallback_url' => 'http://www.tgju.org/currency',
    ]);

    Http::fake([
        'https://www.tgju.org/' => Http::response(tgjuHtml([
            '/html/body/main/div[4]/div[8]/div[2]/div/div[1]/div[2]/div/div[1]/table/tbody/tr[1]/td[1]' => '1,000,000',
        ])),
        'https://www.tgju.org/currency' => Http::response(tgjuHtml([])),
    ]);

    $service = app(AssetPriceService::class);

    expect($service->lastSyncedAt())->toBeNull();

    $prices = $service->tgjuPrices();

    expect($prices)->not->toBe([])
        ->and($service->lastSyncedAt())->not->toBeNull();
});

test('invalidateCache forgets the global tgju cache and the user custom asset caches', function () {
    Cache::flush();

    $user = User::factory()->create();

    Cache::put('asset-prices.tgju', ['usd' => 100000.0], now()->addMinutes(5));
    Cache::forever('asset-prices.tgju.synced_at', now()->toIso8601String());

    $asset = InvestmentAsset::query()->create([
        'name' => 'Custom JSON asset',
        'unit' => 'unit',
        'color' => '#02CD86',
        'price_source_type' => InvestmentAssetPriceSource::Json,
        'price_source_config' => ['url' => 'https://api.example.com/price', 'path' => 'price'],
    ]);

    $assetCacheKey = 'investment-asset-price.'.$asset->id.'.'.md5(json_encode($asset->price_source_config) ?: '');
    Cache::put($assetCacheKey, 1234.5, now()->addMinutes(5));

    $service = app(AssetPriceService::class);
    $service->invalidateCache($user);

    expect(Cache::get('asset-prices.tgju'))->toBeNull()
        ->and(Cache::get($assetCacheKey))->toBeNull()
        ->and($service->lastSyncedAt())->not->toBeNull();
});

test('invalidateCache does not error when the user has no custom assets', function () {
    Cache::flush();

    $user = User::factory()->create();

    app(AssetPriceService::class)->invalidateCache($user);

    expect(Cache::get('asset-prices.tgju'))->toBeNull();
});

test('refreshTgjuPrices forces a fresh fetch and re-primes the cache even when not yet expired', function () {
    Cache::flush();

    config([
        'services.tgju.enabled' => true,
        'services.tgju.url' => 'https://www.tgju.org/',
        'services.tgju.fallback_url' => 'http://www.tgju.org/',
        'services.tgju.currency_url' => 'https://www.tgju.org/currency',
        'services.tgju.currency_fallback_url' => 'http://www.tgju.org/currency',
    ]);

    // Cache is already warm with a stale value that has NOT expired.
    Cache::put('asset-prices.tgju', ['usd' => 1.0], now()->addMinutes(5));

    Http::fake([
        'https://www.tgju.org/' => Http::response(tgjuHtml([
            '/html/body/main/div[4]/div[8]/div[2]/div/div[1]/div[2]/div/div[1]/table/tbody/tr[1]/td[1]' => '1,721,000',
        ])),
        'https://www.tgju.org/currency' => Http::response(tgjuHtml([])),
    ]);

    $service = app(AssetPriceService::class);
    $prices = $service->refreshTgjuPrices();

    expect($prices['usd'])->toBe(172100.0)
        ->and(Cache::get('asset-prices.tgju')['usd'])->toBe(172100.0)
        ->and($service->lastSyncedAt())->not->toBeNull();
});

/**
 * @param  array<string, string>  $values
 */
function tgjuHtml(array $values): string
{
    $document = new DOMDocument;
    $html = $document->appendChild($document->createElement('html'));
    $body = $html->appendChild($document->createElement('body'));
    $main = $body->appendChild($document->createElement('main'));

    foreach ($values as $path => $value) {
        tgjuPutValue($document, $main, $path, $value);
    }

    return $document->saveHTML();
}

function tgjuPutValue(DOMDocument $document, DOMElement $main, string $path, string $value): void
{
    $segments = array_slice(explode('/', trim($path, '/')), 3);
    $node = $main;

    foreach ($segments as $segment) {
        preg_match('/^([a-z]+)(?:\[(\d+)])?$/', $segment, $matches);

        $tag = $matches[1];
        $index = (int) ($matches[2] ?? 1);
        $node = tgjuEnsureChild($document, $node, $tag, $index);
    }

    $node->nodeValue = $value;
}

function tgjuEnsureChild(DOMDocument $document, DOMElement $parent, string $tag, int $index): DOMElement
{
    $children = [];

    foreach ($parent->childNodes as $child) {
        if ($child instanceof DOMElement && $child->tagName === $tag) {
            $children[] = $child;
        }
    }

    while (count($children) < $index) {
        $children[] = $parent->appendChild($document->createElement($tag));
    }

    return $children[$index - 1];
}
