<?php

use App\Models\BillOccurrence;
use App\Models\User;
use App\Notifications\BillDueNotification;

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
