<?php

use App\Support\UserAgentSummary;

test('recognises the major desktop and mobile browsers', function (string $userAgent, string $browser) {
    expect(UserAgentSummary::parse($userAgent)['browser'])->toBe($browser);
})->with([
    'Chrome on Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', 'Chrome'],
    'Edge on Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0', 'Edge'],
    'Firefox on Linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0', 'Firefox'],
    'Safari on macOS' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15', 'Safari'],
    'Chrome on iOS' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/119.0.6045.109 Mobile/15E148 Safari/604.1', 'Chrome'],
    'Opera on Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 OPR/106.0.0.0', 'Opera'],
]);

test('recognises the major platforms', function (string $userAgent, string $platform) {
    expect(UserAgentSummary::parse($userAgent)['platform'])->toBe($platform);
})->with([
    'Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', 'Windows'],
    'macOS' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15', 'Mac'],
    'iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1', 'iPhone'],
    'Android' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36', 'Android'],
]);

test('an empty or missing user agent yields no browser or platform', function (?string $userAgent) {
    expect(UserAgentSummary::parse($userAgent))->toBe(['browser' => null, 'platform' => null]);
})->with([null, '', '   ']);

test('an unrecognised user agent yields null rather than a guess', function () {
    expect(UserAgentSummary::parse('SomeCustomBot/1.0'))
        ->toBe(['browser' => null, 'platform' => null]);
});
