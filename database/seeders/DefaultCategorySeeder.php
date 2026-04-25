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
                'Other',
            ],
            TransactionType::Income->value => [
                'Salary',
                'Freelance',
                'Gift',
                'Investment',
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
