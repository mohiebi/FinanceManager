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

test('a new category joins the end of its type list', function () {
    $user = User::factory()->create();
    Category::factory()->cost()->forUser($user)->create(['sort_order' => 0]);
    Category::factory()->cost()->forUser($user)->create(['sort_order' => 1]);

    $this
        ->actingAs($user)
        ->post(route('categories.store'), [
            'type' => TransactionType::Cost->value,
            'name' => 'Streaming',
        ])
        ->assertSessionHasNoErrors();

    $category = Category::query()
        ->where('user_id', $user->id)
        ->where('name', 'Streaming')
        ->first();

    expect($category->sort_order)->toBe(2);
});

test('a category can be created with a color', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->post(route('categories.store'), [
            'type' => TransactionType::Cost->value,
            'name' => 'Coffee Shops',
            'color' => '#02CD86',
        ])
        ->assertSessionHasNoErrors();

    expect(Category::query()->where('user_id', $user->id)->first()->color)
        ->toBe('#02CD86');
});

test('an invalid color is rejected', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->post(route('categories.store'), [
            'type' => TransactionType::Cost->value,
            'name' => 'Coffee Shops',
            'color' => 'not-a-color',
        ])
        ->assertSessionHasErrors('color');

    expect(Category::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('users can recolor an owned custom category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->forUser($user)->create([
        'name' => 'Groceries',
        'color' => '#02CD86',
    ]);

    $this
        ->actingAs($user)
        ->patch(route('categories.update', $category), [
            'name' => 'Groceries',
            'color' => '#947BFF',
        ])
        ->assertSessionHasNoErrors();

    expect($category->refresh()->color)->toBe('#947BFF');
});

test('categories are listed in their sort order', function () {
    $user = User::factory()->create();
    $third = Category::factory()->cost()->forUser($user)->create(['name' => 'Third', 'sort_order' => 2]);
    $first = Category::factory()->cost()->forUser($user)->create(['name' => 'First', 'sort_order' => 0]);
    $second = Category::factory()->cost()->forUser($user)->create(['name' => 'Second', 'sort_order' => 1]);

    $this
        ->actingAs($user)
        ->get(route('categories.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories.cost.0.id', $first->id)
            ->where('categories.cost.1.id', $second->id)
            ->where('categories.cost.2.id', $third->id)
        );
});

test('users can reorder their own categories within a type', function () {
    $user = User::factory()->create();
    $a = Category::factory()->cost()->forUser($user)->create(['sort_order' => 0]);
    $b = Category::factory()->cost()->forUser($user)->create(['sort_order' => 1]);
    $c = Category::factory()->cost()->forUser($user)->create(['sort_order' => 2]);

    $this
        ->actingAs($user)
        ->patch(route('categories.reorder'), [
            'type' => TransactionType::Cost->value,
            'ids' => [$c->id, $a->id, $b->id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($c->refresh()->sort_order)->toBe(0)
        ->and($a->refresh()->sort_order)->toBe(1)
        ->and($b->refresh()->sort_order)->toBe(2);
});

test('reordering ignores ids that are not owned or do not match the type', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $a = Category::factory()->cost()->forUser($user)->create(['sort_order' => 0]);
    $b = Category::factory()->cost()->forUser($user)->create(['sort_order' => 1]);
    $wrongType = Category::factory()->income()->forUser($user)->create(['sort_order' => 0]);
    $notOwned = Category::factory()->cost()->forUser($otherUser)->create(['sort_order' => 0]);

    $this
        ->actingAs($user)
        ->patch(route('categories.reorder'), [
            'type' => TransactionType::Cost->value,
            'ids' => [$notOwned->id, $b->id, $wrongType->id, $a->id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($b->refresh()->sort_order)->toBe(0)
        ->and($a->refresh()->sort_order)->toBe(1)
        ->and($wrongType->refresh()->sort_order)->toBe(0)
        ->and($notOwned->refresh()->sort_order)->toBe(0);
});
