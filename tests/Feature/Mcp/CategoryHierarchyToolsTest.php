<?php

use App\Mcp\Servers\FinanceServer;
use App\Mcp\Tools\ApplyFinanceChangesTool;
use App\Mcp\Tools\Reports\SpendingSummaryTool;
use App\Mcp\Tools\Transactions\ListCategoriesTool;
use App\Mcp\Tools\Transactions\ListTransactionsTool;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Testing\Fluent\AssertableJson;

/**
 * How the MCP surface reports and creates subcategories and shared categories.
 */
function mcpSpend(User $user, Category $category, float $amount): Transaction
{
    return Transaction::factory()->cost()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'amount' => $amount,
        'currency' => 'toman',
        'occurred_at' => '2026-06-10',
    ]);
}

test('list-categories puts each subcategory right after its parent, with its path', function () {
    $user = User::factory()->withModules()->create();
    $food = Category::factory()->cost()->create(['name' => 'Food']);
    Category::factory()->cost()->create(['name' => 'Transport']);
    $restaurant = Category::factory()->forUser($user)->childOf($food)->create(['name' => 'Restaurant']);
    $gift = Category::factory()->cost()->forUser($user)->forBothTypes()->create(['name' => 'Gift']);

    FinanceServer::actingAs($user)
        ->tool(ListCategoriesTool::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $content) => $content
            ->where('categories', function (Collection $categories) use ($food, $restaurant, $gift): bool {
                $ids = $categories->pluck('id')->all();
                $row = $categories->firstWhere('id', $restaurant->id);

                expect(array_search($restaurant->id, $ids, true))->toBe(array_search($food->id, $ids, true) + 1)
                    ->and($row['parent_id'])->toBe($food->id)
                    ->and($row['parent'])->toBe('Food')
                    ->and($row['path'])->toBe('Food › Restaurant')
                    ->and($categories->firstWhere('id', $gift->id)['for_both_types'])->toBeTrue();

                return true;
            }));
});

test('spending-summary rolls a subcategory into its parent and breaks it out', function () {
    $user = User::factory()->withModules()->create();
    $food = Category::factory()->cost()->create(['name' => 'Food']);
    $restaurant = Category::factory()->forUser($user)->childOf($food)->create(['name' => 'Restaurant']);
    mcpSpend($user, $food, 100);
    mcpSpend($user, $restaurant, 250);

    FinanceServer::actingAs($user)
        ->tool(SpendingSummaryTool::class, [
            'from_date' => '2026-06-01',
            'to_date' => '2026-06-30',
            'currency' => 'toman',
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $content) => $content
            ->where('groups', function (Collection $groups): bool {
                // One Food group, not Food plus Restaurant.
                expect($groups)->toHaveCount(1)
                    ->and($groups[0]['group'])->toBe('Food')
                    ->and($groups[0]['cost'])->toEqual(350)
                    ->and($groups[0]['subcategories'])->toHaveCount(1)
                    ->and($groups[0]['subcategories'][0]['group'])->toBe('Restaurant')
                    ->and($groups[0]['subcategories'][0]['cost'])->toEqual(250);

                return true;
            })
            ->etc());
});

test('list-transactions filtered by a parent includes its subcategories', function () {
    $user = User::factory()->withModules()->create();
    $food = Category::factory()->cost()->create(['name' => 'Food']);
    $restaurant = Category::factory()->forUser($user)->childOf($food)->create(['name' => 'Restaurant']);
    mcpSpend($user, $food, 100);
    $dinner = mcpSpend($user, $restaurant, 250);
    mcpSpend($user, Category::factory()->cost()->create(['name' => 'Transport']), 40);

    FinanceServer::actingAs($user)
        ->tool(ListTransactionsTool::class, ['category_id' => $food->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $content) => $content
            ->where('total', 2)
            ->where('transactions', fn (Collection $transactions): bool => $transactions
                ->firstWhere('id', $dinner->id)['category_path'] === 'Food › Restaurant')
            ->etc());
});

test('apply-finance-changes can create a subcategory', function () {
    $user = User::factory()->withModules()->create();
    $food = Category::factory()->cost()->create(['name' => 'Food']);

    FinanceServer::actingAs($user)
        ->tool(ApplyFinanceChangesTool::class, [
            'operations' => [[
                'resource' => 'category',
                'action' => 'create',
                'type' => 'cost',
                'name' => 'Coffee',
                'parent_id' => $food->id,
            ]],
        ])
        ->assertOk();

    expect(Category::query()->where('user_id', $user->id)->sole()->parent_id)->toBe($food->id);
});

test('apply-finance-changes refuses a subcategory under a subcategory', function () {
    $user = User::factory()->withModules()->create();
    $restaurant = Category::factory()->forUser($user)
        ->childOf(Category::factory()->cost()->create(['name' => 'Food']))
        ->create(['name' => 'Restaurant']);

    FinanceServer::actingAs($user)
        ->tool(ApplyFinanceChangesTool::class, [
            'operations' => [[
                'resource' => 'category',
                'action' => 'create',
                'type' => 'cost',
                'name' => 'Fast food',
                'parent_id' => $restaurant->id,
            ]],
        ])
        ->assertHasErrors();

    expect(Category::query()->where('name', 'Fast food')->exists())->toBeFalse();
});
