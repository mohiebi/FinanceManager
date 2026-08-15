<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('users can update language, calendar, timezone and naming preferences', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/preferences', [
            'locale' => 'de',
            'calendar' => 'gregorian',
            'timezone' => 'Europe/Berlin',
            'flight_terminology_enabled' => false,
        ])
        ->assertRedirect();

    expect($user->refresh()->locale)->toBe('de')
        ->and($user->calendar)->toBe('gregorian')
        ->and($user->timezone)->toBe('Europe/Berlin')
        ->and($user->flight_terminology_enabled)->toBeFalse();
});

test('the timezone decides when a user\'s day starts', function () {
    $tehran = User::factory()->create(['timezone' => 'Asia/Tehran']);
    $utc = User::factory()->create(['timezone' => 'UTC']);

    // 22:00 UTC is already the next day in Tehran (+03:30).
    Carbon::setTestNow(Carbon::parse('2026-07-30 22:00:00', 'UTC'));

    try {
        expect($tehran->localToday()->toDateString())->toBe('2026-07-31')
            ->and($utc->localToday()->toDateString())->toBe('2026-07-30');
    } finally {
        Carbon::setTestNow();
    }
});

test('an unrecognised timezone falls back to UTC rather than guessing', function () {
    $user = User::factory()->create();
    $user->forceFill(['timezone' => 'Mars/Olympus_Mons'])->save();

    expect($user->resolvedTimezone())->toBe('UTC');
});

test('preference props are shared with inertia pages', function () {
    // Every value here is deliberately not the default, so the assertions prove
    // the account's own preferences are what reaches the page.
    $user = User::factory()->create([
        'locale' => 'fa',
        'calendar' => 'jalali',
        'flight_terminology_enabled' => true,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'fa')
            ->where('dir', 'rtl')
            ->where('calendar', 'jalali')
            ->where('flightTerminologyEnabled', true)
            ->where('translations.settings.title', 'تنظیمات'),
        );
});

test('german preference props are shared with inertia pages', function () {
    $user = User::factory()->create([
        'locale' => 'de',
        'calendar' => 'gregorian',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'de')
            ->where('dir', 'ltr')
            ->where('calendar', 'gregorian')
            ->where('translations.settings.title', 'Einstellungen'),
        );
});

test('invalid preferences are rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/preferences', [
            'locale' => 'es',
            'calendar' => 'lunar',
            'timezone' => 'Mars/Olympus_Mons',
            'flight_terminology_enabled' => 'sometimes',
        ])
        ->assertSessionHasErrors(['locale', 'calendar', 'timezone', 'flight_terminology_enabled']);
});

test('standard terminology preference is shared with inertia pages', function () {
    $user = User::factory()->create(['flight_terminology_enabled' => false]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('flightTerminologyEnabled', false),
        );
});

/**
 * Flight naming is opt-in.
 *
 * It shipped switched on and read as a personality the account had not asked
 * for, so a new account now starts on the familiar names and turns the themed
 * ones on deliberately.
 */
test('a new account starts on standard naming', function () {
    $user = User::query()->create([
        'name' => 'Fresh',
        'email' => 'fresh@example.com',
        'password' => 'password',
    ]);

    expect($user->flight_terminology_enabled)->toBeFalse()
        ->and($user->fresh()->flight_terminology_enabled)->toBeFalse();
});

test('a page with no signed in user gets standard naming', function () {
    // The prop still has to be present and false — an absent one would let the
    // client fall back to whatever it treats as the default.
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('flightTerminologyEnabled', false),
        );
});
