<?php

use App\Http\Middleware\CaptureAcquisitionSource;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetUserPreferences;
use App\Http\Middleware\TrackUserActivity;
use App\Jobs\BillReminderJob;
use App\Jobs\CaptureDailyStatsJob;
use App\Jobs\RefreshAssetPricesJob;
use App\Models\McpProposal;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->statefulApi();
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            SetUserPreferences::class,
            CaptureAcquisitionSource::class,
            TrackUserActivity::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Refreshes live asset prices every hour regardless of site traffic or
        // manual syncs, so prices never go stale just because nobody visited a page.
        $schedule->job(new RefreshAssetPricesJob)->hourly();

        // Generates upcoming bill occurrences and sends day-before/due-day reminders.
        // withoutOverlapping guards against a stuck/delayed queue worker letting
        // two runs stack and double-send reminders.
        $schedule->job(new BillReminderJob)->dailyAt('09:00')->withoutOverlapping();

        // Captures one row of customer metrics per day so the admin dashboard
        // can chart engagement trends and period-over-period deltas from real
        // history. updateOrCreate keeps reruns on the same day idempotent.
        $schedule->job(new CaptureDailyStatsJob)->dailyAt('00:10');

        // Prunes resolved/expired MCP proposals older than the 90-day audit window.
        $schedule->command('model:prune', ['--model' => [McpProposal::class]])->daily();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
