<?php

namespace App\Support;

use App\Models\User;
use DateTimeZone;
use Illuminate\Http\Request;

class FrontendLocalization
{
    public const DEFAULT_LOCALE = 'en';

    public const DEFAULT_CALENDAR = 'gregorian';

    public const DEFAULT_TIMEZONE = 'UTC';

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

    /**
     * Also seeds the timezone, but only while it is still the untouched default.
     *
     * Signup never knows the locale — it is carried in the session from the
     * landing page and applied here — so this is the first moment there is
     * anything to infer a zone from. A user who has already picked one keeps it,
     * including the small number who genuinely mean UTC and later switch locale;
     * that is a preference they can correct, whereas leaving every Persian
     * account on UTC silently misplaces their day boundary.
     */
    public static function persistSessionLocale(Request $request, User $user): void
    {
        $locale = self::sessionLocale($request);

        if ($locale === null || $user->locale === $locale) {
            return;
        }

        $attributes = ['locale' => $locale];

        if (blank($user->timezone) || $user->timezone === self::DEFAULT_TIMEZONE) {
            $attributes['timezone'] = self::defaultTimezoneForLocale($locale);
        }

        $user->forceFill($attributes)->save();
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

    /**
     * Every IANA zone this build of PHP knows, for the preferences select and the
     * matching validation rule. Deliberately not a curated short list — a user in
     * a zone we forgot to enumerate would silently get the wrong day boundary.
     *
     * @return array<int, string>
     */
    public static function timezones(): array
    {
        return DateTimeZone::listIdentifiers();
    }

    /**
     * Falls back to UTC rather than the app default, because a junk value here
     * must not quietly shift a user's day boundary to somewhere plausible.
     */
    public static function normalizeTimezone(?string $timezone): string
    {
        return in_array($timezone, self::timezones(), true) ? $timezone : self::DEFAULT_TIMEZONE;
    }

    /**
     * The zone a brand-new account starts in, inferred from the locale it signed
     * up with. Only a starting point — the preferences page owns it from then on.
     */
    public static function defaultTimezoneForLocale(?string $locale): string
    {
        return match (self::normalizeLocale($locale)) {
            'fa' => 'Asia/Tehran',
            'de' => 'Europe/Berlin',
            default => self::DEFAULT_TIMEZONE,
        };
    }

    public static function direction(string $locale): string
    {
        return $locale === 'fa' ? 'rtl' : 'ltr';
    }

    /**
     * The locale written out for a language model, which needs a name rather
     * than an ISO code to reliably answer in the right language.
     *
     * Deliberately in English and deliberately not translated: it is read by a
     * model, not by the user, and the endonym ("فارسی") is the weaker cue.
     */
    public static function languageName(?string $locale): string
    {
        return match (self::normalizeLocale($locale)) {
            'fa' => 'Persian (Farsi)',
            'de' => 'German',
            default => 'English',
        };
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
