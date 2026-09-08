<?php

use App\Actions\Billing\GrantProAccess;
use App\Actions\Billing\RevokeProAccess;
use App\Enums\GrantReason;
use App\Jobs\BillReminderJob;
use App\Jobs\RefreshAssetPricesJob;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * The operator's manual entitlement controls.
 *
 * Deliberately available before any payment UI exists: Pro can be sold by hand
 * from day one, and these stay the escape hatch afterwards for refunds, comps
 * and payments the chain checks could not settle on their own.
 */
Artisan::command('billing:grant {email} {months} {--note=}', function (GrantProAccess $grantProAccess) {
    $email = (string) $this->argument('email');
    $months = (int) $this->argument('months');

    if ($months < 1) {
        $this->error('Months must be a positive whole number.');

        return 1;
    }

    $user = User::query()->where('email', $email)->first();

    if ($user === null) {
        $this->error("No user found with the email {$email}.");

        return 1;
    }

    $grant = $grantProAccess(
        user: $user,
        months: $months,
        reason: GrantReason::AdminGrant,
        note: $this->option('note') === null ? null : (string) $this->option('note'),
    );

    $this->info("Granted {$months} month(s) to {$email}. Pro until {$grant->pro_until_after->toDayDateTimeString()} UTC.");

    return 0;
})->purpose('Grant a user Pro access for a number of months');

Artisan::command('billing:revoke {email} {--note=}', function (RevokeProAccess $revokeProAccess) {
    $email = (string) $this->argument('email');

    $user = User::query()->where('email', $email)->first();

    if ($user === null) {
        $this->error("No user found with the email {$email}.");

        return 1;
    }

    $revokeProAccess(
        user: $user,
        note: $this->option('note') === null ? null : (string) $this->option('note'),
    );

    $this->info("Revoked Pro access for {$email}.");

    return 0;
})->purpose('End a user\'s Pro access immediately');

Schedule::job(new RefreshAssetPricesJob)->everyFiveMinutes();
Schedule::job(new BillReminderJob)->everyFifteenMinutes();

Schedule::call(function (): void {
    DB::table('transaction_imports')->where('expires_at', '<=', now())->delete();
})->name('transactions:prune-imports')->daily();
