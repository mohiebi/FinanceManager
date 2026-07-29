<?php

namespace App\Enums;

/**
 * Whether an investment row adds to a holding or takes away from it.
 *
 * Stored in plaintext on purpose — see the migration that adds the column. With
 * the vault armed the quantity is ciphertext, so the sign is invisible to the
 * server and this is the only thing that can tell the two apart.
 */
enum InvestmentKind: string
{
    case Buy = 'buy';
    case Sell = 'sell';

    public function isSell(): bool
    {
        return $this === self::Sell;
    }

    /** Sales are stored with a negative quantity so holdings are a plain sum. */
    public function signFor(float $quantity): float
    {
        return $this->isSell() ? -abs($quantity) : abs($quantity);
    }
}
