<?php

namespace App\Jobs;

use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued primitive for sending a Telegram notification outside the live
 * webhook conversation (e.g. alerts triggered by other parts of the app).
 * The interactive bot chat handled by TelegramHandler stays synchronous —
 * it's a real-time conversation, not a notification.
 */
class SendTelegramMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public readonly string $chatId,
        public readonly string $message,
    ) {
        $this->onQueue('telegram');
    }

    public function handle(): void
    {
        $chat = TelegraphChat::query()->where('chat_id', $this->chatId)->first();

        if (! $chat instanceof TelegraphChat) {
            return;
        }

        $chat->html($this->message)->send();
    }
}
