<?php

namespace Database\Factories;

use App\Enums\GrantReason;
use App\Models\SubscriptionGrant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionGrant>
 */
class SubscriptionGrantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $months = fake()->randomElement([1, 3, 12]);

        return [
            'user_id' => User::factory(),
            'subscription_payment_id' => null,
            'granted_by_admin_id' => null,
            'months' => $months,
            'pro_until_before' => null,
            'pro_until_after' => now()->addMonthsNoOverflow($months),
            'reason' => GrantReason::Payment,
            'note' => null,
        ];
    }

    /**
     * A grant an administrator made by hand, with no payment behind it.
     */
    public function byAdmin(?User $admin = null, ?string $note = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'granted_by_admin_id' => $admin?->getKey() ?? User::factory(),
            'reason' => GrantReason::AdminGrant,
            'note' => $note ?? 'Granted for support.',
        ]);
    }

    /**
     * A revoke: no months added, entitlement ended where it stood.
     */
    public function revoke(): static
    {
        return $this->state(fn (array $attributes): array => [
            'months' => 0,
            'pro_until_after' => now(),
            'reason' => GrantReason::AdminRevoke,
        ]);
    }
}
