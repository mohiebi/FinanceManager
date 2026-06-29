<?php

namespace App\Notifications;

use App\Mail\Templates\AuthCodeEmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Resend\Laravel\Facades\Resend;

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
        return ['mail'];
    }

    public function toMail(object $notifiable): void
    {
        $isRecovery = $this->purpose === 'recovery';
        $subject    = $isRecovery ? 'کد بازیابی ورود شما' : 'تأیید آدرس ایمیل';

        $routed = $notifiable->routeNotificationFor('mail', $this);
        $to     = is_string($routed) ? $routed : (string) ($notifiable->email ?? '');

        Resend::emails()->send([
            'from'    => config('mail.from.name') . ' <' . config('mail.from.address') . '>',
            'to'      => [$to],
            'subject' => $subject,
            'html'    => AuthCodeEmailTemplate::render($this->code, $this->purpose),
        ]);
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
