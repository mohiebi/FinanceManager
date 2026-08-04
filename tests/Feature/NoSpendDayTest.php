<?php

use App\Enums\Feature;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserStreak;
use App\Support\StreakCalculator;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('a user can mark today as spend-free', function () {
    $user = User::factory()->withModules(Feature::Gamification)->create();

    $this->actingAs($user)
        ->post(route('no-spend-days.store'))
        ->assertRedirect();

    $this->assertDatabaseHas('no_spend_days', [
        'user_id' => $user->id,
        'date' => $user->localToday()->toDateString(),
    ]);
});

test('marking twice is not an error and does not duplicate the day', function () {
    $user = User::factory()->withModules(Feature::Gamification)->create();

    $this->actingAs($user)->post(route('no-spend-days.store'))->assertRedirect();
    $this->actingAs($user)->post(route('no-spend-days.store'))->assertRedirect();

    expect($user->noSpendDays()->count())->toBe(1);
});

test('a day that already has a transaction is not marked spend-free', function () {
    $user = User::factory()->withModules(Feature::Gamification)->create();
    $category = Category::factory()->cost()->forUser($user)->create();

    Transaction::factory()->cost()->for($user)->for($category)->create([
        'occurred_at' => $user->localToday()->toDateString(),
    ]);

    $this->actingAs($user)->post(route('no-spend-days.store'))->assertRedirect();

    // Writing the marker anyway would claim they spent nothing on a day they
    // recorded spending.
    expect($user->noSpendDays()->count())->toBe(0);
});

test('today is resolved in the user own timezone', function () {
    // 22:00 UTC is already the 31st in Tehran.
    Carbon::setTestNow(Carbon::parse('2026-07-30 22:00:00', 'UTC'));

    try {
        $user = User::factory()->withModules(Feature::Gamification)
            ->create(['timezone' => 'Asia/Tehran']);

        $this->actingAs($user)->post(route('no-spend-days.store'))->assertRedirect();

        $this->assertDatabaseHas('no_spend_days', [
            'user_id' => $user->id,
            'date' => '2026-07-31',
        ]);
    } finally {
        Carbon::setTestNow();
    }
});

test('the endpoint is gated on the gamification module', function () {
    // The flight log ships on, so this switches it off explicitly.
    $user = User::factory()->withoutModules(Feature::Gamification)->create();

    $this->actingAs($user)
        ->post(route('no-spend-days.store'))
        ->assertRedirect(route('modules.edit'));

    expect($user->noSpendDays()->count())->toBe(0);
});

test('the dashboard ships the streak only when the module is on', function () {
    $off = User::factory()->withoutModules(Feature::Gamification)->create();
    $on = User::factory()->create();

    $this->actingAs($off)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('streak'));

    $this->actingAs($on)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('streak', fn (Assert $streak) => $streak
                ->where('current_run', 0)
                ->where('logged_today', false)
                ->where('grace_remaining', 1)
                ->has('days', 14)
                ->etc())
            ->etc());
});

test('the streak survives two requests racing to create the same row', function () {
    $user = User::factory()->withModules(Feature::Gamification)->create();

    // Two separate instances stand in for two parallel dashboard requests: both
    // miss the select, both insert, and the loser must not 500 the page.
    $first = User::query()->findOrFail($user->id);
    $second = User::query()->findOrFail($user->id);

    $calculator = app(StreakCalculator::class);

    $calculator->for($first);
    $calculator->for($second);

    expect(UserStreak::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('the nudge toggle is refused while the module is off', function () {
    $user = User::factory()->withoutModules(Feature::Gamification)->create();

    $this->actingAs($user)
        ->patch(route('notifications.preferences'), ['streak_nudge_enabled' => true])
        ->assertForbidden();

    expect($user->fresh()->streak_nudge_enabled)->toBeFalse();
});

test('the nudge toggle works once the module is on and telegram is linked', function () {
    $user = User::factory()
        ->withModules(Feature::Gamification)
        ->create(['telegram_chat_id' => '98620653']);

    $this->actingAs($user)
        ->patch(route('notifications.preferences'), ['streak_nudge_enabled' => true])
        ->assertRedirect();

    expect($user->fresh()->streak_nudge_enabled)->toBeTrue();
});
