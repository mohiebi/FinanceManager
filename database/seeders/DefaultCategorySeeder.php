<?php

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DefaultCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Name => hex colour, matching the v3 design's category-chip palette
        // (a category's colour is text-on-dark, not a background fill — see
        // CategoryChip.vue). These are the shared, unowned template rows
        // (`user_id === null`); they aren't editable per-user, so seeding a
        // colour here is the only place these ever get one.
        $categories = [
            TransactionType::Cost->value => [
                'Food' => Category::DEFAULT_COLORS[TransactionType::Cost->value]['food'],
                'Transport' => Category::DEFAULT_COLORS[TransactionType::Cost->value]['transport'],
                'Housing' => Category::DEFAULT_COLORS[TransactionType::Cost->value]['housing'],
                'Health' => Category::DEFAULT_COLORS[TransactionType::Cost->value]['health'],
                'Shopping' => Category::DEFAULT_COLORS[TransactionType::Cost->value]['shopping'],
                'Bills' => Category::DEFAULT_COLORS[TransactionType::Cost->value]['bills'],
                // Money moved into assets rather than spent. Reports can exclude
                // this category so a large purchase does not read as overspending.
                'Investment' => Category::DEFAULT_COLORS[TransactionType::Cost->value]['investment'],
                'Other' => Category::DEFAULT_COLORS[TransactionType::Cost->value]['other'],
            ],
            // No "Investment" on this side, deliberately. Money coming back out of
            // an asset is either a sale — which is portfolio profit and loss, and
            // already tracked with cost basis by the Investments module — or a
            // recurring payout, which is just income and belongs under whatever it
            // actually is. Naming a category after the source rather than the kind
            // of money made it ambiguous which of the two it meant.
            TransactionType::Income->value => [
                'Salary' => Category::DEFAULT_COLORS[TransactionType::Income->value]['salary'],
                'Freelance' => Category::DEFAULT_COLORS[TransactionType::Income->value]['freelance'],
                'Gift' => Category::DEFAULT_COLORS[TransactionType::Income->value]['gift'],
                'Other' => Category::DEFAULT_COLORS[TransactionType::Income->value]['other'],
            ],
        ];

        foreach ($categories as $type => $names) {
            foreach ($names as $name => $color) {
                Category::query()->updateOrCreate(
                    [
                        'user_id' => null,
                        'type' => $type,
                        'slug' => Str::slug($name),
                    ],
                    [
                        'name' => $name,
                        'color' => $color,
                        'is_default' => true,
                    ],
                );
            }
        }
    }
}
