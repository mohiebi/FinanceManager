<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
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
