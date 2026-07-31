<?php

namespace App\Actions\Gamification;

use App\Models\NoSpendDay;
use App\Models\User;
use Carbon\CarbonImmutable;

class RecordNoSpendDay
{
    /**
     * Mark a day as deliberately spend-free.
     *
     * Idempotent, because the button is reachable from both the dashboard and
     * the Telegram keyboard and a double tap must not be an error. Returns null
     * when the day already has a transaction on it — there is nothing to assert,
     * and writing the marker anyway would claim the user spent nothing on a day
     * they recorded spending.
     */
    public function handle(User $user, ?CarbonImmutable $date = null): ?NoSpendDay
    {
        $date = ($date ?? $user->localToday())->toDateString();

        if ($user->transactions()->where('occurred_at', $date)->exists()) {
            return null;
        }

        return $user->noSpendDays()->firstOrCreate(['date' => $date]);
    }
}
