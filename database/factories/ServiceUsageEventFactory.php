<?php

namespace Database\Factories;

use App\Models\ServiceUsageEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceUsageEvent>
 */
class ServiceUsageEventFactory extends Factory
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
            'service' => 'advisor',
            'operation' => 'recommendation',
            'provider' => 'openai',
            'model' => 'test-model',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'outcome' => 'success',
            'shadow_miles' => 175,
            'charged_miles' => 0,
        ];
    }
}
