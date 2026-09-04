<?php

namespace App\Support;

use App\Models\MileWallet;
use Illuminate\Http\Request;

class AcquisitionSource
{
    public const SESSION_KEY = 'acquisition_source';

    public const REFERRAL_SESSION_KEY = 'miles_referral';

    private const MAX_LENGTH = 120;

    /**
     * Remember the first acquisition hint (UTM parameters or an external
     * referrer) a guest arrives with, so it can be attributed to the account
     * they may create later in the session.
     */
    public static function capture(Request $request): void
    {
        if ($request->user() !== null || ! $request->hasSession()) {
            return;
        }

        if (! $request->session()->has(self::REFERRAL_SESSION_KEY)) {
            $code = mb_strtoupper(trim((string) $request->query('ref')));

            if ($code !== '' && MileWallet::query()->where('referral_code', $code)->exists()) {
                $request->session()->put(self::REFERRAL_SESSION_KEY, [
                    'code' => $code,
                    'captured_at' => now()->toIso8601String(),
                    'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
                    'device_hash' => hash_hmac('sha256', (string) $request->userAgent(), (string) config('app.key')),
                ]);
            }
        }

        if (! $request->session()->has(self::SESSION_KEY)) {
            $source = self::fromRequest($request);

            if ($source !== null) {
                $request->session()->put(self::SESSION_KEY, $source);
            }
        }
    }

    /**
     * Consume the remembered acquisition source for a signup. Returns null
     * when no session is available (queue workers, Telegram webhooks) or when
     * the visitor arrived without any attributable source.
     */
    public static function pull(): ?string
    {
        $request = request();

        if (! $request->hasSession()) {
            return null;
        }

        $source = $request->session()->pull(self::SESSION_KEY);

        return is_string($source) && $source !== '' ? $source : null;
    }

    /** @return array{code: string, captured_at: string, ip_hash?: string, device_hash?: string}|null */
    public static function pullReferral(): ?array
    {
        $request = request();

        if (! $request->hasSession()) {
            return null;
        }

        $referral = $request->session()->pull(self::REFERRAL_SESSION_KEY);

        return is_array($referral)
            && isset($referral['code'], $referral['captured_at'])
            && is_string($referral['code'])
            && is_string($referral['captured_at']) ? $referral : null;
    }

    public static function fromRequest(Request $request): ?string
    {
        $utmSource = trim((string) $request->query('utm_source'));

        if ($utmSource !== '') {
            $utmMedium = trim((string) $request->query('utm_medium'));
            $source = $utmMedium !== '' ? "{$utmSource} / {$utmMedium}" : $utmSource;

            return mb_substr(mb_strtolower($source), 0, self::MAX_LENGTH);
        }

        $referrerHost = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);

        if (! is_string($referrerHost) || $referrerHost === '') {
            return null;
        }

        if (self::isInternalHost($referrerHost, $request)) {
            return null;
        }

        return mb_substr(mb_strtolower($referrerHost), 0, self::MAX_LENGTH);
    }

    private static function isInternalHost(string $referrerHost, Request $request): bool
    {
        if (strcasecmp($referrerHost, $request->getHost()) === 0) {
            return true;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($appHost) && strcasecmp($referrerHost, $appHost) === 0;
    }
}
