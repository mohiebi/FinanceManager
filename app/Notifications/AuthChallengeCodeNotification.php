<?php

namespace App\Notifications;

use App\Mail\Templates\AuthCodeEmailTemplate;
use App\Notifications\Channels\ResendChannel;
use App\Support\FrontendLocalization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AuthChallengeCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $code,
        public readonly string $purpose,
        public readonly ?string $recipientLocale = null,
    ) {
        $this->onQueue('emails');
    }

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
        $routed = $notifiable->routeNotificationFor('mail', $this);
        $to = is_string($routed) ? $routed : (string) ($notifiable->email ?? '');
        $locale = FrontendLocalization::normalizeLocale($this->recipientLocale ?? ($notifiable->locale ?? null));

        return [
            'to' => $to,
            'subject' => AuthCodeEmailTemplate::subject($this->purpose, $locale),
            'html' => AuthCodeEmailTemplate::render($this->code, $this->purpose, $locale),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'purpose' => $this->purpose,
            'locale' => FrontendLocalization::normalizeLocale($this->recipientLocale),
        ];
    }
}
