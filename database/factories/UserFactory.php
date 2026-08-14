<?php

namespace Database\Factories;

use App\Actions\Features\UpdateUserFeature;
use App\Enums\Feature;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'birthdate' => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
            'locale' => 'en',
            'calendar' => 'gregorian',
            'timezone' => 'UTC',
            'streak_nudge_enabled' => false,
            'bill_advance_reminder_enabled' => true,
            // Declared even though it is null: a DB default is not present on a
            // freshly-created instance, so strict mode throws the moment
            // anything reads it in the same request — which the notifications
            // page now does via hasTelegram().
            'telegram_chat_id' => null,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Switch feature modules on for the user.
     *
     * Modules are off by default (see App\Enums\Feature), so any test exercising
     * bills, investments or portfolio needs this. Goes through UpdateUserFeature
     * so dependencies resolve — asking for Portfolio also enables Investments.
     * Pass no arguments to enable every toggleable module.
     */
    public function withModules(Feature ...$features): static
    {
        $features = $features === [] ? Feature::toggleable() : $features;

        return $this->afterCreating(function (User $user) use ($features): void {
            foreach ($features as $feature) {
                app(UpdateUserFeature::class)($user, $feature, true);
            }
        });
    }

    /**
     * Switch feature modules off for the user.
     *
     * Most modules ship off, so tests get that for free by omitting
     * {@see self::withModules()}. The flight log does not — it has no page to be
     * discovered from, so it defaults on — and anything asserting how the app
     * behaves without it has to say so.
     */
    public function withoutModules(Feature ...$features): static
    {
        return $this->afterCreating(function (User $user) use ($features): void {
            foreach ($features as $feature) {
                app(UpdateUserFeature::class)($user, $feature, false);
            }
        });
    }

    /**
     * Give the user a live Pro entitlement.
     *
     * Sets the column directly rather than going through GrantProAccess, so a test
     * that only needs a Pro user does not also acquire a grant row it never asserts
     * on. Tests covering the entitlement audit trail should call the action.
     */
    public function pro(?string $until = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'pro_until' => $until ?? now()->addMonthsNoOverflow(1),
        ]);
    }

    /**
     * A user whose Pro entitlement has lapsed.
     *
     * Distinct from a user who never paid: pro_until stays populated, which is what
     * the renewal path and the expired notice both key off.
     */
    public function proExpired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'pro_until' => now()->subDays(3),
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the model should not have a password.
     */
    public function passwordless(): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null,
            'remember_token' => null,
        ]);
    }
}
