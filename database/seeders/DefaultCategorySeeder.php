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
                'Food' => '#F59E0B',
                'Transport' => '#3B82F6',
                'Housing' => '#6B7280',
                'Health' => '#E94E50',
                'Shopping' => '#947BFF',
                'Bills' => '#F97316',
                // Money moved into assets rather than spent. Reports can exclude
                // this category so a large purchase does not read as overspending.
                'Investment' => '#02CD86',
                'Other' => '#686868',
            ],
            // No "Investment" on this side, deliberately. Money coming back out of
            // an asset is either a sale — which is portfolio profit and loss, and
            // already tracked with cost basis by the Investments module — or a
            // recurring payout, which is just income and belongs under whatever it
            // actually is. Naming a category after the source rather than the kind
            // of money made it ambiguous which of the two it meant.
            TransactionType::Income->value => [
                'Salary' => '#02CD86',
                'Freelance' => '#947BFF',
                'Gift' => '#F59E0B',
                'Other' => '#686868',
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
