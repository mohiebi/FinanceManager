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
        $categories = [
            TransactionType::Cost->value => [
                'Food',
                'Transport',
                'Housing',
                'Health',
                'Shopping',
                'Bills',
                // Money moved into assets rather than spent. Reports can exclude
                // this category so a large purchase does not read as overspending.
                'Investment',
                'Other',
            ],
            // No "Investment" on this side, deliberately. Money coming back out of
            // an asset is either a sale — which is portfolio profit and loss, and
            // already tracked with cost basis by the Investments module — or a
            // recurring payout, which is just income and belongs under whatever it
            // actually is. Naming a category after the source rather than the kind
            // of money made it ambiguous which of the two it meant.
            TransactionType::Income->value => [
                'Salary',
                'Freelance',
                'Gift',
                'Other',
            ],
        ];

        foreach ($categories as $type => $names) {
            foreach ($names as $name) {
                Category::query()->updateOrCreate(
                    [
                        'user_id' => null,
                        'type' => $type,
                        'slug' => Str::slug($name),
                    ],
                    [
                        'name' => $name,
                        'is_default' => true,
                    ],
                );
            }
        }
    }
}
