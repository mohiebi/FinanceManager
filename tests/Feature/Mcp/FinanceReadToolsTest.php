<?php

use App\Mcp\Servers\FinanceServer;
use App\Mcp\Tools\Bills\ListBillsTool;
use App\Mcp\Tools\Reports\SpendingSummaryTool;
use App\Mcp\Tools\Transactions\ListCategoriesTool;
use App\Mcp\Tools\Transactions\ListTransactionsTool;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

test('list-transactions returns only the authenticated user\'s transactions', function () {
    $user = User::factory()->withModules()->create();
    $other = User::factory()->withModules()->create();

    $mine = Transaction::factory()->cost()->create(['user_id' => $user->id, 'title' => 'My groceries']);
    Transaction::factory()->cost()->create(['user_id' => $other->id, 'title' => 'Their secret purchase']);

    FinanceServer::actingAs($user)
        ->tool(ListTransactionsTool::class)
        ->assertOk()
        ->assertSee('My groceries')
        ->assertDontSee('Their secret purchase')
        ->assertStructuredContent(fn (AssertableJson $content) => $content
            ->where('total', 1)
            ->where('transactions.0.id', $mine->id)
            ->etc());
});

test('list-transactions filters by type, date range, and search', function () {
    $user = User::factory()->withModules()->create();

    Transaction::factory()->cost()->create([
        'user_id' => $user->id,
        'title' => 'Taxi ride',
        'occurred_at' => '2026-06-15',
    ]);
    Transaction::factory()->income()->create([
        'user_id' => $user->id,
        'title' => 'Salary',
        'occurred_at' => '2026-06-20',
    ]);
    Transaction::factory()->cost()->create([
        'user_id' => $user->id,
        'title' => 'Old expense',
        'occurred_at' => '2025-01-01',
    ]);

    FinanceServer::actingAs($user)
        ->tool(ListTransactionsTool::class, [
            'type' => 'cost',
            'from_date' => '2026-06-01',
            'to_date' => '2026-06-30',
        ])
        ->assertOk()
        ->assertSee('Taxi ride')
        ->assertDontSee('Salary')
        ->assertDontSee('Old expense');

    FinanceServer::actingAs($user)
        ->tool(ListTransactionsTool::class, ['search' => 'salary'])
        ->assertOk()
        ->assertSee('Salary')
        ->assertDontSee('Taxi ride');
});

test('list-transactions rejects invalid arguments', function () {
    $user = User::factory()->withModules()->create();

    FinanceServer::actingAs($user)
        ->tool(ListTransactionsTool::class, ['type' => 'nonsense'])
        ->assertHasErrors();
});

test('list-categories returns global and own categories but not other users\' ones', function () {
    $user = User::factory()->withModules()->create();
    $other = User::factory()->withModules()->create();

    Category::factory()->cost()->create(['name' => 'Global groceries']);
    Category::factory()->cost()->forUser($user)->create(['name' => 'My custom category']);
    Category::factory()->cost()->forUser($other)->create(['name' => 'Their custom category']);

    FinanceServer::actingAs($user)
        ->tool(ListCategoriesTool::class)
        ->assertOk()
        ->assertSee('Global groceries')
        ->assertSee('My custom category')
        ->assertDontSee('Their custom category');
});

test('list-bills returns bills with pending occurrences and hides other users\' bills', function () {
    $user = User::factory()->withModules()->create();
    $other = User::factory()->withModules()->create();

    $bill = $user->bills()->create([
        'title' => 'Rent',
        'amount' => 5000,
        'currency' => 'toman',
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 5,
    ]);
    $bill->occurrences()->create(['due_date' => now()->addDays(3)->toDateString()]);

    $other->bills()->create([
        'title' => 'Their internet bill',
        'amount' => 100,
        'currency' => 'toman',
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 1,
    ]);

    FinanceServer::actingAs($user)
        ->tool(ListBillsTool::class)
        ->assertOk()
        ->assertSee('Rent')
        ->assertDontSee('Their internet bill')
        ->assertStructuredContent(fn (AssertableJson $content) => $content
            ->count('bills', 1)
            ->where('bills.0.amount', 5000.0)
            ->count('bills.0.pending_occurrences', 1)
            ->etc());
});

test('spending-summary groups by category and sums same-currency amounts', function () {
    $user = User::factory()->withModules()->create();
    $category = Category::factory()->cost()->create(['name' => 'Food']);

    Transaction::factory()->cost()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'amount' => 100,
        'currency' => 'toman',
        'occurred_at' => '2026-07-01',
    ]);
    Transaction::factory()->cost()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'amount' => 50,
        'currency' => 'toman',
        'occurred_at' => '2026-07-10',
    ]);

    FinanceServer::actingAs($user)
        ->tool(SpendingSummaryTool::class, [
            'from_date' => '2026-07-01',
            'to_date' => '2026-07-31',
            'currency' => 'toman',
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $content) => $content
            ->where('total_cost', 150.0)
            ->where('groups.0.group', 'Food')
            ->where('groups.0.count', 2)
            ->etc());
});

test('spending-summary requires a date range', function () {
    $user = User::factory()->withModules()->create();

    FinanceServer::actingAs($user)
        ->tool(SpendingSummaryTool::class)
        ->assertHasErrors();
});
