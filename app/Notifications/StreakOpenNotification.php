<?php

namespace App\Notifications;

use App\Notifications\Channels\TelegramChannel;
use App\Support\FrontendLocalization;
use Illuminate\Notifications\Notification;

/**
 * The evening reminder that today has not been logged yet.
 *
 * Carries a run length and nothing else. Unlike the bill reminders there is no
 * generic-versus-full split to make here — a streak has no amount or title to
 * withhold, which is the same property that lets it work under the vault.
 */
class StreakOpenNotification extends Notification
{
    public function __construct(public readonly int $currentRun) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', TelegramChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'streak_open',
            'title' => trans('notifications.streak_open.title', [], $this->localeFor($notifiable)),
            'body' => $this->body($notifiable),
            'current_run' => $this->currentRun,
        ];
    }

    public function toTelegram(object $notifiable): ?string
    {
        return $this->body($notifiable);
    }

    /**
     * A user with no run yet is invited to start one; telling them "day 0 is
     * still open" would be nonsense.
     */
    private function body(object $notifiable): string
    {
        $locale = $this->localeFor($notifiable);

        return $this->currentRun > 0
            ? trans('notifications.streak_open.body', ['days' => $this->currentRun + 1], $locale)
            : trans('notifications.streak_open.body_first', [], $locale);
    }

    /**
     * Resolved per recipient at send time. Queued and webhook code has no
     * ambient locale — `SetUserPreferences` only runs on the web middleware group.
     */
    private function localeFor(object $notifiable): string
    {
        return FrontendLocalization::normalizeLocale($notifiable->locale ?? null);
    }
}
