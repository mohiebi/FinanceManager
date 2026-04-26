<?php

namespace App\Actions\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthBroker
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver(SocialAccount::ProviderGoogle)
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function userFromCallback(): User
    {
        /** @var SocialiteUser $googleUser */
        $googleUser = Socialite::driver(SocialAccount::ProviderGoogle)->user();

        return $this->resolveUser($googleUser);
    }

    public function userFromToken(string $token): User
    {
        try {
            /** @var SocialiteUser $googleUser */
            $googleUser = Socialite::driver(SocialAccount::ProviderGoogle)->userFromToken($token);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'token' => 'The Google token is invalid or has expired.',
            ]);
        }

        return $this->resolveUser($googleUser);
    }

    protected function resolveUser(SocialiteUser $googleUser): User
    {
        $providerUserId = trim((string) $googleUser->getId());
        $email = $this->verifiedEmail($googleUser);

        if ($providerUserId === '') {
            throw ValidationException::withMessages([
                'token' => 'Google did not return a valid account identifier.',
            ]);
        }

        return DB::transaction(function () use ($email, $googleUser, $providerUserId): User {
            $socialAccount = SocialAccount::query()
                ->with('user')
                ->where('provider', SocialAccount::ProviderGoogle)
                ->where('provider_user_id', $providerUserId)
                ->first();

            $user = $socialAccount?->user;

            if (! $user instanceof User) {
                $user = User::query()->where('email', $email)->first();
            }

            if (! $user instanceof User) {
                $user = User::query()->create([
                    'name' => $this->resolveName($googleUser, $email),
                    'email' => $email,
                    'password' => null,
                    'email_verified_at' => now(),
                ]);
            } else {
                $user->forceFill([
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
            }

            if (! $socialAccount instanceof SocialAccount) {
                SocialAccount::query()->create([
                    'user_id' => $user->id,
                    'provider' => SocialAccount::ProviderGoogle,
                    'provider_user_id' => $providerUserId,
                    'provider_email' => $email,
                    'avatar' => $googleUser->getAvatar(),
                ]);
            } else {
                $socialAccount->forceFill([
                    'user_id' => $user->id,
                    'provider_email' => $email,
                    'avatar' => $googleUser->getAvatar(),
                ])->save();
            }

            return $user->refresh();
        });
    }

    protected function verifiedEmail(SocialiteUser $googleUser): string
    {
        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $rawUser = $googleUser->getRaw();
        $isVerified = filter_var(
            $rawUser['email_verified'] ?? $rawUser['verified_email'] ?? false,
            FILTER_VALIDATE_BOOL,
        );

        if ($email === '') {
            throw ValidationException::withMessages([
                'token' => 'Google did not return an email address for this account.',
            ]);
        }

        if (! $isVerified) {
            throw ValidationException::withMessages([
                'token' => 'Your Google account email must be verified before you can sign in.',
            ]);
        }

        return $email;
    }

    protected function resolveName(SocialiteUser $googleUser, string $email): string
    {
        $name = trim((string) $googleUser->getName());

        if ($name !== '') {
            return Str::limit($name, 255, '');
        }

        $fallbackName = Str::title(str_replace(['.', '_', '-'], ' ', Str::before($email, '@')));

        return Str::limit($fallbackName, 255, '');
    }
}
