<?php

use App\Models\McpProposal;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

function createAccessToken(User $user, Client $client): Token
{
    return Token::query()->forceCreate([
        'id' => Str::random(80),
        'user_id' => $user->id,
        'client_id' => $client->getKey(),
        'name' => null,
        'scopes' => ['mcp:use'],
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
    $client = createOauthClient();
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
            ->has('history', 1)
            ->where('history.0.status', 'confirmed')
            ->where('mcpUrl', url('/mcp/finance')));
});

test('revoked and expired tokens are not listed', function () {
    $user = User::factory()->create();
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

test('a user can revoke their own connection', function () {
    $user = User::factory()->create();
    $client = createOauthClient();
    $token = createAccessToken($user, $client);

    RefreshToken::query()->forceCreate([
        'id' => Str::random(80),
        'access_token_id' => $token->getKey(),
        'revoked' => false,
        'expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($user)
        ->delete(route('ai-connections.destroy', $token->getKey()))
        ->assertRedirect();

    expect($token->fresh()->revoked)->toBeTrue()
        ->and(RefreshToken::query()->where('access_token_id', $token->getKey())->first()->revoked)->toBeTrue();
});

test('a user cannot revoke another user\'s connection', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $client = createOauthClient();
    $token = createAccessToken($userA, $client);

    $this->actingAs($userB)
        ->delete(route('ai-connections.destroy', $token->getKey()))
        ->assertNotFound();

    expect($token->fresh()->revoked)->toBeFalse();
});
