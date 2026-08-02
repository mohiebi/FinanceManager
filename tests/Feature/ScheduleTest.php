<?php

use App\Jobs\BillReminderJob;
use App\Jobs\CaptureDailyStatsJob;
use App\Jobs\RefreshAssetPricesJob;
use App\Jobs\StreakReminderJob;
use Illuminate\Support\Facades\Artisan;

/**
 * Which jobs the scheduler actually runs.
 *
 * Driven through `schedule:list` rather than by reading the Schedule singleton:
 * the `withSchedule()` callback in bootstrap/app.php only applies once the
 * console kernel boots, so inspecting the container directly passes or fails
 * depending on whether an earlier test happened to run an artisan command.
 */
function scheduleListing(): string
{
    Artisan::call('schedule:list');

    return Artisan::output();
}

test('every background job is registered with the scheduler', function () {
    $listing = scheduleListing();

    $missing = collect([
        RefreshAssetPricesJob::class,
        BillReminderJob::class,
        StreakReminderJob::class,
        CaptureDailyStatsJob::class,
    ])->reject(fn (string $job): bool => str_contains($listing, $job))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

test('the streak nudge is checked often enough to catch every timezone', function () {
    // The job only acts inside the first quarter of the reminder hour in the
    // user's own zone, so a coarser cadence would silently skip whole timezones.
    expect(scheduleListing())->toMatch(
        '/\*\/15 \* \* \* \*\s+'.preg_quote(StreakReminderJob::class, '/').'/',
    );
});
