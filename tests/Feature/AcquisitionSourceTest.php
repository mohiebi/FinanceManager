<?php

use App\Models\AuthChallenge;
use App\Notifications\AuthChallengeCodeNotification;
use App\Support\AcquisitionSource;
use Illuminate\Support\Facades\Notification;

test('utm parameters are captured once per guest session', function () {
    $this->get('/?utm_source=Twitter&utm_medium=Social')->assertSuccessful();

    expect(session(AcquisitionSource::SESSION_KEY))->toBe('twitter / social');

    // First-touch attribution: a later visit must not overwrite the source.
    $this->get('/?utm_source=newsletter')->assertSuccessful();

    expect(session(AcquisitionSource::SESSION_KEY))->toBe('twitter / social');
});

test('an external referrer is captured when no utm parameters are present', function () {
    $this->get('/', ['referer' => 'https://news.ycombinator.com/item?id=1'])
        ->assertSuccessful();

    expect(session(AcquisitionSource::SESSION_KEY))->toBe('news.ycombinator.com');
});

test('internal referrers are ignored', function () {
    $this->get('/', ['referer' => config('app.url').'/transactions'])
        ->assertSuccessful();

    expect(session()->has(AcquisitionSource::SESSION_KEY))->toBeFalse();
});

test('the captured source is stored on the account created during signup', function () {
    Notification::fake();

    $this->get('/?utm_source=producthunt')->assertSuccessful();

    $this->post(route('auth.email.start'), [
        'email' => 'attributed@example.com',
    ]);

    $code = null;

    Notification::assertSentOnDemand(
        AuthChallengeCodeNotification::class,
        function (AuthChallengeCodeNotification $notification) use (&$code) {
            $code = $notification->code;

            return $notification->purpose === AuthChallenge::PurposeSignup;
        },
    );

    $this->post(route('auth.signup.verify'), [
        'email' => 'attributed@example.com',
        'code' => $code,
    ]);

    $this->post(route('auth.signup.complete'), [
        'signup_token' => session('auth_flow')['signup_token'],
        'email' => 'attributed@example.com',
        'name' => 'Attributed User',
        'birthdate' => '1991-04-25',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'attributed@example.com',
        'signup_source' => 'producthunt',
    ]);

    // The source is consumed by the signup so it cannot leak to another account.
    expect(session()->has(AcquisitionSource::SESSION_KEY))->toBeFalse();
});
