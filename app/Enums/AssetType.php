<?php

namespace App\Enums;

enum AssetType: string
{
    case Gold    = 'gold';
    case Silver  = 'silver';
    case Usd     = 'usd';
    case Eur     = 'eur';
    case Coin    = 'coin';    // Bahar Azadi
    case Bitcoin = 'bitcoin';

    public function label(): string
    {
        return match ($this) {
            self::Gold    => 'Gold',
            self::Silver  => 'Silver',
            self::Usd     => 'US Dollar',
            self::Eur     => 'Euro',
            self::Coin    => 'Bahar Azadi',
            self::Bitcoin => 'Bitcoin',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::Gold, self::Silver => 'g',
            self::Usd                => 'USD',
            self::Eur                => 'EUR',
            self::Coin               => 'coin',
            self::Bitcoin            => 'BTC',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Gold    => '🥇',
            self::Silver  => '🥈',
            self::Usd     => '💵',
            self::Eur     => '💶',
            self::Coin    => '🪙',
            self::Bitcoin => '₿',
        };
    }

    /** Hex color used in charts. */
    public function color(): string
    {
        return match ($this) {
            self::Gold    => '#F59E0B',
            self::Silver  => '#6B7280',
            self::Usd     => '#10B981',
            self::Eur     => '#3B82F6',
            self::Coin    => '#EF4444',
            self::Bitcoin => '#F97316',
        };
    }
}
