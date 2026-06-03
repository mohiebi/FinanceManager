<?php

namespace App\Services;

use App\Enums\AssetType;

/**
 * Provides static placeholder prices in Toman.
 *
 * NOTE: These are hard-coded constants for now.
 * Replace this service with real-time API calls (e.g. Nobitex, CoinGecko, TGJU)
 * in a future iteration — the public interface will stay the same.
 */
class AssetPriceService
{
    /**
     * Prices in Toman per unit.
     *
     * Gold/Silver: per gram
     * USD/EUR: per 1 unit of foreign currency
     * Coin: per Bahar Azadi coin
     * Bitcoin: per 1 BTC
     */
    private const PRICES_IN_TOMAN = [
        'gold'    => 12_000_000,        // ~$80/g × ~150,000 T/$
        'silver'  => 140_000,           // ~$0.93/g × ~150,000 T/$
        'usd'     => 91_500,
        'eur'     => 101_000,
        'coin'    => 98_000_000,        // Bahar Azadi (full coin)
        'bitcoin' => 14_500_000_000,    // ~$95,000 × ~150,000 T/$
    ];

    public function priceFor(AssetType $type): float
    {
        return (float) (self::PRICES_IN_TOMAN[$type->value] ?? 0);
    }

    public function valueOf(AssetType $type, float $quantity): float
    {
        return $this->priceFor($type) * $quantity;
    }

    /** @return array<string, int> */
    public function allPrices(): array
    {
        return self::PRICES_IN_TOMAN;
    }
}
