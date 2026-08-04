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
        $user = $request->user();

        return Inertia::render('settings/Notifications', [
            'notifications' => fn () => $user
                ->notifications()
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (DatabaseNotification $notification) => [
                    'id' => $notification->id,
                    'data' => $notification->data,
                    'read_at' => $notification->read_at?->toIso8601String(),
                    'created_at' => $notification->created_at->toIso8601String(),
                ]),
            'streakNudge' => fn () => [
                'enabled' => (bool) $user->streak_nudge_enabled,
                'available' => $this->streakNudgeAvailable($user),
                'telegramLinked' => $user->hasTelegram(),
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

        if ($validated['streak_nudge_enabled']) {
            // Enabling must obey the same contract as the page. Disabling stays
            // available so a stale preference can always be corrected safely.
            abort_unless($this->streakNudgeAvailable($request->user()), 403);
        }

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

    private function streakNudgeAvailable(User $user): bool
    {
        return $user->hasFeature(Feature::Gamification) && $user->hasTelegram();
    }
}
