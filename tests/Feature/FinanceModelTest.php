<?php

use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DefaultCategorySeeder;
use Illuminate\Database\QueryException;

test('users can have many transactions', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->forUser($user)->create();

    Transaction::factory()
        ->count(2)
        ->cost()
        ->for($user)
        ->for($category)
        ->create();

    expect($user->transactions()->count())->toBe(2);
});

test('users can have custom categories', function () {
    $user = User::factory()->create();

    Category::factory()
        ->count(2)
        ->cost()
        ->forUser($user)
        ->create();

    expect($user->categories()->count())->toBe(2);
});

test('default categories are globally available and custom categories are only available to their owner', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $defaultCategory = Category::factory()->cost()->create([
        'name' => 'Food',
        'slug' => 'food',
    ]);
    $ownerCategory = Category::factory()->cost()->forUser($owner)->create([
        'name' => 'Board Games',
        'slug' => 'board-games',
    ]);
    $otherUserCategory = Category::factory()->cost()->forUser($otherUser)->create([
        'name' => 'Pet Care',
        'slug' => 'pet-care',
    ]);

    $availableCategoryIds = Category::query()
        ->availableFor($owner)
        ->pluck('id');

    expect($availableCategoryIds)
        ->toContain($defaultCategory->id)
        ->toContain($ownerCategory->id)
        ->not->toContain($otherUserCategory->id);
});

test('costs and incomes relationships filter transactions by type', function () {
    $user = User::factory()->create();
    $costCategory = Category::factory()->cost()->forUser($user)->create();
    $incomeCategory = Category::factory()->income()->forUser($user)->create();

    $cost = Transaction::factory()
        ->cost()
        ->for($user)
        ->for($costCategory)
        ->create();
    $income = Transaction::factory()
        ->income()
        ->for($user)
        ->for($incomeCategory)
        ->create();

    expect($user->costs()->pluck('id'))
        ->toContain($cost->id)
        ->not->toContain($income->id);

    expect($user->incomes()->pluck('id'))
        ->toContain($income->id)
        ->not->toContain($cost->id);
});

test('transactions cast type and currency to enums', function () {
    $transaction = Transaction::factory()->cost()->create([
        'currency' => Currency::Usd,
    ]);

    expect($transaction->type)->toBe(TransactionType::Cost);
    expect($transaction->currency)->toBe(Currency::Usd);
});

test('deleting a user deletes their transactions and custom categories', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->forUser($user)->create();
    $transaction = Transaction::factory()
        ->cost()
        ->for($user)
        ->for($category)
        ->create();

    $user->delete();

    expect($transaction->fresh())->toBeNull();
    expect($category->fresh())->toBeNull();
});

test('category seeder creates the default category set', function () {
    $this->seed(DefaultCategorySeeder::class);

    expect(Category::query()->whereNull('user_id')->where('is_default', true)->count())->toBe(12);

    foreach (['food', 'transport', 'housing', 'health', 'shopping', 'bills', 'other'] as $slug) {
        $this->assertDatabaseHas('categories', [
            'type' => TransactionType::Cost->value,
            'slug' => $slug,
            'user_id' => null,
            'is_default' => true,
        ]);
    }

    foreach (['salary', 'freelance', 'gift', 'investment', 'other'] as $slug) {
        $this->assertDatabaseHas('categories', [
            'type' => TransactionType::Income->value,
            'slug' => $slug,
            'user_id' => null,
            'is_default' => true,
        ]);
    }
});

test('transaction factory can create cost and income records with valid categories', function () {
    $cost = Transaction::factory()->cost()->create();
    $income = Transaction::factory()->income()->create();

    expect($cost->type)->toBe(TransactionType::Cost);
    expect($cost->category->type)->toBe(TransactionType::Cost);
    expect($income->type)->toBe(TransactionType::Income);
    expect($income->category->type)->toBe(TransactionType::Income);
});

test('transactions must use a category with the same type', function () {
    $user = User::factory()->create();
    $incomeCategory = Category::factory()->income()->forUser($user)->create();

    expect(fn () => Transaction::factory()
        ->cost()
        ->for($user)
        ->for($incomeCategory)
        ->create()
    )->toThrow(InvalidArgumentException::class, 'Transaction category type must match');
});

test('transactions can only use global or owned categories', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherUserCategory = Category::factory()->cost()->forUser($otherUser)->create();

    expect(fn () => Transaction::factory()
        ->cost()
        ->for($user)
        ->for($otherUserCategory)
        ->create()
    )->toThrow(InvalidArgumentException::class, 'Transaction category must be global or owned');
});

test('default categories are unique by type and slug', function () {
    Category::factory()->cost()->create([
        'name' => 'Food',
        'slug' => 'food',
    ]);

    expect(fn () => Category::factory()->cost()->create([
        'name' => 'Food Duplicate',
        'slug' => 'food',
    ]))->toThrow(QueryException::class);
});
