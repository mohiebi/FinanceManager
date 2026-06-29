<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Resend\Laravel\Facades\Resend;

class ResendChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toResend')) {
            return;
        }

        ['to' => $to, 'subject' => $subject, 'html' => $html] = $notification->toResend($notifiable);

        Resend::emails()->send([
            'from'    => config('mail.from.name') . ' <' . config('mail.from.address') . '>',
            'to'      => [$to],
            'subject' => $subject,
            'html'    => $html,
        ]);
    }
}
