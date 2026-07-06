<?php

test('inertia ssr is enabled by default for production rendering', function () {
    expect(config('inertia.ssr.enabled'))->toBeTrue()
        ->and(config('inertia.ssr.url'))->toBe('http://127.0.0.1:13714')
        ->and(config('inertia.ssr.bundle'))->toBe(base_path('bootstrap/ssr/ssr.js'));
});
