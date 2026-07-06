<?php

use App\Models\User;

test('a guest can switch the locale via the session', function () {
    $response = $this->post(route('locale.update'), ['locale' => 'fa']);

    $response->assertRedirect();
    expect(session('locale'))->toBe('fa');
});

test('the landing page renders in the guest session locale', function () {
    $this->post(route('locale.update'), ['locale' => 'fa']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($assert) => $assert
            ->component('Landing')
            ->where('locale', 'fa')
            ->where('dir', 'rtl'));
});

test('the localized landing route sets the guest session locale', function () {
    $this->get(route('home.localized', ['locale' => 'de']))
        ->assertOk()
        ->assertInertia(fn ($assert) => $assert
            ->component('Landing')
            ->where('locale', 'de')
            ->where('dir', 'ltr'));

    expect(session('locale'))->toBe('de');
});

test('an authenticated user switching locale persists it on the profile', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->post(route('locale.update'), ['locale' => 'fa'])
        ->assertRedirect();

    expect($user->fresh()->locale)->toBe('fa');
});

test('an unsupported locale is rejected', function () {
    $this->from(route('home'))
        ->post(route('locale.update'), ['locale' => 'xx'])
        ->assertSessionHasErrors('locale');

    expect(session('locale'))->toBeNull();
});

test('the landing page defaults to english with ltr direction', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($assert) => $assert
            ->component('Landing')
            ->where('locale', 'en')
            ->where('dir', 'ltr'));
});
