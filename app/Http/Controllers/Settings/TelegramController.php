<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TelegramController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Telegram', [
            'connected' => $user->hasTelegram(),
            'telegramChatId' => $user->telegram_chat_id,
            'connectToken' => $user->telegram_connect_token,
            'botUsername' => config('telegraph.bot_username'),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        $token = Str::random(32);

        $request->user()->update(['telegram_connect_token' => $token]);

        return to_route('telegram.edit');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $request->user()->update([
            'telegram_chat_id' => null,
            'telegram_connect_token' => null,
            'streak_nudge_enabled' => false,
        ]);

        return back();
    }
}
