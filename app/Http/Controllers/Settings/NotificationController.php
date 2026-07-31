<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Feature;
use App\Http\Controllers\Controller;
use App\Jobs\StreakReminderJob;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function edit(Request $request): Response
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                'data' => $notification->data,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at->toIso8601String(),
            ]);

        return Inertia::render('settings/Notifications', [
            'notifications' => $notifications,
            'streakNudge' => [
                'enabled' => (bool) $request->user()->streak_nudge_enabled,
                // The nudge is delivered over Telegram, so without a linked chat
                // the switch has nowhere to send and says so rather than lying.
                'available' => $request->user()->hasFeature(Feature::Gamification)
                    && $request->user()->hasTelegram(),
                'hour' => StreakReminderJob::REMINDER_HOUR,
            ],
        ]);
    }

    /**
     * Opt in or out of the nightly streak reminder.
     */
    public function updatePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'streak_nudge_enabled' => ['required', 'boolean'],
        ]);

        // The page disables the switch when the module is off, but a stale tab or
        // a direct request would otherwise store a preference that contradicts
        // what the settings page shows.
        abort_unless($request->user()->hasFeature(Feature::Gamification), 403);

        $request->user()->update($validated);

        return back()->with('status', __('notifications.preferences_saved'));
    }

    public function markRead(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless(
            $notification->notifiable_type === User::class
                && (string) $notification->notifiable_id === (string) $request->user()->id,
            404,
        );

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
