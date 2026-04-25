<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(TransactionType::cases());

        return [
            'user_id' => User::factory(),
            'category_id' => fn (array $attributes) => Category::factory()
                ->state([
                    'user_id' => $attributes['user_id'],
                    'type' => $attributes['type'] instanceof TransactionType
                        ? $attributes['type']
                        : TransactionType::from($attributes['type']),
                ]),
            'type' => $type,
            'amount' => fake()->randomFloat(2, 1, 500000),
            'currency' => Currency::Toman,
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'occurred_at' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        ];
    }

    public function cost(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Cost,
        ]);
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Income,
        ]);
    }
}
