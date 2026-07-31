<?php

namespace App\Jobs;

use App\Enums\Feature;
use App\Models\User;
use App\Notifications\StreakOpenNotification;
use App\Support\StreakCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Nudges users whose run is still open late in their own evening.
 *
 * The nudge is the point of the whole feature: an in-app badge only reaches
 * people who already opened the app, which is the behaviour being encouraged.
 */
class StreakReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    /** Local hour the nudge goes out. Late enough to be a last call, early enough to act on. */
    public const REMINDER_HOUR = 21;

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    public function handle(StreakCalculator $calculator): void
    {
        User::query()
            ->whereFeatureEnabled(Feature::Gamification)
            // The nudge is delivered over Telegram, so an unlinked account has
            // nowhere to receive it and is skipped before any streak is computed.
            ->where('streak_nudge_enabled', true)
            ->whereNotNull('telegram_chat_id')
            ->with('streak')
            ->chunkById(100, function ($users) use ($calculator): void {
                foreach ($users as $user) {
                    $this->nudge($user, $calculator);
                }
            });
    }

    private function nudge(User $user, StreakCalculator $calculator): void
    {
        try {
            $now = Carbon::now($user->resolvedTimezone());
        } catch (Throwable) {
            return;
        }

        // The schedule runs every 15 minutes; match the bucket holding the hour.
        if ($now->hour !== self::REMINDER_HOUR || (int) floor($now->minute / 15) !== 0) {
            return;
        }

        $today = $now->toDateString();

        // A dated column rather than a cache lock, mirroring the bill reminders:
        // it survives a cache flush, and it is the same date the user sees.
        if ($user->streak?->last_nudged_on?->toDateString() === $today) {
            return;
        }

        $streak = $calculator->for($user);

        if ($streak->loggedToday) {
            return;
        }

        $user->notify(new StreakOpenNotification($streak->currentRun));

        // `for()` has just created the record and set it on the relation, so this
        // reuses that instance rather than issuing another select for it.
        $user->streak?->forceFill(['last_nudged_on' => $today])->save();
    }
}
