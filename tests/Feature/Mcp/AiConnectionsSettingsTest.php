<?php

use App\Actions\Features\UpdateUserFeature;
use App\Enums\Feature;
use App\Models\McpProposal;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

/**
 * @param  array<int, string>  $scopes
 */
function createAccessToken(User $user, Client $client, array $scopes = ['mcp:use']): Token
{
    return Token::query()->forceCreate([
        'id' => Str::random(80),
        'user_id' => $user->id,
        'client_id' => $client->getKey(),
        'name' => null,
        'scopes' => $scopes,
        'revoked' => false,
        'created_at' => now(),
        'updated_at' => now(),
        'expires_at' => now()->addDays(15),
    ]);
}

function createOauthClient(string $name = 'Claude'): Client
{
    return Client::query()->forceCreate([
        'name' => $name,
        'secret' => null,
        'provider' => null,
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
        'grant_types' => ['authorization_code', 'refresh_token'],
        'revoked' => false,
    ]);
}

test('the ai connections page lists active connections and change history', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::AiAssistant, true);
    $client = createOauthClient();
    createAccessToken($user, $client);
    createAccessToken($user, $client);
    McpProposal::factory()->confirmed()->create([
        'user_id' => $user->id,
        'client_name' => 'Claude',
    ]);

    $this->actingAs($user)
        ->get(route('ai-connections.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/AiConnections')
            ->has('connections', 1)
            ->where('connections.0.client_name', 'Claude')
            ->where('connections.0.active_sessions', 2)
            ->has('history', 1)
            ->where('history.0.status', 'confirmed')
            ->where('mcpUrl', url('/mcp/finance')));
});

test('revoked and expired tokens are not listed', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::AiAssistant, true);
    $client = createOauthClient();

    $revoked = createAccessToken($user, $client);
    $revoked->forceFill(['revoked' => true])->save();

    $expired = createAccessToken($user, $client);
    $expired->forceFill(['expires_at' => now()->subDay()])->save();

    $this->actingAs($user)
        ->get(route('ai-connections.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('connections', 0));
});

test('a user can revoke all active sessions for their own MCP connection', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::AiAssistant, true);
    $client = createOauthClient();
    $firstToken = createAccessToken($user, $client);
    $secondToken = createAccessToken($user, $client);
    $nonMcpToken = createAccessToken($user, $client, ['profile:read']);

    RefreshToken::query()->forceCreate([
        'id' => Str::random(80),
        'access_token_id' => $firstToken->getKey(),
        'revoked' => false,
        'expires_at' => now()->addDays(30),
    ]);
    RefreshToken::query()->forceCreate([
        'id' => Str::random(80),
        'access_token_id' => $secondToken->getKey(),
        'revoked' => false,
        'expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($user)
        ->delete(route('ai-connections.destroy', $client->getKey()))
        ->assertRedirect();

    expect($firstToken->fresh()->revoked)->toBeTrue()
        ->and($secondToken->fresh()->revoked)->toBeTrue()
        ->and($nonMcpToken->fresh()->revoked)->toBeFalse()
        ->and(RefreshToken::query()->where('access_token_id', $firstToken->getKey())->first()->revoked)->toBeTrue()
        ->and(RefreshToken::query()->where('access_token_id', $secondToken->getKey())->first()->revoked)->toBeTrue();
});

test('a user can pause all their ai connections at once', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::AiAssistant, true);
    $claude = createOauthClient('Claude');
    $chatgpt = createOauthClient('ChatGPT');

    $claudeToken = createAccessToken($user, $claude);
    $chatgptToken = createAccessToken($user, $chatgpt);
    $nonMcpToken = createAccessToken($user, $claude, ['profile:read']);

    RefreshToken::query()->forceCreate([
        'id' => Str::random(80),
        'access_token_id' => $claudeToken->getKey(),
        'revoked' => false,
        'expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($user)
        ->delete(route('ai-connections.revoke-all'))
        ->assertRedirect();

    expect($claudeToken->fresh()->revoked)->toBeTrue()
        ->and($chatgptToken->fresh()->revoked)->toBeTrue()
        ->and($nonMcpToken->fresh()->revoked)->toBeFalse()
        ->and(RefreshToken::query()->where('access_token_id', $claudeToken->getKey())->first()->revoked)->toBeTrue();
});

test('pausing all ai connections does not affect another user\'s tokens', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    app(UpdateUserFeature::class)($userB, Feature::AiAssistant, true);
    $client = createOauthClient();
    $tokenA = createAccessToken($userA, $client);

    $this->actingAs($userB)
        ->delete(route('ai-connections.revoke-all'))
        ->assertRedirect();

    expect($tokenA->fresh()->revoked)->toBeFalse();
});

test('a user cannot revoke another user\'s connection', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    app(UpdateUserFeature::class)($userB, Feature::AiAssistant, true);
    $client = createOauthClient();
    $token = createAccessToken($userA, $client);

    $this->actingAs($userB)
        ->delete(route('ai-connections.destroy', $client->getKey()))
        ->assertNotFound();

    expect($token->fresh()->revoked)->toBeFalse();
});
