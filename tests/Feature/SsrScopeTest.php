<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
    config()->set('inertia.ssr.enabled', true);
    // What these tests read is whether the gateway *attempted* a render, so the
    // one other thing that stops it short must not be left to chance. The bundle
    // at bootstrap/ssr/ssr.js is a gitignored build artifact that only
    // `npm run build:ssr` emits, and CI runs `npm run build`, so on a fresh
    // checkout the gateway returns before dispatching: the guest test fails, and
    // the authenticated one passes for a reason that has nothing to do with the
    // rule it is meant to be pinning.
    //
    // Which routes this application server renders is our decision; Inertia's
    // bundle prerequisite is not, and leaving it in play made both tests answer
    // a question about the machine instead.
    config()->set('inertia.ssr.ensure_bundle_exists', false);

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
