<?php

namespace App\Notifications;

use App\Mail\Templates\SubscriptionEmailTemplate;
use App\Notifications\Channels\ResendChannel;
use App\Support\DateFormatter;
use App\Support\FrontendLocalization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Shared shape for the four things a subscription ever has to say.
 *
 * The important rule these all inherit concerns what may be *stored*. The
 * `notifications.data` column is not encrypted, so a transaction hash or a
 * sending address must never be written into it: that would create a permanent,
 * readable link between a CashPilot account and a wallet — something the chain
 * itself does not publish. Plan names, month counts and expiry dates are our
 * own invoice terms and are safe. Anything sharper is rendered live into the
 * email, which we never keep.
 */
abstract class SubscriptionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    /**
     * The `notifications.{key}` group this message reads its strings from.
     */
    abstract protected function typeKey(): string;

    /**
     * Placeholders for the stored body and the email body.
     *
     * @return array<string, string>
     */
    abstract protected function replacements(object $notifiable): array;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', ResendChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $locale = $this->localeFor($notifiable);
        $key = $this->typeKey();

        return [
            'type' => $key,
            'title' => trans("notifications.{$key}.title", [], $locale),
            'body' => trans("notifications.{$key}.body_generic", $this->replacements($notifiable), $locale),
        ];
    }

    /**
     * @return array{to: string, subject: string, html: string}
     */
    public function toResend(object $notifiable): array
    {
        $locale = $this->localeFor($notifiable);
        $key = $this->typeKey();

        return [
            'to' => $notifiable->email,
            'subject' => trans("notifications.{$key}.title", [], $locale),
            'html' => SubscriptionEmailTemplate::render(
                headline: trans("notifications.{$key}.title", [], $locale),
                body: trans("notifications.{$key}.email_body", $this->replacements($notifiable), $locale),
                locale: $locale,
                action: trans('notifications.subscription_action', [], $locale),
            ),
        ];
    }

    /**
     * Rendered in the recipient's own calendar, so a Jalali user is never shown
     * a Gregorian date for something they have to act on.
     */
    protected function displayDate(object $notifiable, mixed $date): string
    {
        return DateFormatter::format(
            $date,
            FrontendLocalization::normalizeCalendar($notifiable->calendar ?? null),
            'Y-m-d',
        );
    }

    protected function localeFor(object $notifiable): string
    {
        return FrontendLocalization::normalizeLocale($notifiable->locale ?? null);
    }
}
