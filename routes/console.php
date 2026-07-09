<?php

use App\Jobs\BillReminderJob;
use App\Jobs\RefreshAssetPricesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new RefreshAssetPricesJob)->hourly();
Schedule::job(new BillReminderJob)->dailyAt('07:00');
