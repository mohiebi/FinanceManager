<?php

use App\Enums\AssetType;
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
            'bitcoin_usd' => 108500.0,
        ]);
});

test('asset price service returns zero when tgju is unavailable', function () {
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
