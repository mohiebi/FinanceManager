<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Morilog\Jalali\Jalalian;
use Throwable;

/**
 * Calendar-aware date handling for the MCP tools. AI clients must never do
 * Jalali/Gregorian conversion themselves (LLMs get the arithmetic wrong), so
 * dates are detected and converted server-side: Jalali years (1100-1599)
 * never overlap real-world Gregorian finance dates, making detection
 * unambiguous regardless of the user's calendar preference.
 */
class CalendarDates
{
    private const JALALI_YEAR_MIN = 1100;

    private const JALALI_YEAR_MAX = 1599;

    /**
     * Converts a detected Jalali date string to Gregorian Y-m-d. Gregorian
     * input and unparseable values pass through unchanged so the regular
     * validation rules still apply to them.
     */
    public static function normalizeToGregorian(mixed $value): mixed
    {
        if (! is_string($value) || ! preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', trim($value), $matches)) {
            return $value;
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];

        if ($year < self::JALALI_YEAR_MIN || $year > self::JALALI_YEAR_MAX) {
            return $value;
        }

        try {
            return (new Jalalian($year, $month, $day))->toCarbon()->format('Y-m-d');
        } catch (Throwable) {
            return $value;
        }
    }

    public static function toJalali(mixed $date): ?string
    {
        if (! $date) {
            return null;
        }

        return Jalalian::fromCarbon(Carbon::parse($date))->format('Y-m-d');
    }

    public static function isJalaliUser(User $user): bool
    {
        return FrontendLocalization::normalizeCalendar($user->calendar) === 'jalali';
    }

    /**
     * The Jalali month (as 'Y-m') a Gregorian date falls in, for grouping
     * monthly summaries by the user's calendar.
     */
    public static function jalaliMonthKey(Carbon|CarbonInterface $date): string
    {
        return Jalalian::fromCarbon(Carbon::instance($date))->format('Y-m');
    }

    /**
     * @return array{from: string, to: string, from_gregorian: string, to_gregorian: string}
     */
    public static function currentJalaliMonthRange(): array
    {
        $today = Jalalian::fromCarbon(Carbon::today());
        $daysInMonth = (int) $today->format('t');

        $from = new Jalalian($today->getYear(), $today->getMonth(), 1);
        $to = new Jalalian($today->getYear(), $today->getMonth(), $daysInMonth);

        return [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'from_gregorian' => $from->toCarbon()->format('Y-m-d'),
            'to_gregorian' => $to->toCarbon()->format('Y-m-d'),
        ];
    }
}
