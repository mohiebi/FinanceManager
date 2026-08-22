<?php

use App\Models\User;

beforeEach(fn () => $this->withoutVite());

/*
 * The root template carries the server-rendered markup, the hashed asset URLs
 * and the Inertia asset version — and that version is a hash of the Vite
 * manifest, so it changes on every deploy. A document reused across one
 * hydrates the new bundle against the previous build's markup.
 */
test('the inertia document may not be stored by a browser or proxy', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    $response->assertOk();

    expect($response->headers->get('Cache-Control'))
        ->toContain('no-store')
        ->and($response->headers->get('Cache-Control'))->toContain('must-revalidate');
});

test('the guest-facing shell is covered too', function () {
    // It carries the same asset version, so a stale copy breaks a login the
    // same way it breaks an authenticated page.
    expect($this->get('/login')->headers->get('Cache-Control'))->toContain('no-store');
});

test('inertia keeps varying on its own header', function () {
    // Without this a proxy can hand an XHR the HTML document, or a browser
    // navigation the JSON payload.
    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    expect($response->headers->get('Vary'))->toContain('X-Inertia');
});

test('a redirect is left alone', function () {
    // Nothing to hydrate and no asset version, so there is nothing to protect —
    // and rewriting headers on a redirect risks breaking the flash-session
    // handshake that follows it.
    $response = $this->post(route('logout'));

    $response->assertRedirect();
    expect((string) $response->headers->get('Cache-Control'))->not->toContain('no-store');
});
