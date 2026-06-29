<?php

namespace App\Notifications;

use App\Mail\Templates\AuthCodeEmailTemplate;
use App\Notifications\Channels\ResendChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AuthChallengeCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly string $purpose,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [ResendChannel::class];
    }

    /**
     * @return array{to: string, subject: string, html: string}
     */
    public function toResend(object $notifiable): array
    {
        $isRecovery = $this->purpose === 'recovery';

        $routed = $notifiable->routeNotificationFor('mail', $this);
        $to     = is_string($routed) ? $routed : (string) ($notifiable->email ?? '');

        return [
            'to'      => $to,
            'subject' => $isRecovery ? 'کد بازیابی ورود شما' : 'تأیید آدرس ایمیل',
            'html'    => AuthCodeEmailTemplate::render($this->code, $this->purpose),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'purpose' => $this->purpose,
        ];
    }
}
