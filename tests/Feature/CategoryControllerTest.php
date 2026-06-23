<?php

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('users can create a custom category', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('transactions.index'))
        ->post(route('categories.store'), [
            'type' => TransactionType::Cost->value,
            'name' => 'Coffee Shops',
        ]);

    $category = Category::query()
        ->where('user_id', $user->id)
        ->where('type', TransactionType::Cost)
        ->where('name', 'Coffee Shops')
        ->first();

    expect($category)->not->toBeNull()
        ->and($category->is_default)->toBeFalse();

    $response
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas('createdCategory', [
            'id' => $category->id,
            'type' => TransactionType::Cost->value,
        ]);
});

test('users cannot create duplicate available categories for the same type', function () {
    $user = User::factory()->create();

    Category::factory()->cost()->create([
        'name' => 'Food',
    ]);

    $this
        ->actingAs($user)
        ->post(route('categories.store'), [
            'type' => TransactionType::Cost->value,
            'name' => 'Food',
        ])
        ->assertSessionHasErrors('name');

    expect(Category::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('custom category slugs support non english names', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->post(route('categories.store'), [
            'type' => TransactionType::Cost->value,
            'name' => 'بستنی',
        ])
        ->assertSessionHasNoErrors();

    $category = Category::query()
        ->where('user_id', $user->id)
        ->where('name', 'بستنی')
        ->first();

    expect($category)->not->toBeNull()
        ->and($category->slug)->not->toBe('');
});

test('category settings show owned custom categories', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Category::factory()->cost()->create([
        'name' => 'Global Food',
    ]);

    Category::factory()->cost()->forUser($otherUser)->create([
        'name' => 'Other User Category',
    ]);

    Category::factory()->cost()->forUser($user)->create([
        'name' => 'Coffee Shops',
    ]);

    Category::factory()->income()->forUser($user)->create([
        'name' => 'Side Projects',
    ]);

    $this
        ->actingAs($user)
        ->get(route('categories.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Categories')
            ->has('categories.cost', 1)
            ->where('categories.cost.0.name', 'Coffee Shops')
            ->has('categories.income', 1)
            ->where('categories.income.0.name', 'Side Projects')
        );
});

test('users can rename owned custom categories', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->forUser($user)->create([
        'name' => 'Old Name',
    ]);

    $this
        ->actingAs($user)
        ->patch(route('categories.update', $category), [
            'name' => 'Fresh Food',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($category->refresh()->name)->toBe('Fresh Food')
        ->and($category->slug)->toBe('fresh-food');
});

test('users cannot rename categories they do not own', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $category = Category::factory()->income()->forUser($otherUser)->create();

    $this
        ->actingAs($user)
        ->patch(route('categories.update', $category), [
            'name' => 'Nope',
        ])
        ->assertForbidden();
});

test('users can delete unused owned custom categories', function () {
    $user = User::factory()->create();
    $category = Category::factory()->income()->forUser($user)->create();

    $this
        ->actingAs($user)
        ->delete(route('categories.destroy', $category))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('categories', [
        'id' => $category->id,
    ]);
});

test('users cannot delete categories used by transactions', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->forUser($user)->create();

    Transaction::factory()->cost()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
    ]);

    $this
        ->actingAs($user)
        ->delete(route('categories.destroy', $category))
        ->assertRedirect()
        ->assertSessionHasErrors('category');

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
    ]);
});
