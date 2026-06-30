<?php

namespace App\Notifications\Channels;

use App\Jobs\SendTelegramMessageJob;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Generic notification channel: delivers via Telegram if the notifiable has
 * a linked chat and the notification opts in with a toTelegram() method.
 */
class TelegramChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toTelegram')) {
            return;
        }

        if (! $notifiable instanceof User || ! $notifiable->hasTelegram()) {
            return;
        }

        $message = $notification->toTelegram($notifiable);

        if ($message === null || $message === '') {
            return;
        }

        SendTelegramMessageJob::dispatch($notifiable->telegram_chat_id, $message);
    }
}
