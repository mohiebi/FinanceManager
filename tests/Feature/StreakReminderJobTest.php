<?php

use App\Enums\Feature;
use App\Jobs\SendTelegramMessageJob;
use App\Jobs\StreakReminderJob;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\StreakOpenNotification;
use App\Support\StreakCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/** A user set up to receive the nudge, short of anything actually logged. */
function nudgeableUser(string $timezone = 'UTC', string $chatId = '12345'): User
{
    return User::factory()->withModules(Feature::Gamification)->create([
        // Unique on users, so tests with two recipients must differ.
        'telegram_chat_id' => $chatId,
        'streak_nudge_enabled' => true,
        'timezone' => $timezone,
    ]);
}

function runReminder(): void
{
    app(StreakReminderJob::class)->handle(app(StreakCalculator::class));
}

test('it nudges a user whose day is still open at 21:00 their time', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-30 21:00:00', 'UTC'));
    Notification::fake();

    try {
        $user = nudgeableUser();

        runReminder();

        Notification::assertSentTo($user, StreakOpenNotification::class);
    } finally {
        Carbon::setTestNow();
    }
});

test('it nudges each user at 21:00 in their own timezone, not the server\'s', function () {
    // 17:30 UTC is 21:00 in Tehran (+03:30) and nowhere near it in UTC.
    Carbon::setTestNow(Carbon::parse('2026-07-30 17:30:00', 'UTC'));
    Notification::fake();

    try {
        $tehran = nudgeableUser('Asia/Tehran', '11111');
        $utc = nudgeableUser('UTC', '22222');

        runReminder();

        Notification::assertSentTo($tehran, StreakOpenNotification::class);
        Notification::assertNotSentTo($utc, StreakOpenNotification::class);
    } finally {
        Carbon::setTestNow();
    }
});

test('it sends at most once a day even if the schedule fires twice', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-30 21:00:00', 'UTC'));
    Notification::fake();

    try {
        $user = nudgeableUser();

        runReminder();
        runReminder();

        Notification::assertSentToTimes($user, StreakOpenNotification::class, 1);
    } finally {
        Carbon::setTestNow();
    }
});

test('it stays quiet for a user who already logged today', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-30 21:00:00', 'UTC'));
    Notification::fake();

    try {
        $user = nudgeableUser();
        $category = Category::factory()->cost()->forUser($user)->create();
        Transaction::factory()->cost()->for($user)->for($category)->create([
            'occurred_at' => '2026-07-30',
        ]);

        runReminder();

        // Scoped to the nudge: writing that transaction also earns a first-record
        // milestone, which is a different notification with its own test.
        Notification::assertNotSentTo($user, StreakOpenNotification::class);
    } finally {
        Carbon::setTestNow();
    }
});

test('it skips users who have not opted in, or have the module off', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-30 21:00:00', 'UTC'));
    Notification::fake();

    try {
        // Module on, nudge never switched on.
        User::factory()->withModules(Feature::Gamification)->create([
            'telegram_chat_id' => '12345',
        ]);

        // Opted in, but with the module switched off.
        User::factory()->withoutModules(Feature::Gamification)->create([
            'telegram_chat_id' => '67890',
            'streak_nudge_enabled' => true,
        ]);

        // Opted in with the module on, but nowhere to deliver it.
        User::factory()->withModules(Feature::Gamification)->create([
            'streak_nudge_enabled' => true,
        ]);

        runReminder();

        Notification::assertNothingSent();
    } finally {
        Carbon::setTestNow();
    }
});

test('it reaches telegram and leaks no amounts into the stored payload', function () {
    // The account has to predate the run, or the walk clamps at the signup date.
    Carbon::setTestNow(Carbon::parse('2026-07-01 09:00:00', 'UTC'));
    Queue::fake();

    try {
        $user = nudgeableUser();
        $category = Category::factory()->cost()->forUser($user)->create();

        // A run of two days, so the nudge has a number to quote.
        foreach (['2026-07-28', '2026-07-29'] as $date) {
            Transaction::factory()->cost()->for($user)->for($category)->create([
                'occurred_at' => $date,
                'amount' => 987654,
                'title' => 'Very secret purchase',
            ]);
        }

        Carbon::setTestNow(Carbon::parse('2026-07-30 21:00:00', 'UTC'));

        runReminder();

        Queue::assertPushedOn(
            'telegram',
            SendTelegramMessageJob::class,
            fn (SendTelegramMessageJob $job): bool => $job->chatId === '12345'
                && str_contains($job->message, '3'),
        );

        // `notifications.data` is not encrypted, so nothing that the transactions
        // table bothers to encrypt may be repeated in it.
        $stored = $user->notifications()->first()->data;

        expect($stored['current_run'])->toBe(2)
            ->and(json_encode($stored))->not->toContain('987654')
            ->and(json_encode($stored))->not->toContain('Very secret purchase');
    } finally {
        Carbon::setTestNow();
    }
});

test('a user with no run at all is invited to start one', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-30 21:00:00', 'UTC'));
    Queue::fake();

    try {
        nudgeableUser();

        runReminder();

        // "Day 1 is still open" would be a lie about a run that does not exist.
        Queue::assertPushedOn(
            'telegram',
            SendTelegramMessageJob::class,
            fn (SendTelegramMessageJob $job): bool => str_contains($job->message, 'start a run'),
        );
    } finally {
        Carbon::setTestNow();
    }
});
