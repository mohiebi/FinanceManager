<?php

use App\Models\AuthChallenge;
use App\Models\User;
use App\Notifications\AuthChallengeCodeNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('new email starts signup and sends a verification code', function () {
    Notification::fake();

    $response = $this->post(route('auth.email.start'), [
        'email' => 'New.User@Example.com',
    ]);

    $response
        ->assertRedirect()
        ->assertSessionHas('auth_flow.status', 'new_user')
        ->assertSessionHas('auth_flow.next_step', 'signup_code')
        ->assertSessionHas('auth_flow.email', 'new.user@example.com');

    $this->assertDatabaseHas('auth_challenges', [
        'email' => 'new.user@example.com',
        'purpose' => AuthChallenge::PurposeSignup,
        'consumed_at' => null,
    ]);

    Notification::assertSentOnDemand(AuthChallengeCodeNotification::class);
});

test('existing email starts password login', function () {
    $user = User::factory()->create(['email' => 'known@example.com']);

    $response = $this->post(route('auth.email.start'), [
        'email' => strtoupper($user->email),
    ]);

    $response
        ->assertRedirect()
        ->assertSessionHas('auth_flow.status', 'existing_user')
        ->assertSessionHas('auth_flow.next_step', 'password')
        ->assertSessionHas('auth_flow.email', 'known@example.com');
});

test('new users can verify a code complete signup and become authenticated', function () {
    Notification::fake();

    $this->post(route('auth.email.start'), [
        'email' => 'fresh@example.com',
    ]);

    $code = null;

    Notification::assertSentOnDemand(
        AuthChallengeCodeNotification::class,
        function (AuthChallengeCodeNotification $notification) use (&$code) {
            $code = $notification->code;

            return $notification->purpose === AuthChallenge::PurposeSignup;
        },
    );

    $verifyResponse = $this->post(route('auth.signup.verify'), [
        'email' => 'fresh@example.com',
        'code' => $code,
    ]);

    $verifyResponse
        ->assertRedirect()
        ->assertSessionHas('auth_flow.next_step', 'complete_signup')
        ->assertSessionHas('auth_flow.signup_token');

    $signupToken = session('auth_flow')['signup_token'];

    $response = $this->post(route('auth.signup.complete'), [
        'signup_token' => $signupToken,
        'name' => 'Fresh User',
        'birthdate' => '1991-04-25',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();

    $this->assertDatabaseHas('users', [
        'name' => 'Fresh User',
        'email' => 'fresh@example.com',
        'birthdate' => '1991-04-25',
    ]);

    expect(User::where('email', 'fresh@example.com')->first()->email_verified_at)->not->toBeNull();
});

test('signup verification rejects invalid and expired codes', function () {
    AuthChallenge::factory()->create([
        'email' => 'invalid@example.com',
        'purpose' => AuthChallenge::PurposeSignup,
        'code_hash' => Hash::make('123456'),
    ]);

    $this->post(route('auth.signup.verify'), [
        'email' => 'invalid@example.com',
        'code' => '654321',
    ])->assertSessionHasErrors('code');

    AuthChallenge::query()->where('email', 'invalid@example.com')->delete();

    AuthChallenge::factory()->create([
        'email' => 'invalid@example.com',
        'purpose' => AuthChallenge::PurposeSignup,
        'code_hash' => Hash::make('123456'),
        'expires_at' => now()->subMinute(),
    ]);

    $this->post(route('auth.signup.verify'), [
        'email' => 'invalid@example.com',
        'code' => '123456',
    ])->assertSessionHasErrors('code');
});

test('existing users can log in with password', function () {
    $user = User::factory()->create(['email' => 'login@example.com']);

    $response = $this->post(route('auth.login.password'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
});

test('existing users can log in with a recovery code', function () {
    $user = User::factory()->create(['email' => 'recover@example.com']);

    AuthChallenge::factory()->create([
        'email' => $user->email,
        'purpose' => AuthChallenge::PurposeRecovery,
        'code_hash' => Hash::make('123456'),
    ]);

    $response = $this->post(route('auth.recovery.verify'), [
        'email' => $user->email,
        'code' => '123456',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
});

test('api password login can issue sanctum tokens when a device name is provided', function () {
    $user = User::factory()->create(['email' => 'api@example.com']);

    $response = $this->postJson(route('api.auth.login.password'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'iPhone',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('user.email', 'api@example.com')
        ->assertJsonStructure(['token']);

    expect($response->json('token'))->toBeString()->not->toBeEmpty();
});

test('api recovery code logs in existing users directly', function () {
    $user = User::factory()->create(['email' => 'api-recover@example.com']);

    AuthChallenge::factory()->create([
        'email' => $user->email,
        'purpose' => AuthChallenge::PurposeRecovery,
        'code_hash' => Hash::make('123456'),
    ]);

    $response = $this->postJson(route('api.auth.recovery.verify'), [
        'email' => $user->email,
        'code' => '123456',
        'device_name' => 'CLI',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('user.email', 'api-recover@example.com');

    expect($response->json('token'))->toBeString()->not->toBeEmpty();
});
