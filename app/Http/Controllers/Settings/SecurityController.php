<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Support\UserAgentSummary;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\ConfirmPassword;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'hasPassword' => $request->user()->hasPassword(),
            'needsPasswordConfirmation' => $this->needsPasswordConfirmation($request),
            'sessions' => $this->sessions($request),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $request->user()->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return Inertia::render('settings/Security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        return back();
    }

    /**
     * List this user's sign-in sessions, newest activity first. Reads the
     * `sessions` table directly (SESSION_DRIVER=database) rather than through
     * a model — there is no Eloquent model for it, and one row per browser is
     * all this needs.
     *
     * @return array<int, array{id: string, browser: string|null, platform: string|null, ip_address: string|null, last_active_at: string|null, is_current: bool}>
     */
    private function sessions(Request $request): array
    {
        $currentSessionId = $request->session()->getId();

        return DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function (object $session) use ($currentSessionId): array {
                $agent = UserAgentSummary::parse($session->user_agent);

                return [
                    'id' => $session->id,
                    'browser' => $agent['browser'],
                    'platform' => $agent['platform'],
                    'ip_address' => $session->ip_address,
                    'last_active_at' => Date::createFromTimestamp($session->last_activity)->toIso8601String(),
                    'is_current' => $session->id === $currentSessionId,
                ];
            })
            ->all();
    }

    /**
     * Sign a single other session out immediately. The current session can
     * only be ended by actually logging out — revoking it here would leave
     * the requester acting through a session record that no longer exists.
     */
    public function destroySession(Request $request, string $session): RedirectResponse
    {
        abort_if($session === $request->session()->getId(), 403);

        $deleted = DB::table('sessions')
            ->where('id', $session)
            ->where('user_id', $request->user()->id)
            ->delete();

        abort_if($deleted === 0, 404);

        return back();
    }

    /**
     * Confirm the user's password to unlock the security settings page.
     */
    public function confirmPassword(Request $request, StatefulGuard $guard, ConfirmPassword $confirmPassword): RedirectResponse
    {
        if (! $confirmPassword($guard, $request->user(), $request->input('password'))) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', Date::now()->unix());

        return back();
    }

    /**
     * Determine whether the user must reconfirm their password before
     * accessing the security settings page.
     */
    private function needsPasswordConfirmation(Request $request): bool
    {
        if (! Features::canManageTwoFactorAuthentication()
            || ! Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')
            || ! $request->user()->hasPassword()) {
            return false;
        }

        $confirmedAt = Date::now()->unix() - $request->session()->get('auth.password_confirmed_at', 0);

        return $confirmedAt > config('auth.password_timeout', 10800);
    }
}
