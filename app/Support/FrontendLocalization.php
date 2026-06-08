<?php

namespace App\Support;

class FrontendLocalization
{
    public const DEFAULT_LOCALE = 'en';

    public const DEFAULT_CALENDAR = 'gregorian';

    /** @return array<int, string> */
    public static function locales(): array
    {
        return ['en', 'fa'];
    }

    /** @return array<int, string> */
    public static function calendars(): array
    {
        return ['gregorian', 'jalali'];
    }

    public static function normalizeLocale(?string $locale): string
    {
        return in_array($locale, self::locales(), true) ? $locale : self::DEFAULT_LOCALE;
    }

    public static function normalizeCalendar(?string $calendar): string
    {
        return in_array($calendar, self::calendars(), true) ? $calendar : self::DEFAULT_CALENDAR;
    }

    public static function direction(string $locale): string
    {
        return $locale === 'fa' ? 'rtl' : 'ltr';
    }

    /**
     * @return array<string, mixed>
     */
    public static function messages(string $locale): array
    {
        $locale = self::normalizeLocale($locale);
        $messages = [];

        foreach (glob(resource_path("lang/{$locale}/*.php")) ?: [] as $path) {
            $messages[basename($path, '.php')] = require $path;
        }

        return $messages;
    }
}
