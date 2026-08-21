<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

/**
 * The `array` session driver used in tests never writes to the `sessions`
 * table itself, so every row a test needs is inserted directly.
 */
function insertSessionRow(User $user, array $overrides = []): string
{
    $id = $overrides['id'] ?? Str::random(40);

    DB::table('sessions')->insert(array_merge([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '203.0.113.5',
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->timestamp,
    ], $overrides, ['id' => $id]));

    return $id;
}

test('security page is displayed', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->where('canManageTwoFactor', true)
            ->where('twoFactorEnabled', false),
        );
});

test('security page requires password confirmation when enabled', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->create();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->where('needsPasswordConfirmation', true),
        );
});

test('security page does not require password confirmation once confirmed', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->create();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->where('needsPasswordConfirmation', false),
        );
});

test('users can confirm their password from the security page modal', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->create();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $response = $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('security.confirm-password'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    $this->assertTrue(session()->has('auth.password_confirmed_at'));
});

test('confirming the password fails with an incorrect password', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->create();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $response = $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('security.confirm-password'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('security.edit'));

    $this->assertFalse(session()->has('auth.password_confirmed_at'));
});

test('security page does not require password confirmation when disabled', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->create();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => false,
    ]);

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security'),
        );
});

test('security page renders without two factor when feature is disabled', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    config(['fortify.features' => []]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->where('canManageTwoFactor', false)
            ->missing('twoFactorEnabled')
            ->missing('requiresConfirmation'),
        );
});

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('security.edit'));
});

test('passwordless users can view the security page without password confirmation', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->passwordless()->create();

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->where('hasPassword', false),
        );
});

test('passwordless users can add a password without providing a current password', function () {
    $user = User::factory()->passwordless()->create();

    $response = $this
        ->actingAs($user)
        ->put(route('user-password.update'), [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('passwordless users do not need to submit current password when adding a password', function () {
    $user = User::factory()->passwordless()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => '',
            'password' => 'another-new-password',
            'password_confirmation' => 'another-new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('another-new-password', $user->refresh()->password))->toBeTrue();
});

test('the security page lists the users sign-in sessions, newest activity first', function () {
    $user = User::factory()->create();

    $older = insertSessionRow($user, [
        'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'last_activity' => now()->subDay()->timestamp,
    ]);
    $newer = insertSessionRow($user, [
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->has('sessions', 2)
            ->where('sessions.0.id', $newer)
            ->where('sessions.0.browser', 'Chrome')
            ->where('sessions.0.platform', 'Windows')
            ->where('sessions.1.id', $older)
            ->where('sessions.1.browser', 'Firefox'),
        );
});

test('another users sign-in sessions are never listed', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    insertSessionRow($otherUser);

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->has('sessions', 0),
        );
});

test('a session other than the one making the request is not flagged as current', function () {
    $user = User::factory()->create();
    insertSessionRow($user, ['id' => 'a-different-browser-session']);

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sessions.0.is_current', false),
        );
});

test('the session behind the current request is flagged as current', function () {
    $user = User::factory()->create();

    // The array session driver still round-trips a real session cookie —
    // resending it (rather than relying on the in-process session ID alone)
    // is what makes the second request resolve to the same session the
    // first one started, exactly as a real browser would. withCookie()
    // encrypts the value itself, so the plain session ID goes in.
    $this->actingAs($user)->get(route('security.edit'));
    $currentId = session()->getId();

    insertSessionRow($user, ['id' => $currentId]);

    $this->withCookie(config('session.cookie'), $currentId)
        ->actingAs($user)
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sessions.0.is_current', true),
        );
});

test('a user can revoke one of their other sign-in sessions', function () {
    $user = User::factory()->create();
    $sessionId = insertSessionRow($user);

    $this->actingAs($user)
        ->delete(route('security.sessions.destroy', $sessionId))
        ->assertRedirect();

    expect(DB::table('sessions')->where('id', $sessionId)->exists())->toBeFalse();
});

test('a user cannot revoke the session behind the current request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('security.edit'));
    $currentId = session()->getId();

    insertSessionRow($user, ['id' => $currentId]);

    $this->withCookie(config('session.cookie'), $currentId)
        ->actingAs($user)
        ->delete(route('security.sessions.destroy', $currentId))
        ->assertForbidden();

    expect(DB::table('sessions')->where('id', $currentId)->exists())->toBeTrue();
});

test('a user cannot revoke another users session', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $sessionId = insertSessionRow($otherUser);

    $this->actingAs($user)
        ->delete(route('security.sessions.destroy', $sessionId))
        ->assertNotFound();

    expect(DB::table('sessions')->where('id', $sessionId)->exists())->toBeTrue();
});
