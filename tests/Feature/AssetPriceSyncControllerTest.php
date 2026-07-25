<?php

use App\Jobs\RefreshAssetPricesJob;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

test('it forgets the price cache, dispatches a refresh job, and redirects back', function () {
    Queue::fake();
    Cache::flush();
    Cache::put('asset-prices.tgju', ['usd' => 100000.0], now()->addMinutes(5));

    $user = User::factory()->withModules()->create();

    $this->actingAs($user)
        ->from('/portfolio')
        ->post(route('asset-prices.sync'))
        ->assertRedirect('/portfolio');

    expect(Cache::get('asset-prices.tgju'))->toBeNull();

    Queue::assertPushedOn('prices', RefreshAssetPricesJob::class);
});

test('it rejects a manual sync within 5 minutes of the last sync', function () {
    Queue::fake();
    Cache::flush();
    Cache::put('asset-prices.tgju.synced_at', now()->subMinutes(2)->toIso8601String());

    $user = User::factory()->withModules()->create();

    $this->actingAs($user)
        ->post(route('asset-prices.sync'))
        ->assertSessionHasErrors('sync');

    Queue::assertNotPushed(RefreshAssetPricesJob::class);
});

test('it allows a manual sync once 5 minutes have passed since the last sync', function () {
    Queue::fake();
    Cache::flush();
    Cache::put('asset-prices.tgju.synced_at', now()->subMinutes(6)->toIso8601String());

    $user = User::factory()->withModules()->create();

    $this->actingAs($user)
        ->post(route('asset-prices.sync'))
        ->assertSessionDoesntHaveErrors('sync')
        ->assertRedirect();

    Queue::assertPushedOn('prices', RefreshAssetPricesJob::class);
});

test('it is throttled', function () {
    Queue::fake();

    $user = User::factory()->withModules()->create();

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
