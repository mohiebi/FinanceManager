<?php

use App\Enums\Feature;
use App\Models\BillOccurrence;
use App\Models\User;
use App\Notifications\BillDueNotification;
use Inertia\Testing\AssertableInertia as Assert;

test('it lists the users notifications', function () {
    $user = User::factory()->create();
    $user->notify(new BillDueNotification(
        $user->bills()->create([
            'title' => 'Rent', 'amount' => 100, 'currency' => 'toman',
            'recurrence_type' => 'monthly', 'due_day_of_month' => 1,
        ]),
        new BillOccurrence(['due_date' => '2026-07-01']),
        'due_day',
    ));

    $this->actingAs($user)
        ->get(route('notifications.edit'))
        ->assertOk();
});

test('it marks a single notification as read', function () {
    $user = User::factory()->create();
    $bill = $user->bills()->create([
        'title' => 'Rent', 'amount' => 100, 'currency' => 'toman',
        'recurrence_type' => 'monthly', 'due_day_of_month' => 1,
    ]);
    $occurrence = $bill->occurrences()->create(['due_date' => '2026-07-01']);
    $user->notify(new BillDueNotification($bill, $occurrence, 'due_day'));

    $notification = $user->notifications()->sole();
    expect($notification->read_at)->toBeNull();

    $this->actingAs($user)
        ->patch(route('notifications.read', $notification->id))
        ->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('it marks all notifications as read', function () {
    $user = User::factory()->create();
    $bill = $user->bills()->create([
        'title' => 'Rent', 'amount' => 100, 'currency' => 'toman',
        'recurrence_type' => 'monthly', 'due_day_of_month' => 1,
    ]);

    foreach (['2026-07-01', '2026-08-01'] as $date) {
        $occurrence = $bill->occurrences()->create(['due_date' => $date]);
        $user->notify(new BillDueNotification($bill, $occurrence, 'due_day'));
    }

    expect($user->unreadNotifications()->count())->toBe(2);

    $this->actingAs($user)
        ->patch(route('notifications.read-all'))
        ->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('users cannot mark another users notification as read', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $bill = $owner->bills()->create([
        'title' => 'Rent', 'amount' => 100, 'currency' => 'toman',
        'recurrence_type' => 'monthly', 'due_day_of_month' => 1,
    ]);
    $occurrence = $bill->occurrences()->create(['due_date' => '2026-07-01']);
    $owner->notify(new BillDueNotification($bill, $occurrence, 'due_day'));
    $notification = $owner->notifications()->sole();

    $this->actingAs($intruder)
        ->patch(route('notifications.read', $notification->id))
        ->assertNotFound();
});

test('the streak nudge is unavailable until telegram is linked', function () {
    // The nudge is delivered over Telegram, so without a linked chat the switch
    // has nowhere to send and renders disabled rather than lying.
    $user = User::factory()->create(['telegram_chat_id' => null]);

    $this->actingAs($user)
        ->get(route('notifications.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('streakNudge.available', false)
            ->where('streakNudge.enabled', false)
            ->where('streakNudge.telegramLinked', false)
            ->etc());
});

test('linking telegram makes the streak nudge available', function () {
    $user = User::factory()->create(['telegram_chat_id' => '98620653']);

    $this->actingAs($user)
        ->get(route('notifications.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('streakNudge.available', true)
            ->where('streakNudge.telegramLinked', true)
            ->etc());
});

test('the streak nudge preference persists once it can be switched on', function () {
    $user = User::factory()->create(['telegram_chat_id' => '98620653']);

    $this->actingAs($user)
        ->patch(route('notifications.preferences'), ['streak_nudge_enabled' => true])
        ->assertRedirect();

    expect($user->fresh()->streak_nudge_enabled)->toBeTrue();
});

test('the streak nudge is refused until telegram is linked', function () {
    $user = User::factory()->create(['telegram_chat_id' => null]);

    $this->actingAs($user)
        ->patch(route('notifications.preferences'), ['streak_nudge_enabled' => true])
        ->assertForbidden();

    expect($user->fresh()->streak_nudge_enabled)->toBeFalse();
});

test('the streak nudge is refused while the flight log is off', function () {
    $user = User::factory()
        ->withoutModules(Feature::Gamification)
        ->create(['telegram_chat_id' => '98620653']);

    // A stale tab would otherwise store a preference that contradicts what the
    // settings page shows.
    $this->actingAs($user)
        ->patch(route('notifications.preferences'), ['streak_nudge_enabled' => true])
        ->assertForbidden();

    expect($user->fresh()->streak_nudge_enabled)->toBeFalse();
});

test('disconnecting telegram disables the streak nudge', function () {
    $user = User::factory()
        ->withModules(Feature::TelegramBot)
        ->create([
            'telegram_chat_id' => '98620653',
            'streak_nudge_enabled' => true,
        ]);

    $this->actingAs($user)
        ->delete(route('telegram.disconnect'))
        ->assertRedirect();

    expect($user->fresh())
        ->telegram_chat_id->toBeNull()
        ->streak_nudge_enabled->toBeFalse();
});

test('the bill advance reminder is unavailable until telegram is linked', function () {
    $user = User::factory()->withModules(Feature::Bills)->create(['telegram_chat_id' => null]);

    $this->actingAs($user)
        ->get(route('notifications.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('billAdvanceReminder.available', false)
            ->etc());
});

test('linking telegram makes the bill advance reminder available', function () {
    $user = User::factory()->withModules(Feature::Bills)->create(['telegram_chat_id' => '98620653']);

    $this->actingAs($user)
        ->get(route('notifications.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('billAdvanceReminder.available', true)
            ->etc());
});

test('the bill advance reminder preference persists once it can be switched on', function () {
    $user = User::factory()->withModules(Feature::Bills)->create(['telegram_chat_id' => '98620653']);

    $this->actingAs($user)
        ->patch(route('notifications.preferences'), ['bill_advance_reminder_enabled' => false])
        ->assertRedirect();

    expect($user->fresh()->bill_advance_reminder_enabled)->toBeFalse();
});

test('turning the bill advance reminder on is refused while bills is off', function () {
    $user = User::factory()
        ->withoutModules(Feature::Bills)
        ->create([
            'telegram_chat_id' => '98620653',
            'bill_advance_reminder_enabled' => false,
        ]);

    $this->actingAs($user)
        ->patch(route('notifications.preferences'), ['bill_advance_reminder_enabled' => true])
        ->assertForbidden();

    expect($user->fresh()->bill_advance_reminder_enabled)->toBeFalse();
});

test('either notification preference can be saved without resending the other', function () {
    $user = User::factory()->withModules(Feature::Bills, Feature::Gamification)->create([
        'telegram_chat_id' => '98620653',
        'streak_nudge_enabled' => true,
    ]);

    $this->actingAs($user)
        ->patch(route('notifications.preferences'), ['bill_advance_reminder_enabled' => false])
        ->assertRedirect();

    expect($user->fresh())
        ->streak_nudge_enabled->toBeTrue()
        ->bill_advance_reminder_enabled->toBeFalse();
});
