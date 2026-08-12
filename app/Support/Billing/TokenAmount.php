<?php

namespace App\Support\Billing;

use InvalidArgumentException;

/**
 * Arbitrary-precision arithmetic on non-negative token amounts.
 *
 * On-chain values do not fit PHP integers. One ether is 10^18 wei against a
 * PHP_INT_MAX of about 9.2 * 10^18, so a cast overflows at roughly nine ether —
 * and a uint256 from a hostile contract runs to 78 digits. Every amount here is
 * therefore a decimal string from end to end, and no value ever passes through
 * int or float.
 *
 * Implemented with string arithmetic rather than bcmath or gmp on purpose:
 * neither extension is declared in composer.json, and money comparisons must
 * not depend on how a particular box happens to be built.
 *
 * "Base units" means the chain's smallest indivisible amount — wei for ether,
 * or the token's own smallest unit. "Decimal" means the human-facing figure.
 */
final class TokenAmount
{
    /**
     * Convert a uint256 hex value, as returned by JSON-RPC, into base units.
     */
    public static function fromHex(string $hex): string
    {
        $digits = mb_strtolower(trim($hex));

        if (str_starts_with($digits, '0x')) {
            $digits = mb_substr($digits, 2);
        }

        if ($digits === '' || ! ctype_xdigit($digits)) {
            throw new InvalidArgumentException("Not a hexadecimal quantity: [{$hex}].");
        }

        $decimal = '0';

        foreach (mb_str_split($digits) as $character) {
            $decimal = self::add(self::multiplyBySmall($decimal, 16), (string) hexdec($character));
        }

        return self::normalize($decimal);
    }

    /**
     * Convert a human-facing amount into base units at the asset's precision.
     *
     * Excess precision is tolerated only while the surplus digits are zeros,
     * which is what a decimal(36,18) column returns for a six-decimal token.
     * Anything that would actually lose value is refused rather than truncated:
     * silently rounding somebody's payment is how an amount check starts
     * disagreeing with the chain.
     */
    public static function fromDecimal(string $amount, int $decimals): string
    {
        if ($decimals < 0) {
            throw new InvalidArgumentException('Decimals cannot be negative.');
        }

        if (preg_match('/^(\d+)(?:\.(\d*))?$/', trim($amount), $matches) !== 1) {
            throw new InvalidArgumentException("Not a non-negative decimal amount: [{$amount}].");
        }

        $whole = $matches[1];
        $fraction = $matches[2] ?? '';

        if (mb_strlen($fraction) > $decimals) {
            $surplus = mb_substr($fraction, $decimals);

            if (rtrim($surplus, '0') !== '') {
                throw new InvalidArgumentException(
                    "[{$amount}] carries more precision than {$decimals} decimals can represent."
                );
            }

            $fraction = mb_substr($fraction, 0, $decimals);
        }

        return self::normalize($whole.str_pad($fraction, $decimals, '0'));
    }

    /**
     * Render base units as a human-facing amount, without rounding.
     */
    public static function toDecimal(string $baseUnits, int $decimals): string
    {
        $units = self::normalize($baseUnits);

        if ($decimals === 0) {
            return $units;
        }

        $padded = str_pad($units, $decimals + 1, '0', STR_PAD_LEFT);

        return mb_substr($padded, 0, -$decimals).'.'.mb_substr($padded, -$decimals);
    }

    /**
     * Compare two base-unit amounts: -1, 0 or 1.
     */
    public static function compare(string $a, string $b): int
    {
        $left = self::normalize($a);
        $right = self::normalize($b);

        // Normalized, so the longer string is unambiguously the larger number
        // and equal lengths compare correctly character by character.
        if (mb_strlen($left) !== mb_strlen($right)) {
            return mb_strlen($left) <=> mb_strlen($right);
        }

        return strcmp($left, $right) <=> 0;
    }

    /**
     * Add two base-unit amounts.
     */
    public static function add(string $a, string $b): string
    {
        $left = self::normalize($a);
        $right = self::normalize($b);

        $result = '';
        $carry = 0;
        $i = mb_strlen($left) - 1;
        $j = mb_strlen($right) - 1;

        while ($i >= 0 || $j >= 0 || $carry > 0) {
            $sum = $carry;

            if ($i >= 0) {
                $sum += (int) $left[$i--];
            }

            if ($j >= 0) {
                $sum += (int) $right[$j--];
            }

            $result = ($sum % 10).$result;
            $carry = intdiv($sum, 10);
        }

        return self::normalize($result);
    }

    /**
     * Whether an amount lands inside [floor, floor + width).
     *
     * The shape every payment check uses: at least what was asked for, and not
     * so much more that it must have been meant for a different intent.
     */
    public static function isWithinBand(string $amount, string $floor, string $width): bool
    {
        return self::compare($amount, $floor) >= 0
            && self::compare($amount, self::add($floor, $width)) < 0;
    }

    /**
     * Multiply a decimal string by a small integer factor.
     *
     * Small meaning the per-digit product stays inside a PHP integer, which is
     * the only reason this can use int arithmetic at all.
     */
    private static function multiplyBySmall(string $decimal, int $factor): string
    {
        if ($factor < 0 || $factor > 1000) {
            throw new InvalidArgumentException('Factor must be a small non-negative integer.');
        }

        $digits = self::normalize($decimal);
        $result = '';
        $carry = 0;

        for ($i = mb_strlen($digits) - 1; $i >= 0; $i--) {
            $product = ((int) $digits[$i]) * $factor + $carry;
            $result = ($product % 10).$result;
            $carry = intdiv($product, 10);
        }

        while ($carry > 0) {
            $result = ($carry % 10).$result;
            $carry = intdiv($carry, 10);
        }

        return self::normalize($result);
    }

    /**
     * Reduce to a canonical digit string with no leading zeros.
     */
    private static function normalize(string $value): string
    {
        $digits = trim($value);

        if ($digits === '' || preg_match('/^\d+$/', $digits) !== 1) {
            throw new InvalidArgumentException("Not a non-negative integer amount: [{$value}].");
        }

        $stripped = ltrim($digits, '0');

        return $stripped === '' ? '0' : $stripped;
    }
}
