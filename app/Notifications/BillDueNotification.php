<?php

namespace App\Notifications;

use App\Models\Bill;
use App\Models\BillOccurrence;
use App\Notifications\Channels\TelegramChannel;
use App\Support\DateFormatter;
use App\Support\Encryption\EncryptedValue;
use App\Support\FrontendLocalization;
use Illuminate\Notifications\Notification;

class BillDueNotification extends Notification
{
    public function __construct(
        public readonly Bill $bill,
        public readonly BillOccurrence $occurrence,
        public readonly string $reason,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', TelegramChannel::class];
    }

    /**
     * The notification text is pre-rendered in the recipient's locale at send
     * time (Laravel's :param syntax), rather than re-translated client-side —
     * avoids a placeholder-syntax mismatch with vue-i18n's {param} style and
     * keeps historical notifications readable even if the user later changes
     * their locale.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $locale = FrontendLocalization::normalizeLocale($notifiable->locale ?? null);
        $key = $this->typeKey();

        return [
            'type' => $key,
            'bill_id' => $this->bill->id,
            'title' => trans("notifications.{$key}.title", [], $locale),
            // Deliberately the generic body. `notifications.data` is not encrypted,
            // so repeating the bill's title or amount here would put in cleartext
            // exactly what the bills table encrypts. The bill_id is enough for the
            // UI to link through to the real figures.
            'body' => trans("notifications.{$key}.body_generic", [
                'date' => $this->displayDate($notifiable),
            ], $locale),
            'due_date' => $this->occurrence->due_date->toDateString(),
        ];
    }

    /**
     * Rendered live and handed straight to Telegram — never stored by us, so it
     * can carry the real title and amount.
     */
    public function toTelegram(object $notifiable): ?string
    {
        if (! $this->bill->telegram_reminder_enabled) {
            return null;
        }

        // Unreachable while the vault conflicts with the Telegram module, but a
        // reminder that renders "•••" as an amount would be worse than none.
        if ($this->bill->amount instanceof EncryptedValue || $this->bill->title instanceof EncryptedValue) {
            return null;
        }

        return trans(
            "notifications.{$this->typeKey()}.body",
            [
                'title' => $this->bill->title,
                'date' => $this->displayDate($notifiable),
                'amount' => number_format((float) $this->bill->amount),
                'currency' => strtoupper($this->bill->currency),
            ],
            FrontendLocalization::normalizeLocale($notifiable->locale ?? null),
        );
    }

    private function displayDate(object $notifiable): string
    {
        return DateFormatter::format(
            $this->occurrence->due_date,
            FrontendLocalization::normalizeCalendar($notifiable->calendar ?? null),
            'Y-m-d',
        );
    }

    private function typeKey(): string
    {
        return $this->reason === 'day_before' ? 'bill_due_tomorrow' : 'bill_due_today';
    }
}
