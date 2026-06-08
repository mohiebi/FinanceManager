<?php

namespace App\Services;

use App\Enums\AssetType;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class AssetPriceService
{
    private const TGJU_PRICE_PATHS = [
        'usd' => [
            'source' => 'home',
            'xpath' => '/html/body/main/div[4]/div[8]/div[2]/div/div[1]/div[2]/div/div[1]/table/tbody/tr[1]/td[1]',
            'divide_by' => 10,
        ],
        'usdt' => [
            'source' => 'home',
            'xpath' => '/html/body/main/div[1]/div[2]/div/ul/li[8]/span[1]/span',
            'divide_by' => 10,
        ],
        'eur' => [
            'source' => 'currency',
            'xpath' => '/html/body/main/div[4]/div/div/div[1]/table/tbody/tr[2]/td[1]',
            'divide_by' => 10,
        ],
        'gold_750' => [
            'source' => 'home',
            'xpath' => '/html/body/main/div[1]/div[2]/div/ul/li[4]/span[1]/span',
            'divide_by' => 10,
        ],
        'coin' => [
            'source' => 'home',
            'xpath' => '/html/body/main/div[1]/div[2]/div/ul/li[5]/span[1]/span',
            'divide_by' => 10,
        ],
        'silver' => [
            'source' => 'home',
            'xpath' => '/html/body/main/div[4]/div[3]/div[2]/table/tbody/tr[4]/td[1]',
            'divide_by' => 10,
        ],
        'gold_ounce' => [
            'source' => 'home',
            'xpath' => '/html/body/main/div[1]/div[2]/div/ul/li[2]/span[1]/span',
            'divide_by' => 1,
        ],
        'bitcoin_usd' => [
            'source' => 'home',
            'xpath' => '/html/body/main/div[1]/div[2]/div/ul/li[9]/span[1]/span',
            'divide_by' => 1,
        ],
    ];

    public function priceFor(AssetType $type): float
    {
        return (float) ($this->allPrices()[$type->value] ?? 0);
    }

    public function valueOf(AssetType $type, float $quantity): float
    {
        return $this->priceFor($type) * $quantity;
    }

    /**
     * @return array<string, float>
     */
    public function allPrices(): array
    {
        $tgjuPrices = $this->tgjuPrices();

        return [
            'gold' => $tgjuPrices['gold_750'] ?? 0.0,
            'silver' => $tgjuPrices['silver'] ?? 0.0,
            'usd' => $tgjuPrices['usd'] ?? 0.0,
            'eur' => $tgjuPrices['eur'] ?? 0.0,
            'coin' => $tgjuPrices['coin'] ?? 0.0,
            'bitcoin' => $tgjuPrices['bitcoin'] ?? 0.0,
        ];
    }

    /**
     * @return array<string, float>
     */
    public function tgjuPrices(): array
    {
        if (! config('services.tgju.enabled', true)) {
            return [];
        }

        return Cache::remember(
            'asset-prices.tgju',
            now()->addSeconds((int) config('services.tgju.cache_seconds', 300)),
            fn (): array => $this->fetchTgjuPrices(),
        );
    }

    /**
     * @return array<string, float>
     */
    private function fetchTgjuPrices(): array
    {
        $prices = [];

        // Keep the total time spent fetching well under PHP's max_execution_time
        // (each source/URL pair has its own request timeout, and they run
        // sequentially, so without an overall budget they can add up and
        // trigger a fatal "Maximum execution time exceeded" error).
        $deadline = microtime(true) + (float) config('services.tgju.total_budget_seconds', 18);

        foreach ($this->tgjuSourceUrls() as $source => $urls) {
            foreach ($urls as $url) {
                $remaining = $deadline - microtime(true);

                if ($remaining <= 0) {
                    break 2;
                }

                $requestTimeout = min(
                    (int) config('services.tgju.timeout', 8),
                    max(1, (int) ceil($remaining)),
                );

                try {
                    $response = Http::timeout($requestTimeout)
                        ->connectTimeout(min(
                            (int) config('services.tgju.connect_timeout', 4),
                            $requestTimeout,
                        ))
                        ->get($url);

                    if (! $response->successful()) {
                        continue;
                    }

                    $sourcePrices = $this->parseTgjuHtml($response->body(), $source);

                    if ($sourcePrices !== []) {
                        $prices = [
                            ...$prices,
                            ...$sourcePrices,
                        ];

                        break;
                    }
                } catch (Throwable) {
                    continue;
                }
            }
        }

        if (isset($prices['bitcoin_usd'], $prices['usd'])) {
            $prices['bitcoin'] = $prices['bitcoin_usd'] * $prices['usd'];
        }

        return $prices;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function tgjuSourceUrls(): array
    {
        return [
            'home' => [
                (string) config('services.tgju.url', 'https://www.tgju.org/'),
                (string) config('services.tgju.fallback_url', 'http://www.tgju.org/'),
            ],
            'currency' => [
                (string) config('services.tgju.currency_url', 'https://www.tgju.org/currency'),
                (string) config('services.tgju.currency_fallback_url', 'http://www.tgju.org/currency'),
            ],
        ];
    }

    /**
     * @return array<string, float>
     */
    private function parseTgjuHtml(string $html, string $source): array
    {
        if (trim($html) === '') {
            return [];
        }

        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $prices = [];

        foreach (self::TGJU_PRICE_PATHS as $key => $settings) {
            if ($settings['source'] !== $source) {
                continue;
            }

            $value = $this->readPrice($xpath, $settings['xpath'], $settings['divide_by']);

            if ($value !== null) {
                $prices[$key] = $value;
            }
        }

        if (isset($prices['gold_750'])) {
            $prices['gold_900'] = $prices['gold_750'] / 750 * 900;
        }

        return $prices;
    }

    private function readPrice(DOMXPath $xpath, string $query, int $divideBy): ?float
    {
        $rawValue = trim((string) $xpath->evaluate("string({$query})"));
        $price = $this->parseNumber($rawValue);

        if ($price === null) {
            return null;
        }

        return $price / $divideBy;
    }

    private function parseNumber(string $value): ?float
    {
        $normalized = strtr($value, [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
        ]);

        $normalized = preg_replace('/[^\d.]/', '', $normalized);

        if ($normalized === null || $normalized === '') {
            return null;
        }

        return (float) $normalized;
    }
}
