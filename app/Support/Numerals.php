<?php

namespace App\Support;

/**
 * Numbers as a Persian or Arabic keyboard actually types them.
 *
 * A Farsi keyboard produces ۱۲۳ and ٬ ٫ for the separators, none of which PHP
 * reads as a number - so without this a user has to switch keyboard layouts to
 * enter an amount.
 */
final class Numerals
{
    /** Eastern digits to ASCII, leaving everything else alone. */
    private const DIGITS = [
        "\u{06F0}" => '0', "\u{06F1}" => '1', "\u{06F2}" => '2', "\u{06F3}" => '3', "\u{06F4}" => '4',
        "\u{06F5}" => '5', "\u{06F6}" => '6', "\u{06F7}" => '7', "\u{06F8}" => '8', "\u{06F9}" => '9',
        "\u{0660}" => '0', "\u{0661}" => '1', "\u{0662}" => '2', "\u{0663}" => '3', "\u{0664}" => '4',
        "\u{0665}" => '5', "\u{0666}" => '6', "\u{0667}" => '7', "\u{0668}" => '8', "\u{0669}" => '9',
    ];

    /**
     * Separators a number may carry, mapped to what PHP understands.
     *
     * Grouping marks are dropped rather than replaced: the app prints amounts
     * as 2,100,000, so it should read one back.
     */
    private const SEPARATORS = [
        "\u{066B}" => '.',  // Arabic decimal separator
        "\u{066C}" => '',   // Arabic thousands separator
        "\u{060C}" => '',   // Arabic comma
        "\u{200C}" => '',   // zero-width non-joiner
        "\u{00A0}" => '',   // non-breaking space
        ',' => '',
        '_' => '',
        ' ' => '',
    ];

    /** Persian and Arabic-Indic digits rewritten as ASCII. */
    public static function toLatin(string $value): string
    {
        return strtr($value, self::DIGITS);
    }

    /**
     * A number typed in any of those digit sets, or null when it is not one.
     *
     * Deliberately strict about what is left after the separators go: an amount
     * the user fat-fingered should be refused, not silently read as the digits
     * that happened to be in it.
     */
    public static function parse(string $value): ?float
    {
        $normalized = strtr(self::toLatin(trim($value)), self::SEPARATORS);

        // A lone leading sign or dot is not a number, and is_numeric agrees.
        return is_numeric($normalized) ? (float) $normalized : null;
    }
}
