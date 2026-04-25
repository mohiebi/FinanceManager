<?php

use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('dashboard shows separated cost and income transactions', function () {
    $user = User::factory()->create();
    $costCategory = Category::factory()->cost()->forUser($user)->create();
    $incomeCategory = Category::factory()->income()->forUser($user)->create();

    Transaction::factory()
        ->cost()
        ->for($user)
        ->for($costCategory)
        ->create(['title' => 'Groceries']);
    Transaction::factory()
        ->income()
        ->for($user)
        ->for($incomeCategory)
        ->create(['title' => 'Salary']);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('transactions.costs', 1)
            ->where('transactions.costs.0.title', 'Groceries')
            ->has('transactions.incomes', 1)
            ->where('transactions.incomes.0.title', 'Salary')
            ->has('categories.cost', 1)
            ->has('categories.income', 1)
            ->has('currencies', 3)
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
