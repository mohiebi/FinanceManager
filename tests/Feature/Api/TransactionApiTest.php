<?php

use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

test('api users can list their transactions', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $category = Category::factory()->cost()->forUser($user)->create();

    Transaction::factory()
        ->cost()
        ->for($user)
        ->for($category)
        ->create(['title' => 'Groceries']);
    Transaction::factory()->cost()->for($otherUser)->create(['title' => 'Invisible']);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/transactions');

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Groceries');
});

test('api users can filter transactions by type', function () {
    $user = User::factory()->create();
    $costCategory = Category::factory()->cost()->forUser($user)->create();
    $incomeCategory = Category::factory()->income()->forUser($user)->create();

    Transaction::factory()->cost()->for($user)->for($costCategory)->create();
    Transaction::factory()->income()->for($user)->for($incomeCategory)->create([
        'title' => 'Salary',
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/transactions?type=income');

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Salary')
        ->assertJsonPath('data.0.type', TransactionType::Income->value);
});

test('api users can create transactions', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->forUser($user)->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/transactions', [
        'type' => TransactionType::Cost->value,
        'category_id' => $category->id,
        'amount' => '19.99',
        'currency' => Currency::Toman->value,
        'title' => 'Coffee beans',
        'description' => null,
        'occurred_at' => '2026-04-25',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.title', 'Coffee beans')
        ->assertJsonPath('data.category.id', $category->id);

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'category_id' => $category->id,
        'title' => 'Coffee beans',
    ]);
});

test('api users can update their own transactions', function () {
    $user = User::factory()->create();
    $category = Category::factory()->income()->forUser($user)->create();
    $transaction = Transaction::factory()
        ->income()
        ->for($user)
        ->for($category)
        ->create(['title' => 'Old invoice']);

    $response = $this->actingAs($user, 'sanctum')->patchJson("/api/transactions/{$transaction->id}", [
        'type' => TransactionType::Income->value,
        'category_id' => $category->id,
        'amount' => '800',
        'currency' => Currency::Usd->value,
        'title' => 'New invoice',
        'description' => 'Paid',
        'occurred_at' => '2026-04-25',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.title', 'New invoice')
        ->assertJsonPath('data.currency', Currency::Usd->value);
});

test('api users can delete their own transactions', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->forUser($user)->create();
    $transaction = Transaction::factory()->cost()->for($user)->for($category)->create();

    $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/transactions/{$transaction->id}");

    $response->assertNoContent();

    expect($transaction->fresh())->toBeNull();
});

test('api users cannot access another users transaction', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $transaction = Transaction::factory()->cost()->for($otherUser)->create();

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/transactions/{$transaction->id}")
        ->assertNotFound();
});

test('api users can list default and owned categories only', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $defaultCategory = Category::factory()->cost()->create(['name' => 'Food']);
    $ownedCategory = Category::factory()->cost()->forUser($user)->create(['name' => 'Books']);
    $otherCategory = Category::factory()->cost()->forUser($otherUser)->create(['name' => 'Hidden']);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/categories?type=cost');

    $response->assertOk();

    $categoryIds = collect($response->json('data'))->pluck('id');

    expect($categoryIds)
        ->toContain($defaultCategory->id)
        ->toContain($ownedCategory->id)
        ->not->toContain($otherCategory->id);
});
