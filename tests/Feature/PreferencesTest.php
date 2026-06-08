<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('users can update language and calendar preferences', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/preferences', [
            'locale' => 'fa',
            'calendar' => 'jalali',
        ])
        ->assertRedirect();

    expect($user->refresh()->locale)->toBe('fa')
        ->and($user->calendar)->toBe('jalali');
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

test('invalid preferences are rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/preferences', [
            'locale' => 'de',
            'calendar' => 'lunar',
        ])
        ->assertSessionHasErrors(['locale', 'calendar']);
});
