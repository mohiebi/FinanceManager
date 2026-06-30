<?php

namespace App\Notifications;

use App\Models\Bill;
use App\Models\BillOccurrence;
use App\Notifications\Channels\TelegramChannel;
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
        $locale = $notifiable->locale ?? 'en';
        $key = $this->typeKey();
        $params = [
            'title' => $this->bill->title,
            'date' => $this->occurrence->due_date->toDateString(),
            'amount' => number_format((float) $this->bill->amount),
            'currency' => strtoupper($this->bill->currency),
        ];

        return [
            'type' => $key,
            'bill_id' => $this->bill->id,
            'title' => trans("notifications.{$key}.title", [], $locale),
            'body' => trans("notifications.{$key}.body", $params, $locale),
            'due_date' => $this->occurrence->due_date->toDateString(),
        ];
    }

    public function toTelegram(object $notifiable): ?string
    {
        if (! $this->bill->telegram_reminder_enabled) {
            return null;
        }

        return trans(
            "notifications.{$this->typeKey()}.body",
            [
                'title' => $this->bill->title,
                'date' => $this->occurrence->due_date->toDateString(),
                'amount' => number_format((float) $this->bill->amount),
                'currency' => strtoupper($this->bill->currency),
            ],
            $notifiable->locale ?? 'en',
        );
    }

    private function typeKey(): string
    {
        return $this->reason === 'day_before' ? 'bill_due_tomorrow' : 'bill_due_today';
    }
}
