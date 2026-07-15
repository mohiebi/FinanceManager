<?php

use App\Jobs\CaptureDailyStatsJob;
use App\Models\DailyStat;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    config(['app.admin_email' => 'admin@example.com']);
});

test('the job captures one idempotent snapshot of customer metrics per day', function () {
    Carbon::setTestNow('2026-07-15 12:00:00');

    try {
        User::factory()->create([
            'email' => 'admin@example.com',
            'telegram_chat_id' => 'admin-chat',
            'last_active_at' => now(),
        ]);
        User::factory()->create([
            'email' => 'fresh@example.com',
            'telegram_chat_id' => 'fresh-chat',
            'created_at' => now()->subHours(2),
            'last_active_at' => now()->subDay(),
        ]);
        User::factory()->unverified()->create([
            'email' => 'dormant@example.com',
            'birthdate' => null,
            'created_at' => now()->subDays(10),
            'last_active_at' => now()->subDays(20),
        ]);

        CaptureDailyStatsJob::dispatchSync();
        CaptureDailyStatsJob::dispatchSync();

        expect(DailyStat::query()->count())->toBe(1);

        $stat = DailyStat::query()->first();

        expect($stat->date->toDateString())->toBe('2026-07-15')
            ->and($stat->total_customers)->toBe(2)
            ->and($stat->new_customers)->toBe(1)
            ->and($stat->active_customers_7d)->toBe(1)
            ->and($stat->active_customers_30d)->toBe(2)
            ->and($stat->telegram_customers)->toBe(1)
            ->and($stat->verified_customers)->toBe(1)
            ->and($stat->completed_profiles)->toBe(1);
    } finally {
        Carbon::setTestNow();
    }
});
