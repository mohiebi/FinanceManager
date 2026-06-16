<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\GoogleAuthBroker;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WebGoogleAuthController extends Controller
{
    public function __construct(private readonly GoogleAuthBroker $broker) {}

    public function redirect(): RedirectResponse
    {
        return $this->broker->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $user = $this->broker->userFromCallback();
        } catch (ValidationException $exception) {
            // A duplicate/late callback request (e.g. a double-click on "Sign in
            // with Google") reuses an already-consumed authorization code and
            // fails here, but the original request may have already
            // authenticated this session. Don't show an error in that case.
            if (Auth::check()) {
                return redirect()->intended(route('dashboard', absolute: false));
            }

            return to_route('login')->withErrors([
                'google' => $exception->errors()['token'][0] ?? 'We could not sign you in with Google.',
            ]);
        } catch (\Throwable $exception) {
            if (Auth::check()) {
                return redirect()->intended(route('dashboard', absolute: false));
            }

            Log::warning('Google sign-in failed', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return to_route('login')->withErrors([
                'google' => 'We could not sign you in with Google.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        if ($user->requiresProfileCompletion()) {
            return to_route('profile.edit')->with('status', 'Complete your profile to finish setting up your account.');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
