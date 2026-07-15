<?php

namespace App\Jobs;

use App\Actions\Admin\BuildAdminAnalytics;
use App\Models\DailyStat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class CaptureDailyStatsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    /**
     * Persist one snapshot of the customer metrics per day so the admin
     * dashboard can chart trends and period-over-period deltas from real
     * historical values instead of approximating them from current state.
     */
    public function handle(BuildAdminAnalytics $analytics): void
    {
        // Pass a Carbon instance (not a Y-m-d string) so the match query and the
        // stored value serialize identically across SQLite and MySQL.
        DailyStat::query()->updateOrCreate(
            ['date' => Carbon::today()],
            $analytics->snapshot(),
        );
    }
}
