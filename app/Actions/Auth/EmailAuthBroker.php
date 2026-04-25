<?php

namespace App\Actions\Auth;

use App\Models\AuthChallenge;
use App\Models\User;
use App\Notifications\AuthChallengeCodeNotification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailAuthBroker
{
    public const MaxAttempts = 5;

    /**
     * @return array{email: string, status: string, next_step: string}
     */
    public function start(string $email, ?string $ipAddress = null): array
    {
        $email = $this->normalizeEmail($email);

        if (User::query()->where('email', $email)->exists()) {
            return [
                'email' => $email,
                'status' => 'existing_user',
                'next_step' => 'password',
            ];
        }

        $this->sendChallenge($email, AuthChallenge::PurposeSignup, $ipAddress);

        return [
            'email' => $email,
            'status' => 'new_user',
            'next_step' => 'signup_code',
        ];
    }

    public function sendRecoveryChallenge(string $email, ?string $ipAddress = null): AuthChallenge
    {
        $email = $this->normalizeEmail($email);

        if (! User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => __('We could not find an account with that email address.'),
            ]);
        }

        return $this->sendChallenge($email, AuthChallenge::PurposeRecovery, $ipAddress);
    }

    public function sendChallenge(string $email, string $purpose, ?string $ipAddress = null): AuthChallenge
    {
        $email = $this->normalizeEmail($email);
        $code = (string) random_int(100000, 999999);

        AuthChallenge::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $challenge = AuthChallenge::query()->create([
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
            'last_sent_at' => now(),
            'ip_address' => $ipAddress,
        ]);

        Notification::route('mail', $email)
            ->notify(new AuthChallengeCodeNotification($code, $purpose));

        return $challenge;
    }

    /**
     * @return array{email: string, signup_token: string}
     */
    public function verifySignupCode(string $email, string $code): array
    {
        $challenge = $this->verifyCode($email, $code, AuthChallenge::PurposeSignup);

        return [
            'email' => $challenge->email,
            'signup_token' => Crypt::encryptString((string) $challenge->id),
        ];
    }

    public function verifyRecoveryCode(string $email, string $code): User
    {
        $challenge = $this->verifyCode($email, $code, AuthChallenge::PurposeRecovery);
        $challenge->forceFill(['consumed_at' => now()])->save();

        $user = User::query()->where('email', $challenge->email)->first();

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'email' => __('We could not find an account with that email address.'),
            ]);
        }

        return $user;
    }

    /**
     * @param  array{name: string, birthdate: string, password: string}  $attributes
     */
    public function completeSignup(string $signupToken, array $attributes): User
    {
        $challenge = $this->challengeFromSignupToken($signupToken);

        if (User::query()->where('email', $challenge->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => __('An account already exists for this email address.'),
            ]);
        }

        $user = User::query()->create([
            'name' => $attributes['name'],
            'email' => $challenge->email,
            'birthdate' => $attributes['birthdate'],
            'password' => $attributes['password'],
            'email_verified_at' => now(),
        ]);

        $challenge->forceFill(['consumed_at' => now()])->save();

        return $user;
    }

    public function validatePasswordLogin(string $email, string $password): User
    {
        $email = $this->normalizeEmail($email);
        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        return $user;
    }

    public function issueToken(Authenticatable $user, ?string $deviceName): ?string
    {
        if (! $deviceName || ! method_exists($user, 'createToken')) {
            return null;
        }

        return $user->createToken($deviceName)->plainTextToken;
    }

    public function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    protected function verifyCode(string $email, string $code, string $purpose): AuthChallenge
    {
        $challenge = AuthChallenge::query()
            ->where('email', $this->normalizeEmail($email))
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $challenge instanceof AuthChallenge) {
            throw ValidationException::withMessages([
                'code' => __('Request a new code before continuing.'),
            ]);
        }

        if ($challenge->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'code' => __('This code has expired.'),
            ]);
        }

        if ($challenge->attempts >= self::MaxAttempts) {
            throw ValidationException::withMessages([
                'code' => __('Too many attempts. Request a new code.'),
            ]);
        }

        if (! Hash::check($code, $challenge->code_hash)) {
            $challenge->increment('attempts');

            throw ValidationException::withMessages([
                'code' => __('The code is invalid.'),
            ]);
        }

        $challenge->forceFill(['verified_at' => now()])->save();

        return $challenge;
    }

    protected function challengeFromSignupToken(string $signupToken): AuthChallenge
    {
        try {
            $challengeId = Crypt::decryptString($signupToken);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'signup_token' => __('Verify your email before completing signup.'),
            ]);
        }

        $challenge = AuthChallenge::query()
            ->whereKey($challengeId)
            ->where('purpose', AuthChallenge::PurposeSignup)
            ->whereNotNull('verified_at')
            ->whereNull('consumed_at')
            ->first();

        if (! $challenge instanceof AuthChallenge || $challenge->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'signup_token' => __('Verify your email before completing signup.'),
            ]);
        }

        return $challenge;
    }
}
