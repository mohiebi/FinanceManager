<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'user_id' => null,
            'type' => fake()->randomElement(TransactionType::cases()),
            'name' => $name,
            'slug' => null,
            'is_default' => true,
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

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'is_default' => false,
        ]);
    }

    public function childOf(Category $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
            'type' => $parent->type,
        ]);
    }

    public function forBothTypes(): static
    {
        return $this->state(fn (array $attributes) => [
            'for_both_types' => true,
        ]);
    }
}
