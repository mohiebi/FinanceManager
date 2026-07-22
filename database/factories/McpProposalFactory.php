<?php

namespace Database\Factories;

use App\Enums\McpProposalStatus;
use App\Models\McpProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<McpProposal>
 */
class McpProposalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'oauth_client_id' => fake()->uuid(),
            'client_name' => fake()->company(),
            'action' => 'create',
            'resource_type' => 'transaction',
            'resource_id' => null,
            'payload' => [
                'title' => fake()->sentence(3),
                'amount' => fake()->randomFloat(2, 1, 1000),
            ],
            'diff_summary' => [
                'title' => ['old' => null, 'new' => fake()->sentence(3)],
            ],
            'status' => McpProposalStatus::Pending,
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => McpProposalStatus::Confirmed,
            'consumed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => McpProposalStatus::Rejected,
            'consumed_at' => now(),
        ]);
    }
}
