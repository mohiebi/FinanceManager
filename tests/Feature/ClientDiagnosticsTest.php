<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Support\Facades\Log;

beforeEach(fn () => $this->withoutVite());

test('a client report reaches the log at a level the container will show', function () {
    // The container runs at LOG_LEVEL=error, so anything quieter would be
    // filtered out and the instrumentation would record nothing.
    Log::shouldReceive('error')
        ->once()
        ->withArgs(fn (string $message): bool => str_contains($message, '[client]')
            && str_contains($message, 'kind=hydration'));

    $this->actingAs(User::factory()->create())
        ->postJson(route('diagnostics.client'), [
            'kind' => 'hydration',
            'component' => 'Dashboard',
            'message' => 'Hydration node mismatch',
        ])
        ->assertNoContent();
});

test('a boot report says whether the server sent SSR markup', function () {
    Log::shouldReceive('error')
        ->once()
        ->withArgs(fn (string $message): bool => str_contains($message, 'ssr=NO'));

    $this->postJson(route('diagnostics.client'), [
        'kind' => 'boot',
        'component' => 'Dashboard',
        'ssrMarkup' => false,
        'ssrChildren' => 0,
    ])->assertNoContent();
});

test('the endpoint disappears when the flag is off', function () {
    config()->set('diagnostics.client_enabled', false);

    $this->postJson(route('diagnostics.client'), ['kind' => 'boot'])
        ->assertNotFound();
});

test('a malformed report is rejected rather than logged', function () {
    $this->postJson(route('diagnostics.client'), ['component' => 'Dashboard'])
        ->assertStatus(422);
});

test('the document render log records the SSR fallback', function () {
    // Without an SSR server the root element comes back empty — which is
    // exactly the fallback this line exists to make visible.
    Log::shouldReceive('error')
        ->atLeast()
        ->once()
        ->withArgs(fn (string $message): bool => str_contains($message, '[document]')
            && str_contains($message, 'component=Dashboard')
            && str_contains($message, 'ssr=NO')
            && str_contains($message, 'authed=yes'));

    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk();
});

test('an inertia xhr visit is not logged as a document render', function () {
    // It returns JSON and never runs SSR, so it would only add noise. The
    // version header has to be the real one, or Inertia answers 409 instead of
    // performing the visit.
    Log::shouldReceive('error')->never();

    $version = app(HandleInertiaRequests::class)->version(request());

    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $version,
    ]);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/json');
});
