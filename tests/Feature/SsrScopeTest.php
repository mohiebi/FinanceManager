<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
    config()->set('inertia.ssr.enabled', true);
    // The gateway POSTs the page to the SSR worker; faking it lets the test
    // assert whether a server render was even attempted.
    Http::fake(['*' => Http::response(['head' => [], 'body' => '<div id="app"></div>'])]);
});

/**
 * A signed-in page is never crawled, so SSR buys it nothing while still
 * requiring the server's markup to match the browser's render node for node.
 * Dashboard did not match, and a mismatch is how a page renders but stops
 * responding.
 */
test('an authenticated page is not server rendered', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk();

    Http::assertNothingSent();
});

test('the public shell keeps server rendering', function () {
    // Landing and the auth screens are where the SEO and first-paint value is,
    // and they carry no per-account state for the two renders to disagree about.
    $this->get('/login')->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/render'));
});
