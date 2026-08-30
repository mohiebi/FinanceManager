<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
    config()->set('inertia.ssr.enabled', true);

    // What these tests are about is which routes this application chooses to
    // server render, and that decision is ours. Inertia's own prerequisite —
    // that a built bundle sits at bootstrap/ssr/ssr.js — is not, and leaving it
    // in play made both tests answer a question about the machine instead.
    //
    // The bundle is gitignored and only `npm run build:ssr` emits it, so a
    // developer who has run that sees the gateway dispatch while CI, which runs
    // `npm run build`, never does. That is what failed here: the public-shell
    // test looked for a render request that was skipped before it was ever
    // attempted. The authenticated test was worse — it passed for the same
    // reason, asserting nothing was sent in a run where nothing could be.
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
