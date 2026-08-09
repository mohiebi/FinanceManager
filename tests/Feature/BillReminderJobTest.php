<?php

use App\Jobs\BillReminderJob;
use App\Jobs\SendTelegramMessageJob;
use App\Models\User;
use App\Notifications\BillDueNotification;
use App\Support\BillDueDateCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

test('it skips users who have the bills module switched off', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 14, 9));
    Notification::fake();

    try {
        // No withModules() — bills ships switched off.
        $user = User::factory()->create(['telegram_chat_id' => '12345']);

        $bill = $user->bills()->create([
            'title' => 'Rent',
            'amount' => 100,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 15,
        ]);

        $bill->occurrences()->create(['due_date' => '2026-07-15']);

        app(BillReminderJob::class)->handle(app(BillDueDateCalculator::class));

        Notification::assertNothingSent();
    } finally {
        Carbon::setTestNow();
    }
});

test('it sends an advance reminder exactly once, N days before the due date', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 12, 9));
    Notification::fake();

    try {
        $user = User::factory()->withModules()->create([
            'telegram_chat_id' => '12345',
            'calendar' => 'jalali',
        ]);

        expect(BillReminderJob::ADVANCE_REMINDER_DAYS)->toBe(3);

        $bill = $user->bills()->create([
            'title' => 'Rent',
            'amount' => 100,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 15,
        ]);

        $occurrence = $bill->occurrences()->create(['due_date' => '2026-07-15']);

        app(BillReminderJob::class)->handle(app(BillDueDateCalculator::class));

        Notification::assertSentTo($user, BillDueNotification::class, fn ($notification) => $notification->reason === 'day_before'
            && $notification->occurrence->is($occurrence));

        Notification::assertSentToTimes($user, BillDueNotification::class, 1);

        $occurrence->refresh();
        expect($occurrence->reminder_day_before_sent_at)->not->toBeNull()
            ->and($occurrence->reminder_due_day_sent_at)->toBeNull();

        // Running it again the same day must not duplicate the reminder.
        app(BillReminderJob::class)->handle(app(BillDueDateCalculator::class));
        Notification::assertSentToTimes($user, BillDueNotification::class, 1);
    } finally {
        Carbon::setTestNow();
    }
});

test('it does not send the advance reminder when the user has turned it off', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 12, 9));
    Notification::fake();

    try {
        $user = User::factory()->withModules()->create([
            'telegram_chat_id' => '12345',
            'bill_advance_reminder_enabled' => false,
        ]);

        $bill = $user->bills()->create([
            'title' => 'Rent',
            'amount' => 100,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 15,
        ]);

        $bill->occurrences()->create(['due_date' => '2026-07-15']);

        app(BillReminderJob::class)->handle(app(BillDueDateCalculator::class));

        Notification::assertNothingSent();
    } finally {
        Carbon::setTestNow();
    }
});

test('it sends a due-day reminder and dispatches a telegram message when linked', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 15, 9));
    Queue::fake();

    try {
        $user = User::factory()->withModules()->create([
            'telegram_chat_id' => '12345',
            'calendar' => 'jalali',
        ]);

        $bill = $user->bills()->create([
            'title' => 'Phone',
            'amount' => 250000,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 15,
        ]);

        $bill->occurrences()->create(['due_date' => '2026-07-15']);

        app(BillReminderJob::class)->handle(app(BillDueDateCalculator::class));

        $notification = $user->notifications()->sole();
        expect($notification->data['type'])->toBe('bill_due_today')
            ->and($notification->data['bill_id'])->toBe($bill->id)
            ->and($notification->data['body'])->toContain('1405-04-24')
            ->and($notification->data['body'])->not->toContain('2026-07-15')
            // notifications.data is not encrypted, so the stored body must not
            // repeat what the bills table goes to the trouble of encrypting.
            ->and($notification->data['body'])->not->toContain('Phone')
            ->and($notification->data['body'])->not->toContain('250,000')
            ->and($notification->data['body'])->not->toContain('250000');

        Queue::assertPushedOn('telegram', SendTelegramMessageJob::class, fn ($job) => $job->chatId === '12345');
    } finally {
        Carbon::setTestNow();
    }
});

test('it skips telegram dispatch when the user has not linked telegram', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 15, 9));
    Queue::fake();

    try {
        $user = User::factory()->withModules()->create(['telegram_chat_id' => null]);

        $bill = $user->bills()->create([
            'title' => 'Internet',
            'amount' => 100000,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 15,
        ]);

        $bill->occurrences()->create(['due_date' => '2026-07-15']);

        app(BillReminderJob::class)->handle(app(BillDueDateCalculator::class));

        expect($user->notifications()->count())->toBe(1);
        Queue::assertNotPushed(SendTelegramMessageJob::class);
    } finally {
        Carbon::setTestNow();
    }
});

test('it does not remind for a paid occurrence', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 15, 9));
    Notification::fake();

    try {
        $user = User::factory()->withModules()->create();

        $bill = $user->bills()->create([
            'title' => 'Rent',
            'amount' => 100,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 15,
        ]);

        $bill->occurrences()->create(['due_date' => '2026-07-15', 'paid_at' => now()]);

        app(BillReminderJob::class)->handle(app(BillDueDateCalculator::class));

        Notification::assertNothingSent();
    } finally {
        Carbon::setTestNow();
    }
});

test('it generates the next occurrence for a recurring bill once the previous one is paid', function () {
    Carbon::setTestNow(Carbon::create(2026, 8, 1));

    try {
        $user = User::factory()->withModules()->create();

        $bill = $user->bills()->create([
            'title' => 'Rent',
            'amount' => 100,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 1,
        ]);

        $bill->occurrences()->create([
            'due_date' => '2026-07-01',
            'paid_at' => now()->subDays(5),
        ]);

        app(BillReminderJob::class)->handle(app(BillDueDateCalculator::class));

        expect($bill->occurrences()->count())->toBe(2);
        $latest = $bill->occurrences()->whereNull('paid_at')->sole();
        expect($latest->due_date->toDateString())->toBe('2026-08-01');
    } finally {
        Carbon::setTestNow();
    }
});

test('it does not send a reminder when the computed due date lands on an already-paid occurrence', function () {
    Carbon::setTestNow(Carbon::create(2026, 8, 1));
    Notification::fake();

    try {
        $user = User::factory()->withModules()->create();

        $bill = $user->bills()->create([
            'title' => 'Rent',
            'amount' => 100,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 1,
        ]);

        // Paid early for today's exact due date — no unpaid occurrence exists,
        // so ensureUpcomingOccurrence's firstOrCreate() will find this row
        // instead of creating a fresh one.
        $bill->occurrences()->create([
            'due_date' => '2026-08-01',
            'paid_at' => now(),
        ]);

        app(BillReminderJob::class)->handle(app(BillDueDateCalculator::class));

        expect($bill->occurrences()->count())->toBe(1);
        Notification::assertNothingSent();
    } finally {
        Carbon::setTestNow();
    }
});
