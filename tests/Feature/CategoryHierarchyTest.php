<?php

use App\Actions\Budgets\BuildBudgetProgress;
use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Parent/child categories (one level) and categories shared across both
 * transaction types.
 */
function defaultFood(): Category
{
    return Category::factory()->cost()->create(['name' => 'Food']);
}

function spend(User $user, Category $category, float $amount, TransactionType $type = TransactionType::Cost): Transaction
{
    return $user->transactions()->create([
        'category_id' => $category->id,
        'type' => $type,
        'amount' => $amount,
        'currency' => 'toman',
        'title' => 'Entry',
        'occurred_at' => Carbon::today()->toDateString(),
    ]);
}

// ── Parent rules ────────────────────────────────────────────────────────

test('a user can file their own subcategory under a default parent', function () {
    $user = User::factory()->create();
    $food = defaultFood();

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'type' => 'cost',
            'name' => 'Restaurant',
            'parent_id' => $food->id,
        ])
        ->assertSessionHasNoErrors();

    expect(Category::query()->where('user_id', $user->id)->sole()->parent_id)->toBe($food->id);
});

test('a subcategory cannot have subcategories of its own', function () {
    $user = User::factory()->create();
    $restaurant = Category::factory()->forUser($user)->childOf(defaultFood())->create();

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'type' => 'cost',
            'name' => 'Fast food',
            'parent_id' => $restaurant->id,
        ])
        ->assertSessionHasErrors(['parent_id' => __('finance.categories.parent_not_top_level')]);
});

test('a category with subcategories cannot become one', function () {
    $user = User::factory()->create();
    $family = Category::factory()->cost()->forUser($user)->create();
    Category::factory()->forUser($user)->childOf($family)->create();

    $this->actingAs($user)
        ->patch(route('categories.update', $family), [
            'name' => $family->name,
            'parent_id' => defaultFood()->id,
        ])
        ->assertSessionHasErrors(['parent_id' => __('finance.categories.has_children')]);

    expect($family->fresh()->parent_id)->toBeNull();
});

test('a category cannot be its own parent', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->forUser($user)->create();

    $this->actingAs($user)
        ->patch(route('categories.update', $category), [
            'name' => $category->name,
            'parent_id' => $category->id,
        ])
        ->assertSessionHasErrors(['parent_id' => __('finance.categories.parent_is_self')]);
});

test('a parent must be available for the subcategory\'s type', function () {
    $user = User::factory()->create();
    $salary = Category::factory()->income()->create(['name' => 'Salary']);

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'type' => 'cost',
            'name' => 'Bonus spending',
            'parent_id' => $salary->id,
        ])
        ->assertSessionHasErrors(['parent_id' => __('finance.categories.parent_type_mismatch')]);
});

test('another user\'s category cannot be a parent', function () {
    $user = User::factory()->create();
    $theirs = Category::factory()->cost()->forUser(User::factory()->create())->create();

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'type' => 'cost',
            'name' => 'Borrowed parent',
            'parent_id' => $theirs->id,
        ])
        ->assertSessionHasErrors(['parent_id' => __('finance.categories.parent_invalid')]);
});

test('deleting a parent promotes its subcategories instead of deleting them', function () {
    $user = User::factory()->create();
    $family = Category::factory()->cost()->forUser($user)->create();
    $child = Category::factory()->forUser($user)->childOf($family)->create();

    $this->actingAs($user)
        ->delete(route('categories.destroy', $family))
        ->assertSessionHasNoErrors();

    expect($family->fresh())->toBeNull()
        ->and($child->fresh())->not->toBeNull()
        ->and($child->fresh()->parent_id)->toBeNull();
});

test('renaming or recolouring leaves the parent and sharing alone', function () {
    $user = User::factory()->create();
    $family = Category::factory()->cost()->forUser($user)->forBothTypes()->create();
    $child = Category::factory()->forUser($user)->childOf($family)->forBothTypes()->create();

    // The colour swatch and the inline rename only send name and colour.
    $this->actingAs($user)
        ->patch(route('categories.update', $child), [
            'name' => 'Renamed',
            'color' => '#123456',
        ])
        ->assertSessionHasNoErrors();

    expect($child->fresh()->parent_id)->toBe($family->id)
        ->and($child->fresh()->for_both_types)->toBeTrue();
});

// ── Shared across both types ────────────────────────────────────────────

test('a shared category can be used for both costs and income', function () {
    $user = User::factory()->create();
    $gift = Category::factory()->cost()->forUser($user)->forBothTypes()->create(['name' => 'Gift']);

    expect(spend($user, $gift, 100)->category_id)->toBe($gift->id)
        ->and(spend($user, $gift, 50, TransactionType::Income)->category_id)->toBe($gift->id);
});

test('a single-type category is still refused for the other type', function () {
    $user = User::factory()->create();
    $groceries = Category::factory()->cost()->forUser($user)->create();

    expect(fn () => spend($user, $groceries, 50, TransactionType::Income))
        ->toThrow(InvalidArgumentException::class);
});

test('the transactions page lists a shared category under both types', function () {
    $user = User::factory()->create();
    $gift = Category::factory()->cost()->forUser($user)->forBothTypes()->create(['name' => 'Gift']);

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories.cost', fn ($categories) => collect($categories)->contains('id', $gift->id))
            ->where('categories.income', fn ($categories) => collect($categories)->contains('id', $gift->id))
        );
});

test('sharing a category claims its name on the other side too', function () {
    $user = User::factory()->create();
    Category::factory()->income()->forUser($user)->create(['name' => 'Gift']);
    $costGift = Category::factory()->cost()->forUser($user)->create(['name' => 'Gift']);

    // Otherwise the income picker would offer two Gifts.
    $this->actingAs($user)
        ->patch(route('categories.update', $costGift), [
            'name' => 'Gift',
            'for_both_types' => true,
        ])
        ->assertSessionHasErrors(['name' => __('finance.categories.already_exists')]);
});

test('unsharing is blocked while the other type still uses the category', function () {
    $user = User::factory()->create();
    $gift = Category::factory()->cost()->forUser($user)->forBothTypes()->create(['name' => 'Gift']);
    spend($user, $gift, 50, TransactionType::Income);
    spend($user, $gift, 70, TransactionType::Income);

    $this->actingAs($user)
        ->patch(route('categories.update', $gift), [
            'name' => 'Gift',
            'for_both_types' => false,
        ])
        ->assertSessionHasErrors(['for_both_types' => __('finance.categories.unshare_in_use', ['count' => 2])]);

    expect($gift->fresh()->for_both_types)->toBeTrue();
});

test('unsharing a parent is blocked while a subcategory needs the other type', function () {
    $user = User::factory()->create();
    $family = Category::factory()->cost()->forUser($user)->forBothTypes()->create();
    Category::factory()->income()->forUser($user)->create(['parent_id' => $family->id]);

    $this->actingAs($user)
        ->patch(route('categories.update', $family), [
            'name' => $family->name,
            'for_both_types' => false,
        ])
        ->assertSessionHasErrors(['for_both_types' => __('finance.categories.children_need_shared')]);
});

test('a shared subcategory needs a shared parent', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'type' => 'cost',
            'name' => 'Takeaway',
            'parent_id' => defaultFood()->id,
            'for_both_types' => true,
        ])
        ->assertSessionHasErrors(['for_both_types' => __('finance.categories.parent_type_mismatch')]);
});

test('bulk assign accepts a shared category for income rows', function () {
    $user = User::factory()->create();
    $salary = Category::factory()->income()->create(['name' => 'Salary']);
    $gift = Category::factory()->cost()->forUser($user)->forBothTypes()->create(['name' => 'Gift']);
    $income = spend($user, $salary, 500, TransactionType::Income);

    $this->actingAs($user)
        ->patch(route('transactions.update-bulk-category'), [
            'ids' => [$income->id],
            'type' => 'income',
            'category_id' => $gift->id,
        ])
        ->assertRedirect();

    expect($income->fresh()->category_id)->toBe($gift->id);
});

// ── The transaction filter ──────────────────────────────────────────────

test('filtering by a parent includes its subcategories', function () {
    $user = User::factory()->create();
    $food = defaultFood();
    $restaurant = Category::factory()->forUser($user)->childOf($food)->create();
    $transport = Category::factory()->cost()->create(['name' => 'Transport']);

    spend($user, $food, 10);
    spend($user, $restaurant, 20);
    spend($user, $transport, 30);

    $this->actingAs($user)
        ->get(route('transactions.index', ['category' => $food->id]))
        ->assertInertia(fn (Assert $page) => $page->has('transactions.costs', 2));

    // A subcategory filter stays narrow: the parent's own rows are not in it.
    $this->actingAs($user)
        ->get(route('transactions.index', ['category' => $restaurant->id]))
        ->assertInertia(fn (Assert $page) => $page->has('transactions.costs', 1));
});

// ── Budgets ─────────────────────────────────────────────────────────────

test('a budget line on a parent counts its subcategories\' spending', function () {
    $user = User::factory()->create();
    $food = defaultFood();
    $restaurant = Category::factory()->forUser($user)->childOf($food)->create();
    $budget = Budget::factory()->expecting(10000000)->create(['user_id' => $user->id]);
    BudgetLine::factory()->percent(30)->create(['budget_id' => $budget->id, 'category_id' => $food->id]);
    BudgetLine::factory()->remainder()->create(['budget_id' => $budget->id]);

    spend($user, $food, 100000);
    spend($user, $restaurant, 250000);

    $progress = app(BuildBudgetProgress::class)->handle($user, $budget->load('lines.category'));

    // Before the rollup the restaurant spend fell through to "everything else".
    expect($progress['lines'][0]['actual'])->toBe(350000.0)
        ->and($progress['lines'][1]['actual'])->toBe(0.0);
});

test('the most specific budget line wins a subcategory\'s spending', function () {
    $user = User::factory()->create();
    $food = defaultFood();
    $restaurant = Category::factory()->forUser($user)->childOf($food)->create();
    $cafe = Category::factory()->forUser($user)->childOf($food)->create();
    $budget = Budget::factory()->expecting(10000000)->create(['user_id' => $user->id]);
    BudgetLine::factory()->percent(30)->create(['budget_id' => $budget->id, 'category_id' => $food->id]);
    BudgetLine::factory()->percent(10)->create(['budget_id' => $budget->id, 'category_id' => $restaurant->id]);
    BudgetLine::factory()->remainder()->create(['budget_id' => $budget->id]);

    spend($user, $food, 100000);
    spend($user, $restaurant, 250000);
    spend($user, $cafe, 40000);

    $progress = app(BuildBudgetProgress::class)->handle($user, $budget->load('lines.category'));

    // Every row lands in exactly one line: nothing counted twice, nothing lost.
    expect($progress['lines'][0]['actual'])->toBe(140000.0)
        ->and($progress['lines'][1]['actual'])->toBe(250000.0)
        ->and($progress['lines'][2]['actual'])->toBe(0.0);
});

test('the vault payload hands each line the ids it owns', function () {
    $user = User::factory()->create();
    $food = defaultFood();
    $restaurant = Category::factory()->forUser($user)->childOf($food)->create();
    $cafe = Category::factory()->forUser($user)->childOf($food)->create();
    // Someone else's subcategory under the same shared default parent.
    Category::factory()->forUser(User::factory()->create())->childOf($food)->create();
    $budget = Budget::factory()->expecting(10000000)->create(['user_id' => $user->id]);
    BudgetLine::factory()->percent(30)->create(['budget_id' => $budget->id, 'category_id' => $food->id]);
    BudgetLine::factory()->percent(10)->create(['budget_id' => $budget->id, 'category_id' => $restaurant->id]);
    BudgetLine::factory()->remainder()->create(['budget_id' => $budget->id]);

    $payload = app(BuildBudgetProgress::class)->clientPayload($user, $budget->load('lines.category'));

    expect($payload['lines'][0]['category_ids'])->toEqualCanonicalizing([$food->id, $cafe->id])
        ->and($payload['lines'][1]['category_ids'])->toBe([$restaurant->id])
        ->and($payload['lines'][2]['category_ids'])->toEqualCanonicalizing([$food->id, $cafe->id, $restaurant->id]);
});
