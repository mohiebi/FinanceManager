<?php

namespace App\Support;

/**
 * A deliberately small user-agent reader: enough to label a sign-in session
 * "Chrome on Windows", not a full device-fingerprinting library. No
 * third-party UA parser is a dependency of this app, and a browser-sessions
 * list needs a human-recognisable label, not exhaustive device detection.
 */
class UserAgentSummary
{
    /**
     * @return array{browser: string|null, platform: string|null}
     */
    public static function parse(?string $userAgent): array
    {
        $userAgent = trim((string) $userAgent);

        if ($userAgent === '') {
            return ['browser' => null, 'platform' => null];
        }

        return [
            'browser' => self::browser($userAgent),
            'platform' => self::platform($userAgent),
        ];
    }

    private static function browser(string $userAgent): ?string
    {
        return match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/'), str_contains($userAgent, 'Opera') => 'Opera',
            str_contains($userAgent, 'Firefox/'), str_contains($userAgent, 'FxiOS/') => 'Firefox',
            str_contains($userAgent, 'CriOS/'), str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => null,
        };
    }

    private static function platform(string $userAgent): ?string
    {
        return match (true) {
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Macintosh'), str_contains($userAgent, 'Mac OS X') => 'Mac',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'CrOS') => 'Chrome OS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };
    }
}
