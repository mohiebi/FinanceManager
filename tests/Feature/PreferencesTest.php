<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('users can update language, calendar and timezone preferences', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/preferences', [
            'locale' => 'de',
            'calendar' => 'gregorian',
            'timezone' => 'Europe/Berlin',
        ])
        ->assertRedirect();

    expect($user->refresh()->locale)->toBe('de')
        ->and($user->calendar)->toBe('gregorian')
        ->and($user->timezone)->toBe('Europe/Berlin');
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
    $user = User::factory()->create([
        'locale' => 'fa',
        'calendar' => 'jalali',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'fa')
            ->where('dir', 'rtl')
            ->where('calendar', 'jalali')
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
        ])
        ->assertSessionHasErrors(['locale', 'calendar', 'timezone']);
});
