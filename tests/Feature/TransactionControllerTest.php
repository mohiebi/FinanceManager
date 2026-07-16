<?php

use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('dashboard shows separated cost and income transactions', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-20 12:00:00'));

    try {
        $user = User::factory()->create();
        $costCategory = Category::factory()->cost()->forUser($user)->create();
        $incomeCategory = Category::factory()->income()->forUser($user)->create();

        Transaction::factory()
            ->cost()
            ->for($user)
            ->for($costCategory)
            ->create([
                'title' => 'Groceries',
                'occurred_at' => '2026-06-10',
            ]);
        Transaction::factory()
            ->income()
            ->for($user)
            ->for($incomeCategory)
            ->create([
                'title' => 'Salary',
                'occurred_at' => '2026-06-15',
            ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('transactions.costs', 1)
                ->where('transactions.costs.0.title', 'Groceries')
                ->where('transactions.costs.0.category.name', $costCategory->name)
                ->has('transactions.incomes', 1)
                ->where('transactions.incomes.0.title', 'Salary')
                ->where('transactions.incomes.0.category.name', $incomeCategory->name)
                ->has('categories.cost', 1)
                ->has('categories.income', 1)
                ->has('currencies', 3)
            );
    } finally {
        Carbon::setTestNow();
    }
});

test('transactions page filters by type category date and search', function () {
    $user = User::factory()->create();
    $costCategory = Category::factory()->cost()->forUser($user)->create(['name' => 'Rent']);
    $incomeCategory = Category::factory()->income()->forUser($user)->create(['name' => 'Salary']);

    Transaction::factory()
        ->cost()
        ->for($user)
        ->for($costCategory)
        ->create([
            'title' => 'May apartment rent',
            'description' => 'Downtown lease',
            'occurred_at' => '2026-05-03',
        ]);

    Transaction::factory()
        ->cost()
        ->for($user)
        ->for($costCategory)
        ->create([
            'title' => 'June apartment rent',
            'occurred_at' => '2026-06-03',
        ]);

    Transaction::factory()
        ->income()
        ->for($user)
        ->for($incomeCategory)
        ->create([
            'title' => 'May paycheck',
            'occurred_at' => '2026-05-10',
        ]);

    $this->actingAs($user)
        ->get(route('transactions.index', [
            'type' => TransactionType::Cost->value,
            'category' => $costCategory->id,
            'from' => '2026-05-01',
            'to' => '2026-05-31',
            'search' => 'lease',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Transactions')
            ->where('filters.type', 'cost')
            ->where('filters.category', $costCategory->id)
            ->where('filters.search', 'lease')
            ->has('transactions.costs', 1)
            ->where('transactions.costs.0.title', 'May apartment rent')
            ->has('transactions.incomes', 0)
            ->where('summary.count', 1),
        );
});

test('users can create transactions from an available category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->forUser($user)->create();

    $response = $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Cost->value,
        'category_id' => $category->id,
        'amount' => '45.50',
        'currency' => Currency::Toman->value,
        'title' => 'Lunch',
        'description' => 'Team lunch',
        'occurred_at' => '2026-04-25',
    ]);

    $response->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'category_id' => $category->id,
        'type' => TransactionType::Cost->value,
        'amount' => '45.50',
        'currency' => Currency::Toman->value,
        'title' => 'Lunch',
    ]);
});

test('users can update their own transactions', function () {
    $user = User::factory()->create();
    $category = Category::factory()->income()->forUser($user)->create();
    $transaction = Transaction::factory()
        ->income()
        ->for($user)
        ->for($category)
        ->create(['title' => 'Old title']);

    $response = $this->actingAs($user)->patch(route('transactions.update', $transaction), [
        'type' => TransactionType::Income->value,
        'category_id' => $category->id,
        'amount' => '2500',
        'currency' => Currency::Usd->value,
        'title' => 'Client invoice',
        'description' => null,
        'occurred_at' => '2026-04-25',
    ]);

    $response->assertRedirect(route('dashboard'));

    expect($transaction->refresh()->title)->toBe('Client invoice');
    expect($transaction->currency)->toBe(Currency::Usd);
});

test('users can delete their own transactions', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->forUser($user)->create();
    $transaction = Transaction::factory()
        ->cost()
        ->for($user)
        ->for($category)
        ->create();

    $response = $this->actingAs($user)->delete(route('transactions.destroy', $transaction));

    $response->assertRedirect(route('dashboard'));

    expect($transaction->fresh())->toBeNull();
});

test('users can bulk delete their own selected transactions only', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $category = Category::factory()->cost()->forUser($user)->create();

    $transactions = Transaction::factory()
        ->count(3)
        ->cost()
        ->for($user)
        ->for($category)
        ->create();
    $otherTransaction = Transaction::factory()
        ->cost()
        ->for($otherUser)
        ->create();

    $this->actingAs($user)
        ->delete(route('transactions.destroy-bulk'), [
            'ids' => [
                $transactions[0]->id,
                $transactions[1]->id,
                $otherTransaction->id,
            ],
        ])
        ->assertRedirect();

    expect($transactions[0]->fresh())->toBeNull()
        ->and($transactions[1]->fresh())->toBeNull()
        ->and($transactions[2]->fresh())->not->toBeNull()
        ->and($otherTransaction->fresh())->not->toBeNull();
});

test('transactions reject categories for another transaction type', function () {
    $user = User::factory()->create();
    $category = Category::factory()->income()->forUser($user)->create();

    $response = $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Cost->value,
        'category_id' => $category->id,
        'amount' => '10',
        'currency' => Currency::Toman->value,
        'title' => 'Wrong category',
        'occurred_at' => '2026-04-25',
    ]);

    $response->assertSessionHasErrors('category_id');
});

test('users cannot use another users custom category', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $category = Category::factory()->cost()->forUser($otherUser)->create();

    $response = $this->actingAs($user)->post(route('transactions.store'), [
        'type' => TransactionType::Cost->value,
        'category_id' => $category->id,
        'amount' => '10',
        'currency' => Currency::Toman->value,
        'title' => 'Sneaky category',
        'occurred_at' => '2026-04-25',
    ]);

    $response->assertSessionHasErrors('category_id');
});
