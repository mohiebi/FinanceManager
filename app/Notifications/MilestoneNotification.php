<?php

namespace App\Notifications;

use App\Enums\Milestone;
use App\Support\FrontendLocalization;
use Illuminate\Notifications\Notification;

/**
 * A moment reached, announced once.
 *
 * The copy says what changed in the product — "your reports now cover a full
 * quarter" — rather than "well done". A milestone that only congratulates is
 * decoration; one that tells the user something became trustworthy is a reason
 * to go and look.
 */
class MilestoneNotification extends Notification
{
    public function __construct(public readonly Milestone $milestone) {}

    /**
     * Database only, deliberately.
     *
     * The one Telegram switch a user has is the streak reminder; sending
     * milestones over it too would mean someone who turned that off — or who
     * linked Telegram purely for bill reminders — still receives gamification
     * messages they never asked for. The bell is the right home for a moment
     * that fires once.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $locale = $this->localeFor($notifiable);
        $key = $this->milestone->value;

        return [
            'type' => 'milestone',
            'milestone' => $key,
            'title' => trans("notifications.milestones.{$key}.title", [], $locale),
            // Safe to store verbatim: a milestone carries no amount and no title,
            // so there is nothing here that the transactions table encrypts.
            'body' => trans("notifications.milestones.{$key}.body", [], $locale),
        ];
    }

    /**
     * Resolved per recipient at send time: queued and observer code has no
     * ambient locale, since SetUserPreferences only runs on the web group.
     */
    private function localeFor(object $notifiable): string
    {
        return FrontendLocalization::normalizeLocale($notifiable->locale ?? null);
    }
}
