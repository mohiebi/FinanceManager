<?php

use App\Actions\Features\UpdateUserFeature;
use App\Enums\Feature;
use App\Models\User;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

test('Passport keys are loaded from persistent storage', function () {
    $normalize = fn (string $path): string => str_replace('\\', '/', $path);

    expect($normalize(Passport::keyPath('oauth-private.key')))
        ->toBe($normalize(storage_path('passport/oauth-private.key')))
        ->and($normalize(Passport::keyPath('oauth-public.key')))
        ->toBe($normalize(storage_path('passport/oauth-public.key')));
});

test('oauth authorization server metadata advertises the mcp scope and PKCE', function () {
    $response = $this->getJson('/.well-known/oauth-authorization-server');

    $response->assertOk()
        ->assertJsonPath('code_challenge_methods_supported', ['S256'])
        ->assertJsonFragment(['scopes_supported' => ['mcp:use']]);

    expect($response->json('registration_endpoint'))->toContain('/oauth/register')
        ->and($response->json('token_endpoint'))->toContain('/oauth/token');
});

test('oauth protected resource metadata is discoverable', function () {
    $this->getJson('/.well-known/oauth-protected-resource')
        ->assertOk()
        ->assertJsonFragment(['scopes_supported' => ['mcp:use']]);
});

test('dynamic client registration creates a public authorization-code client', function () {
    $response = $this->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    ]);

    $response->assertOk()
        ->assertJsonPath('token_endpoint_auth_method', 'none')
        ->assertJsonPath('scope', 'mcp:use')
        ->assertJsonStructure(['client_id', 'redirect_uris', 'grant_types']);

    expect(Client::query()->count())->toBe(1)
        ->and(Client::query()->first()->confidential())->toBeFalse();
});

test('the mcp endpoint rejects unauthenticated requests with discovery headers', function () {
    $response = $this->postJson('/mcp/finance', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    $response->assertUnauthorized();

    expect($response->headers->get('WWW-Authenticate'))
        ->toContain('Bearer')
        ->toContain('resource_metadata');
});

test('an authenticated ai client can list the finance tools', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::AiAssistant, true);
    Passport::actingAs($user, ['mcp:use']);

    $response = $this->postJson('/mcp/finance', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    $response->assertOk();

    $names = collect($response->json('result.tools'))->pluck('name');

    expect($names)->toContain('list-transactions-tool')
        ->toContain('propose-transaction-tool')
        ->toContain('confirm-proposal-tool');
});

test('users with the ai assistant module switched off cannot use the mcp endpoint', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, ['mcp:use']);

    $this->postJson('/mcp/finance', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])
        ->assertForbidden()
        ->assertJsonPath('error_description', 'Turn on the AI Assistant module before connecting AI assistants.');
});

test('unverified users cannot use the mcp endpoint', function () {
    $user = User::factory()->unverified()->create();
    Passport::actingAs($user, ['mcp:use']);

    $this->postJson('/mcp/finance', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertForbidden();
});

test('users with incomplete profiles cannot use the mcp endpoint', function () {
    $user = User::factory()->create(['birthdate' => null]);
    Passport::actingAs($user, ['mcp:use']);

    $this->postJson('/mcp/finance', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertForbidden();
});
