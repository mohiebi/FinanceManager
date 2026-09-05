<?php

namespace App\Support;

use App\Models\MileWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class AcquisitionSource
{
    public const SESSION_KEY = 'acquisition_source';

    public const REFERRAL_SESSION_KEY = 'miles_referral';

    /**
     * A referral is promised thirty days; SESSION_LIFETIME is two hours, so the
     * session alone loses the touch. Encrypted like every other cookie here.
     */
    public const REFERRAL_COOKIE = 'miles_referral';

    public const REFERRAL_DAYS = 30;

    public const DEVICE_COOKIE = 'cashpilot_device';

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

        self::rememberReferral($request, (string) $request->query('ref'));

        if (! $request->session()->has(self::SESSION_KEY)) {
            $source = self::fromRequest($request);

            if ($source !== null) {
                $request->session()->put(self::SESSION_KEY, $source);
            }
        }
    }

    /**
     * Hold on to a referral code until the visitor creates an account.
     *
     * Takes the code rather than reading it: the same touch arrives as `?ref=`
     * and as an /invite path, and a second link must not overwrite the first.
     */
    public static function rememberReferral(Request $request, string $code): void
    {
        if ($request->user() !== null
            || ! $request->hasSession()
            || $request->session()->has(self::REFERRAL_SESSION_KEY)
            || self::touchFromCookie($request) !== null) {
            return;
        }

        $code = mb_strtoupper(trim($code));

        if ($code === '' || ! MileWallet::query()->where('referral_code', $code)->exists()) {
            return;
        }

        $touch = [
            'code' => $code,
            'captured_at' => now()->toIso8601String(),
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
            'device_hash' => hash_hmac('sha256', self::deviceToken($request), (string) config('app.key')),
        ];

        $request->session()->put(self::REFERRAL_SESSION_KEY, $touch);
        Cookie::queue(Cookie::make(
            self::REFERRAL_COOKIE,
            (string) json_encode($touch),
            self::REFERRAL_DAYS * 24 * 60,
        ));
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
        $referral = $request->hasSession()
            ? $request->session()->pull(self::REFERRAL_SESSION_KEY)
            : null;

        // Session first; the cookie is what survives until they sign up.
        $referral = self::validTouch($referral) ?? self::touchFromCookie($request);

        if ($referral !== null) {
            Cookie::queue(Cookie::forget(self::REFERRAL_COOKIE));
        }

        return $referral;
    }

    /** @return array{code: string, captured_at: string, ip_hash?: string, device_hash?: string}|null */
    private static function touchFromCookie(Request $request): ?array
    {
        $raw = $request->cookie(self::REFERRAL_COOKIE);

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return self::validTouch(json_decode($raw, true));
    }

    /** @return array{code: string, captured_at: string, ip_hash?: string, device_hash?: string}|null */
    private static function validTouch(mixed $referral): ?array
    {
        return is_array($referral)
            && isset($referral['code'], $referral['captured_at'])
            && is_string($referral['code'])
            && is_string($referral['captured_at']) ? $referral : null;
    }

    private static function deviceToken(Request $request): string
    {
        $token = $request->cookie(self::DEVICE_COOKIE);

        if (is_string($token) && strlen($token) >= 32) {
            return $token;
        }

        $token = Str::random(64);
        Cookie::queue(Cookie::forever(self::DEVICE_COOKIE, $token));

        return $token;
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
