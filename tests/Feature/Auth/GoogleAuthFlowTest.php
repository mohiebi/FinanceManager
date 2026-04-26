<?php

use App\Models\SocialAccount;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('login page can redirect users to google', function () {
    Socialite::fake(SocialAccount::ProviderGoogle);

    $response = $this->get(route('auth.google.redirect'));

    $response->assertRedirect('https://socialite.fake/google/authorize');
});

test('new google users are logged in and sent to profile completion', function () {
    Socialite::fake(SocialAccount::ProviderGoogle, fakeGoogleUser([
        'sub' => 'google-user-123',
        'name' => 'Google User',
        'email' => 'google.user@example.com',
        'email_verified' => true,
    ]));

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('profile.edit'));
    $this->assertAuthenticated();

    $user = User::query()->where('email', 'google.user@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user?->hasPassword())->toBeFalse();
    expect($user?->email_verified_at)->not->toBeNull();
    expect($user?->requiresProfileCompletion())->toBeTrue();

    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user?->id,
        'provider' => SocialAccount::ProviderGoogle,
        'provider_user_id' => 'google-user-123',
        'provider_email' => 'google.user@example.com',
    ]);
});

test('google callback links existing users by email and redirects complete profiles to the dashboard', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'known@example.com',
    ]);

    Socialite::fake(SocialAccount::ProviderGoogle, fakeGoogleUser([
        'sub' => 'google-user-456',
        'name' => $user->name,
        'email' => 'KNOWN@example.com',
        'email_verified' => true,
    ]));

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);

    expect($user->refresh()->email_verified_at)->not->toBeNull();

    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => SocialAccount::ProviderGoogle,
        'provider_user_id' => 'google-user-456',
        'provider_email' => 'known@example.com',
    ]);
});

test('api google login issues sanctum tokens and flags incomplete profiles', function () {
    $provider = Mockery::mock();
    $provider->shouldReceive('userFromToken')
        ->once()
        ->with('google-token')
        ->andReturn(fakeGoogleUser([
            'sub' => 'google-user-789',
            'name' => 'Mobile User',
            'email' => 'mobile.user@example.com',
            'email_verified' => true,
        ]));

    Socialite::shouldReceive('driver')
        ->once()
        ->with(SocialAccount::ProviderGoogle)
        ->andReturn($provider);

    $response = $this->postJson(route('api.auth.login.google'), [
        'token' => 'google-token',
        'device_name' => 'iPhone',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('user.email', 'mobile.user@example.com')
        ->assertJsonPath('has_password', false)
        ->assertJsonPath('requires_profile_completion', true)
        ->assertJsonPath('next_step', 'complete_profile');

    expect($response->json('token'))->toBeString()->not->toBeEmpty();
});

test('authenticated users with incomplete profiles are redirected to profile settings', function () {
    $user = User::factory()->passwordless()->create([
        'email_verified_at' => now(),
        'birthdate' => null,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('status', 'Complete your profile to continue.');
});

test('google users can complete their profile through the api', function () {
    $user = User::factory()->passwordless()->create([
        'birthdate' => null,
    ]);

    Sanctum::actingAs($user);

    $response = $this->patchJson(route('api.profile.update'), [
        'name' => 'Updated Name',
        'birthdate' => '1991-04-25',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('user.name', 'Updated Name')
        ->assertJsonPath('requires_profile_completion', false);

    expect($user->refresh()->birthdate?->toDateString())->toBe('1991-04-25');
});

/**
 * @param  array<string, mixed>  $attributes
 */
function fakeGoogleUser(array $attributes): SocialiteUser
{
    return (new SocialiteUser)->setRaw($attributes)->map([
        'id' => $attributes['sub'],
        'name' => $attributes['name'],
        'email' => $attributes['email'],
        'avatar' => $attributes['picture'] ?? null,
    ]);
}
