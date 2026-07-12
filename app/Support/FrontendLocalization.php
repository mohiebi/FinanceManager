<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class FrontendLocalization
{
    public const DEFAULT_LOCALE = 'en';

    public const DEFAULT_CALENDAR = 'gregorian';

    /** @return array<int, string> */
    public static function locales(): array
    {
        return ['en', 'fa', 'de'];
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

    public static function sessionLocale(Request $request): ?string
    {
        $locale = $request->session()->get('locale');

        return is_string($locale) && in_array($locale, self::locales(), true)
            ? $locale
            : null;
    }

    public static function persistSessionLocale(Request $request, User $user): void
    {
        $locale = self::sessionLocale($request);

        if ($locale === null || $user->locale === $locale) {
            return;
        }

        $user->forceFill(['locale' => $locale])->save();
    }

    /**
     * Authenticated users carry their locale on the profile; guests (e.g. the
     * public landing page) keep their choice in the session.
     */
    public static function resolve(Request $request): string
    {
        return self::normalizeLocale(
            $request->route('locale') ?? $request->user()?->locale ?? $request->session()->get('locale'),
        );
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
