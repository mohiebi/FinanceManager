<?php

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\User;

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
