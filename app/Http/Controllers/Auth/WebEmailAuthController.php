<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\EmailAuthBroker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CodeVerificationRequest;
use App\Http\Requests\Auth\CompleteSignupRequest;
use App\Http\Requests\Auth\PasswordLoginRequest;
use App\Http\Requests\Auth\StartEmailAuthRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class WebEmailAuthController extends Controller
{
    public function __construct(private readonly EmailAuthBroker $broker) {}

    public function start(StartEmailAuthRequest $request): RedirectResponse
    {
        $flow = $this->broker->start(
            $request->string('email')->toString(),
            $request->ip(),
        );

        $request->session()->put('auth_flow', $flow);

        return back();
    }

    public function login(PasswordLoginRequest $request): RedirectResponse
    {
        $user = $this->broker->validatePasswordLogin(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        $request->session()->forget('auth_flow');

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function sendRecovery(StartEmailAuthRequest $request): RedirectResponse
    {
        $email = $request->string('email')->toString();

        $this->broker->sendRecoveryChallenge($email, $request->ip());

        $request->session()->put('auth_flow', [
            'email' => $this->broker->normalizeEmail($email),
            'status' => 'recovery_code_sent',
            'next_step' => 'recovery_code',
        ]);

        return back();
    }

    public function verifyRecovery(CodeVerificationRequest $request): RedirectResponse
    {
        $user = $this->broker->verifyRecoveryCode(
            $request->string('email')->toString(),
            $request->string('code')->toString(),
        );

        $request->session()->forget('auth_flow');

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function verifySignup(CodeVerificationRequest $request): RedirectResponse
    {
        $flow = $this->broker->verifySignupCode(
            $request->string('email')->toString(),
            $request->string('code')->toString(),
        );

        $request->session()->put('auth_flow', [
            ...$flow,
            'status' => 'signup_verified',
            'next_step' => 'complete_signup',
        ]);

        return back();
    }

    public function completeSignup(CompleteSignupRequest $request): RedirectResponse
    {
        $user = $this->broker->completeSignup(
            (string) $request->session()->get(
                'auth_flow.signup_token',
                $request->string('signup_token')->toString(),
            ),
            $request->safe()->only(['name', 'birthdate', 'password']),
            (string) $request->session()->get(
                'auth_flow.email',
                $request->string('email')->toString(),
            ),
        );

        $request->session()->forget('auth_flow');

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
