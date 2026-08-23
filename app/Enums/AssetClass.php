<?php

namespace App\Enums;

use App\Models\InvestmentAsset;

/**
 * The broad family an investment asset belongs to.
 *
 * The coarser half of a two-part taxonomy. This one answers "how much of the
 * portfolio sits in metals rather than currency"; the finer half — which metal —
 * is {@see InvestmentAsset::$underlying_asset_id}, because a closed
 * enum can never name an asset a user has just invented.
 *
 * The vocabulary is deliberately the same list the investor assessment already
 * asks for, so a holding and the target allocation written against it are
 * filed under the same word.
 */
enum AssetClass: string
{
    case Stock = 'stock';
    case Etf = 'etf';
    case Bond = 'bond';
    case Currency = 'currency';
    case Metal = 'metal';
    case Crypto = 'crypto';
    case Commodity = 'commodity';
    case RealEstate = 'real_estate';
    case PrivateAsset = 'private_asset';
    case Other = 'other';

    public function label(): string
    {
        $translationKey = "finance.asset_classes.{$this->value}";
        $translatedLabel = __($translationKey);

        if ($translatedLabel !== $translationKey) {
            return $translatedLabel;
        }

        return match ($this) {
            self::Stock => 'Stock',
            self::Etf => 'ETF',
            self::Bond => 'Bond',
            self::Currency => 'Currency',
            self::Metal => 'Precious metal',
            self::Crypto => 'Crypto',
            self::Commodity => 'Commodity',
            self::RealEstate => 'Real estate',
            self::PrivateAsset => 'Private asset',
            self::Other => 'Other',
        };
    }

    /**
     * The values, for validation rules and JSON schemas.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * A first guess from the unit a user typed, used to preselect the field.
     *
     * Only ever a default in a form the user can still change — nothing decides
     * anything on the strength of this.
     */
    public static function guessFromUnit(?string $unit): self
    {
        return match (mb_strtolower(trim((string) $unit))) {
            'g', 'gr', 'gram', 'grams', 'oz', 'ounce', 'kg', 'coin', 'coins', 'bar', 'bars', 'مثقال', 'گرم', 'سکه', 'شمش' => self::Metal,
            'usd', 'eur', 'gbp', 'aed', 'try', 'toman', 'rial' => self::Currency,
            'btc', 'eth', 'usdt', 'sol' => self::Crypto,
            'share', 'shares', 'stock', 'stocks', 'سهم' => self::Stock,
            default => self::Other,
        };
    }
}
