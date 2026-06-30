<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;

test('it forgets the price cache and redirects back', function () {
    Cache::flush();
    Cache::put('asset-prices.tgju', ['usd' => 100000.0], now()->addMinutes(5));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/portfolio')
        ->post(route('asset-prices.sync'))
        ->assertRedirect('/portfolio');

    expect(Cache::get('asset-prices.tgju'))->toBeNull();
});

test('it is throttled', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 3; $i++) {
        $this->actingAs($user)->post(route('asset-prices.sync'))->assertRedirect();
    }

    $this->actingAs($user)
        ->post(route('asset-prices.sync'))
        ->assertStatus(429);
});

test('guests cannot sync prices', function () {
    $this->post(route('asset-prices.sync'))
        ->assertRedirect(route('login'));
});
