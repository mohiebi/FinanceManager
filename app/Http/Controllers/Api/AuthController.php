<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\EmailAuthBroker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CodeVerificationRequest;
use App\Http\Requests\Auth\CompleteSignupRequest;
use App\Http\Requests\Auth\PasswordLoginRequest;
use App\Http\Requests\Auth\StartEmailAuthRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly EmailAuthBroker $broker) {}

    public function start(StartEmailAuthRequest $request): JsonResponse
    {
        return response()->json($this->broker->start(
            $request->string('email')->toString(),
            $request->ip(),
        ));
    }

    public function passwordLogin(PasswordLoginRequest $request): JsonResponse
    {
        $user = $this->broker->validatePasswordLogin(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return $this->authenticatedResponse($user, $request->string('device_name')->toString());
    }

    public function sendRecovery(StartEmailAuthRequest $request): JsonResponse
    {
        $this->broker->sendRecoveryChallenge(
            $request->string('email')->toString(),
            $request->ip(),
        );

        return response()->json(['message' => __('auth.login_code_sent')]);
    }

    public function verifyRecovery(CodeVerificationRequest $request): JsonResponse
    {
        $user = $this->broker->verifyRecoveryCode(
            $request->string('email')->toString(),
            $request->string('code')->toString(),
        );

        return $this->authenticatedResponse($user, $request->string('device_name')->toString());
    }

    public function verifySignup(CodeVerificationRequest $request): JsonResponse
    {
        return response()->json($this->broker->verifySignupCode(
            $request->string('email')->toString(),
            $request->string('code')->toString(),
        ));
    }

    public function completeSignup(CompleteSignupRequest $request): JsonResponse
    {
        $user = $this->broker->completeSignup(
            $request->string('signup_token')->toString(),
            $request->safe()->only(['name', 'birthdate', 'password']),
        );

        return $this->authenticatedResponse($user, $request->string('device_name')->toString());
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        return response()->json([
            'user' => $user,
            'has_password' => $user?->hasPassword() ?? false,
            'requires_profile_completion' => $user?->requiresProfileCompletion() ?? false,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => __('auth.logged_out')]);
    }

    protected function authenticatedResponse(User $user, string $deviceName): JsonResponse
    {
        return response()->json([
            'user' => $user,
            'token' => $this->broker->issueToken($user, $deviceName !== '' ? $deviceName : null),
            'has_password' => $user->hasPassword(),
            'requires_profile_completion' => $user->requiresProfileCompletion(),
            'next_step' => $user->requiresProfileCompletion() ? 'complete_profile' : null,
        ]);
    }
}
