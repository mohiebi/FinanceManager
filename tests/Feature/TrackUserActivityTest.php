<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

test('authenticated web activity is stored at most once every five minutes', function () {
    Carbon::setTestNow('2026-07-15 12:00:00');

    try {
        $user = User::factory()->create(['last_active_at' => null]);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
        expect($user->refresh()->last_active_at?->toDateTimeString())->toBe('2026-07-15 12:00:00');

        Carbon::setTestNow('2026-07-15 12:04:00');
        $this->get(route('profile.edit'))->assertOk();
        expect($user->refresh()->last_active_at?->toDateTimeString())->toBe('2026-07-15 12:00:00');

        Carbon::setTestNow('2026-07-15 12:06:00');
        $this->get(route('profile.edit'))->assertOk();
        expect($user->refresh()->last_active_at?->toDateTimeString())->toBe('2026-07-15 12:06:00');
    } finally {
        Carbon::setTestNow();
    }
});

test('authenticated sanctum activity is tracked', function () {
    Carbon::setTestNow('2026-07-15 12:00:00');

    try {
        $user = User::factory()->create(['last_active_at' => null]);
        Sanctum::actingAs($user);

        $this->getJson('/api/user')->assertOk();

        expect($user->refresh()->last_active_at?->toDateTimeString())->toBe('2026-07-15 12:00:00');
    } finally {
        Carbon::setTestNow();
    }
});
