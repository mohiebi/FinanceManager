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
        return back()->with('auth_flow', $this->broker->start(
            $request->string('email')->toString(),
            $request->ip(),
        ));
    }

    public function login(PasswordLoginRequest $request): RedirectResponse
    {
        $user = $this->broker->validatePasswordLogin(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function sendRecovery(StartEmailAuthRequest $request): RedirectResponse
    {
        $email = $request->string('email')->toString();

        $this->broker->sendRecoveryChallenge($email, $request->ip());

        return back()->with('auth_flow', [
            'email' => $this->broker->normalizeEmail($email),
            'status' => 'recovery_code_sent',
            'next_step' => 'recovery_code',
        ]);
    }

    public function verifyRecovery(CodeVerificationRequest $request): RedirectResponse
    {
        $user = $this->broker->verifyRecoveryCode(
            $request->string('email')->toString(),
            $request->string('code')->toString(),
        );

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

        return back()->with('auth_flow', [
            ...$flow,
            'status' => 'signup_verified',
            'next_step' => 'complete_signup',
        ]);
    }

    public function completeSignup(CompleteSignupRequest $request): RedirectResponse
    {
        $user = $this->broker->completeSignup(
            $request->string('signup_token')->toString(),
            $request->safe()->only(['name', 'birthdate', 'password']),
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
