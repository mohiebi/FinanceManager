<?php

use App\Models\User;
use App\Support\Admin\McpTokenQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('app.admin_email', 'boss@example.com');
});

function visibilityAdmin(): User
{
    $admin = User::factory()->create(['email' => 'boss@example.com']);

    test()->actingAs($admin);

    return $admin;
}

/**
 * A Passport token row, written directly.
 *
 * Passport's own factories are not registered here, and the aggregate under
 * test reads these columns in SQL rather than through the model, so the raw
 * row is both sufficient and closer to what production holds.
 *
 * @param  array<int, string>  $scopes
 */
function mcpToken(User $user, array $scopes = ['mcp:use'], array $overrides = []): void
{
    DB::table('oauth_access_tokens')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'client_id' => (string) Str::uuid(),
        'name' => 'Test client',
        'scopes' => json_encode($scopes),
        'revoked' => false,
        'created_at' => now(),
        'updated_at' => now(),
        'expires_at' => now()->addYear(),
        ...$overrides,
    ]);
}

test('the dashboard counts Pro accounts and AI connections', function () {
    visibilityAdmin();

    User::factory()->pro()->create();
    User::factory()->proExpired()->create();
    mcpToken(User::factory()->create());
    User::factory()->create(['telegram_chat_id' => '12345']);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.pro_customers', 1)
            ->where('summary.mcp_customers', 1)
            ->where('summary.telegram_customers', 1)
        );
});

test('a wildcard-scoped token still counts as an AI connection', function () {
    // Passport's Token::can() treats '*' as granting everything, so a query
    // looking only for the literal scope would quietly undercount.
    $user = User::factory()->create();
    mcpToken($user, ['*']);

    expect(McpTokenQuery::live()->count())->toBe(1)
        ->and(User::query()->tap(McpTokenQuery::connected(...))->pluck('id')->all())
        ->toBe([$user->id]);
});

test('a token without the MCP scope is not an AI connection', function () {
    mcpToken(User::factory()->create(), ['read']);

    expect(McpTokenQuery::live()->count())->toBe(0);
});

test('revoked and expired tokens stop counting', function () {
    mcpToken(User::factory()->create(), overrides: ['revoked' => true]);
    mcpToken(User::factory()->create(), overrides: ['expires_at' => now()->subDay()]);

    expect(McpTokenQuery::live()->count())->toBe(0);
});

test('a token with no expiry counts as live', function () {
    mcpToken(User::factory()->create(), overrides: ['expires_at' => null]);

    expect(McpTokenQuery::live()->count())->toBe(1);
});

test('the operator is left out of the customer figures', function () {
    // The admin's own account is excluded by customers(), which is why this
    // number is deliberately narrower than the billing console's.
    $admin = visibilityAdmin();
    $admin->forceFill(['pro_until' => now()->addYear()])->save();
    mcpToken($admin);

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.pro_customers', 0)
            ->where('summary.mcp_customers', 0)
        );
});

test('the directory can be filtered to Pro accounts', function () {
    visibilityAdmin();
    $pro = User::factory()->pro()->create();
    User::factory()->create();

    $this->get(route('admin.dashboard', ['pro' => 'active']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $pro->id)
            ->where('users.data.0.pro', true)
        );

    $this->get(route('admin.dashboard', ['pro' => 'inactive']))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1));
});

test('the directory can be filtered to AI connections', function () {
    visibilityAdmin();
    $connected = User::factory()->create();
    mcpToken($connected);
    User::factory()->create();

    $this->get(route('admin.dashboard', ['mcp' => 'connected']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $connected->id)
            ->where('users.data.0.mcp_connected', true)
        );

    $this->get(route('admin.dashboard', ['mcp' => 'disconnected']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.mcp_connected', false)
        );
});

test('an unknown filter value falls back to showing everyone', function () {
    visibilityAdmin();
    User::factory()->count(2)->create();

    $this->get(route('admin.dashboard', ['pro' => 'nonsense', 'mcp' => 'nonsense']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 2)
            ->where('filters.pro', 'all')
            ->where('filters.mcp', 'all')
        );
});
