<?php

namespace App\Services\Billing;

use App\Enums\SettlementAsset;
use App\Exceptions\QuoteUnavailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Converts a plan's USD price into an amount of the asset the buyer chose.
 *
 * Stablecoins never reach the network: one unit is one dollar by definition, so
 * asking anybody is both pointless and a needless dependency on a service being
 * up. Only a volatile asset is quoted, and only its rate is cached.
 */
final readonly class AssetQuoteService
{
    /**
     * USD per one unit of the asset, as a decimal string.
     *
     * @throws QuoteUnavailable
     */
    public function usdRate(SettlementAsset $asset): string
    {
        if ($asset->isStable()) {
            return '1.00000000';
        }

        $rate = Cache::remember(
            "billing.quote.{$asset->value}",
            (int) config('billing.quote.cache_seconds', 300),
            fn (): ?float => $this->fetchUsdRate($asset),
        );

        // A null that was cached would pin the failure for the whole TTL, so it
        // is evicted rather than served.
        if ($rate === null || $rate <= 0) {
            Cache::forget("billing.quote.{$asset->value}");

            throw new QuoteUnavailable("No USD rate available for {$asset->symbol()}.");
        }

        return number_format($rate, 8, '.', '');
    }

    /**
     * The plan price expressed in the asset, quantized so the nonce has room.
     *
     * The division happens in floating point, which is safe precisely because
     * its result is then frozen: the figure is quantized here, stored on the
     * payment, and every later comparison is exact string arithmetic against
     * that stored value. Nothing recomputes it.
     *
     * @throws QuoteUnavailable
     */
    public function priceIn(SettlementAsset $asset, string $priceUsd, string $usdRate): string
    {
        $price = (float) $priceUsd;
        $rate = (float) $usdRate;

        if ($price <= 0 || $rate <= 0) {
            throw new QuoteUnavailable("Cannot price a plan at {$priceUsd} USD against a rate of {$usdRate}.");
        }

        $amount = $price / $rate;

        if (! is_finite($amount) || $amount <= 0) {
            throw new QuoteUnavailable("Pricing {$priceUsd} USD in {$asset->symbol()} produced no usable amount.");
        }

        return number_format($amount, $asset->quotePrecision(), '.', '');
    }

    private function fetchUsdRate(SettlementAsset $asset): ?float
    {
        if (! config('billing.quote.enabled', true)) {
            return null;
        }

        $url = (string) config('billing.quote.url', '');

        if ($url === '') {
            return null;
        }

        try {
            $response = Http::timeout((int) config('billing.quote.timeout', 6))
                ->connectTimeout((int) config('billing.quote.connect_timeout', 3))
                ->withOptions(['allow_redirects' => false])
                ->get($url, [
                    'ids' => $this->quoteIdFor($asset),
                    'vs_currencies' => 'usd',
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $rate = $response->json($this->quoteIdFor($asset).'.usd');

        return is_numeric($rate) ? (float) $rate : null;
    }

    /**
     * The rate provider's own name for the asset.
     *
     * Provider-specific, so it lives here rather than on the enum — swapping the
     * quote source should not mean editing an enum that knows nothing about it.
     */
    private function quoteIdFor(SettlementAsset $asset): string
    {
        return match ($asset) {
            SettlementAsset::Eth => 'ethereum',
            SettlementAsset::Usdt => 'tether',
            SettlementAsset::Usdc => 'usd-coin',
        };
    }
}
