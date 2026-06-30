<?php

namespace App\Services;

use App\Enums\AssetType;
use App\Enums\InvestmentAssetPriceSource;
use App\Models\InvestmentAsset;
use App\Models\User;
use App\Support\SafeFormulaEvaluator;
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

    public function __construct(private SafeFormulaEvaluator $formulaEvaluator) {}

    public function priceFor(AssetType|InvestmentAsset|string $asset): float
    {
        if ($asset instanceof InvestmentAsset) {
            return $this->priceForInvestmentAsset($asset);
        }

        $key = $asset instanceof AssetType ? $asset->value : $asset;

        return (float) ($this->allPrices()[$key] ?? 0);
    }

    public function valueOf(AssetType|InvestmentAsset|string $asset, float $quantity): float
    {
        return $this->priceFor($asset) * $quantity;
    }

    public function priceAvailableFor(AssetType|InvestmentAsset|string $asset): bool
    {
        return $this->priceFor($asset) > 0;
    }

    public function pricesAvailable(): bool
    {
        return $this->tgjuPrices() !== [];
    }

    public function lastSyncedAt(): ?string
    {
        return Cache::get('asset-prices.tgju.synced_at');
    }

    public function invalidateCache(User $user): void
    {
        Cache::forget('asset-prices.tgju');

        InvestmentAsset::query()
            ->availableFor($user)
            ->whereIn('price_source_type', [
                InvestmentAssetPriceSource::Json,
                InvestmentAssetPriceSource::Xml,
            ])
            ->get()
            ->each(function (InvestmentAsset $asset): void {
                Cache::forget($this->remotePriceCacheKey($asset, $asset->price_source_config ?? []));
            });
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

        $prices = Cache::remember(
            'asset-prices.tgju',
            now()->addSeconds((int) config('services.tgju.cache_seconds', 300)),
            fn (): array => $this->fetchTgjuPrices(),
        );

        if ($prices !== []) {
            return $prices;
        }

        // The live fetch failed (or is still failing within its cache window) —
        // fall back to the last successfully fetched prices rather than zeroing
        // everything out.
        return Cache::get('asset-prices.tgju.last_known', []);
    }

    private function priceForInvestmentAsset(InvestmentAsset $asset): float
    {
        $config = $asset->price_source_config ?? [];
        $sourceType = $asset->price_source_type instanceof InvestmentAssetPriceSource
            ? $asset->price_source_type
            : InvestmentAssetPriceSource::tryFrom((string) $asset->price_source_type);

        try {
            return match ($sourceType) {
                InvestmentAssetPriceSource::Builtin => $this->priceForBuiltinAsset($asset, $config),
                InvestmentAssetPriceSource::Manual => $this->manualPrice($config),
                InvestmentAssetPriceSource::Formula => $this->formulaPrice($config),
                InvestmentAssetPriceSource::Json => $this->remotePrice($asset, $config, 'json'),
                InvestmentAssetPriceSource::Xml => $this->remotePrice($asset, $config, 'xml'),
                default => 0.0,
            };
        } catch (Throwable) {
            return 0.0;
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function priceForBuiltinAsset(InvestmentAsset $asset, array $config): float
    {
        $key = (string) ($config['key'] ?? $asset->slug);

        return (float) ($this->allPrices()[$key] ?? 0.0);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function manualPrice(array $config): float
    {
        return max(0.0, (float) ($config['price'] ?? 0));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function formulaPrice(array $config): float
    {
        $formula = trim((string) ($config['formula'] ?? ''));

        if ($formula === '') {
            return 0.0;
        }

        return max(0.0, $this->formulaEvaluator->evaluate($formula, $this->formulaVariables()));
    }

    /**
     * @return array<string, float>
     */
    private function formulaVariables(): array
    {
        $tgjuPrices = $this->tgjuPrices();
        $prices = $this->allPrices();
        $goldPrice = $tgjuPrices['gold_750'] ?? $prices['gold'] ?? 0.0;

        return [
            ...$tgjuPrices,
            ...$prices,
            'goldprice' => $goldPrice,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function remotePrice(InvestmentAsset $asset, array $config, string $format): float
    {
        return Cache::remember(
            $this->remotePriceCacheKey($asset, $config),
            now()->addSeconds((int) config('services.custom_asset_prices.cache_seconds', 300)),
            fn (): float => $this->fetchRemotePrice($config, $format),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function remotePriceCacheKey(InvestmentAsset $asset, array $config): string
    {
        return 'investment-asset-price.'.$asset->id.'.'.md5(json_encode($config) ?: '');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function fetchRemotePrice(array $config, string $format): float
    {
        $url = $this->safeUrl((string) ($config['url'] ?? ''));

        if ($url === null) {
            return 0.0;
        }

        $response = Http::timeout((int) config('services.custom_asset_prices.timeout', 5))
            ->connectTimeout((int) config('services.custom_asset_prices.connect_timeout', 3))
            ->withOptions(['allow_redirects' => false])
            ->get($url);

        if (! $response->successful()) {
            return 0.0;
        }

        $rawValue = $format === 'json'
            ? $this->extractJsonValue($response->body(), (string) ($config['path'] ?? ''))
            : $this->extractXmlValue($response->body(), (string) ($config['xpath'] ?? ''));

        $price = $this->parseNumber($rawValue);

        if ($price === null) {
            return 0.0;
        }

        $divideBy = max(1.0, (float) ($config['divide_by'] ?? 1));
        $price /= $divideBy;
        $formula = trim((string) ($config['formula'] ?? ''));

        if ($formula !== '') {
            $price = $this->formulaEvaluator->evaluate($formula, [
                ...$this->formulaVariables(),
                'value' => $price,
            ]);
        }

        return max(0.0, $price);
    }

    private function safeUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user'], $parts['pass'])) {
            return null;
        }

        if ($this->hostIsUnsafe($host)) {
            return null;
        }

        return $url;
    }

    private function hostIsUnsafe(string $host): bool
    {
        $trimmedHost = trim($host, '[]');

        if ($trimmedHost === 'localhost' || str_ends_with($trimmedHost, '.localhost')) {
            return true;
        }

        if (filter_var($trimmedHost, FILTER_VALIDATE_IP)) {
            return ! filter_var($trimmedHost, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        if (app()->environment('testing')) {
            return false;
        }

        $resolvedIps = gethostbynamel($trimmedHost);

        if ($resolvedIps === false || $resolvedIps === []) {
            return true;
        }

        foreach ($resolvedIps as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return true;
            }
        }

        return false;
    }

    private function extractJsonValue(string $body, string $path): string
    {
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            return '';
        }

        $value = $path === '' ? $decoded : data_get($decoded, $path);

        return is_scalar($value) ? (string) $value : '';
    }

    private function extractXmlValue(string $body, string $xpathQuery): string
    {
        if (trim($body) === '' || trim($xpathQuery) === '') {
            return '';
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($body);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return '';
        }

        $xpath = new DOMXPath($document);

        return trim((string) $xpath->evaluate("string({$xpathQuery})"));
    }

    /**
     * @return array<string, float>
     */
    private function fetchTgjuPrices(): array
    {
        $prices = [];

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

        if ($prices !== []) {
            Cache::forever('asset-prices.tgju.synced_at', now()->toIso8601String());
            Cache::forever('asset-prices.tgju.last_known', $prices);
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
