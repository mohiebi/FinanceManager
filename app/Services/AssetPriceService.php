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
    /**
     * Fallback prices in Toman per unit.
     *
     * Gold/Silver: per gram
     * USD/EUR: per 1 unit of foreign currency
     * Coin: per Bahar Azadi coin
     * Bitcoin: per 1 BTC
     */
    private const FALLBACK_PRICES_IN_TOMAN = [
        'gold' => 12_000_000,
        'silver' => 140_000,
        'usd' => 91_500,
        'eur' => 101_000,
        'coin' => 98_000_000,
        'bitcoin' => 14_500_000_000,
    ];

    private const TGJU_PRICE_PATHS = [
        'usd' => [
            'xpath' => '/html/body/main/div[4]/div[8]/div[2]/div/div[1]/div[2]/div/div[1]/table/tbody/tr[1]/td[1]',
            'divide_by' => 10,
        ],
        'usdt' => [
            'xpath' => '/html/body/main/div[1]/div[2]/div/ul/li[8]/span[1]/span',
            'divide_by' => 10,
        ],
        'gold_750' => [
            'xpath' => '/html/body/main/div[1]/div[2]/div/ul/li[4]/span[1]/span',
            'divide_by' => 10,
        ],
        'silver' => [
            'xpath' => '/html/body/main/div[4]/div[3]/div[2]/table/tbody/tr[4]/td[1]',
            'divide_by' => 10,
        ],
        'gold_ounce' => [
            'xpath' => '/html/body/main/div[1]/div[2]/div/ul/li[2]/span[1]/span',
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
            'gold' => $tgjuPrices['gold_750'] ?? self::FALLBACK_PRICES_IN_TOMAN['gold'],
            'silver' => $tgjuPrices['silver'] ?? self::FALLBACK_PRICES_IN_TOMAN['silver'],
            'usd' => $tgjuPrices['usd'] ?? self::FALLBACK_PRICES_IN_TOMAN['usd'],
            'eur' => self::FALLBACK_PRICES_IN_TOMAN['eur'],
            'coin' => self::FALLBACK_PRICES_IN_TOMAN['coin'],
            'bitcoin' => self::FALLBACK_PRICES_IN_TOMAN['bitcoin'],
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
        foreach ($this->tgjuUrls() as $url) {
            try {
                $response = Http::timeout((int) config('services.tgju.timeout', 8))
                    ->connectTimeout((int) config('services.tgju.connect_timeout', 4))
                    ->get($url);

                if (! $response->successful()) {
                    continue;
                }

                $prices = $this->parseTgjuHtml($response->body());

                if ($prices !== []) {
                    return $prices;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function tgjuUrls(): array
    {
        return [
            (string) config('services.tgju.url', 'https://www.tgju.org/'),
            (string) config('services.tgju.fallback_url', 'http://www.tgju.org/'),
        ];
    }

    /**
     * @return array<string, float>
     */
    private function parseTgjuHtml(string $html): array
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
