<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\EmailAuthBroker;
use App\Actions\Auth\GoogleAuthBroker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GoogleLoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class GoogleAuthController extends Controller
{
    public function __construct(
        private readonly GoogleAuthBroker $googleAuthBroker,
        private readonly EmailAuthBroker $emailAuthBroker,
    ) {}

    public function login(GoogleLoginRequest $request): JsonResponse
    {
        $user = $this->googleAuthBroker->userFromToken(
            $request->string('token')->toString(),
        );

        return response()->json($this->authenticatedPayload(
            $user,
            $request->string('device_name')->toString(),
        ));
    }

    /**
     * @return array{user: User, token: ?string, has_password: bool, requires_profile_completion: bool, next_step: ?string}
     */
    protected function authenticatedPayload(User $user, string $deviceName): array
    {
        return [
            'user' => $user,
            'token' => $this->emailAuthBroker->issueToken($user, $deviceName !== '' ? $deviceName : null),
            'has_password' => $user->hasPassword(),
            'requires_profile_completion' => $user->requiresProfileCompletion(),
            'next_step' => $user->requiresProfileCompletion() ? 'complete_profile' : null,
        ];
    }
}
