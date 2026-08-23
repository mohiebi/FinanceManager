<?php

namespace App\Enums;

enum ScreeningRisk: string
{
    case NoMatch = 'no_match';
    case Unknown = 'unknown';
    case Flagged = 'flagged';
    case Sanctioned = 'sanctioned';

    public function isDefinitive(): bool
    {
        return $this !== self::Unknown;
    }

    public function quarantinesFunds(): bool
    {
        return match ($this) {
            self::Sanctioned => true,
            self::NoMatch, self::Unknown, self::Flagged => false,
        };
    }
}
