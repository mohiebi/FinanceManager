<?php

namespace Database\Factories;

use App\Enums\CouponKind;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Defaults to an unrestricted 25%-off code: public, unlimited, no expiry.
     * Every restriction is opt-in through a state, so a test that cares about
     * one limit does not silently inherit three others.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => mb_strtoupper(Str::random(10)),
            'kind' => CouponKind::Percent,
            'percent_off' => 25,
            'amount_off_usd' => null,
            'user_id' => null,
            'max_redemptions' => null,
            'max_per_user' => null,
            'valid_until' => null,
            'disabled_at' => null,
            'note' => null,
            'created_by_admin_id' => null,
        ];
    }

    public function percent(int $percentOff): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => CouponKind::Percent,
            'percent_off' => $percentOff,
            'amount_off_usd' => null,
        ]);
    }

    public function fixed(string $amountOffUsd): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => CouponKind::Fixed,
            'percent_off' => null,
            'amount_off_usd' => $amountOffUsd,
        ]);
    }

    /** Covers the whole price, so redemption skips the chain entirely. */
    public function free(): static
    {
        return $this->percent(100);
    }

    /**
     * Issued to one account rather than published.
     *
     * Named issuedTo rather than for: Factory::for() already exists for
     * defining a parent relationship, and redeclaring it with a different
     * signature is a fatal at class-load time.
     */
    public function issuedTo(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->getKey(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'valid_until' => now()->subDay(),
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'disabled_at' => now(),
        ]);
    }

    public function limited(?int $total = null, ?int $perUser = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'max_redemptions' => $total,
            'max_per_user' => $perUser,
        ]);
    }
}
