<?php

use App\Enums\AssetType;
use App\Enums\Feature;
use App\Enums\TransactionType;
use App\Mcp\Servers\FinanceServer;
use App\Mcp\Tools\Budgets\BudgetProgressTool;
use App\Mcp\Tools\Goals\ListSavingsGoalsTool;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\InvestmentAsset;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

function mcpCostCategory(string $name): Category
{
    return Category::query()->firstOrCreate(
        ['type' => TransactionType::Cost, 'slug' => Category::slugForName($name)],
        ['name' => $name, 'user_id' => null],
    );
}

function mcpIncomeCategory(): Category
{
    return Category::query()->firstOrCreate(
        ['type' => TransactionType::Income, 'slug' => 'salary'],
        ['name' => 'Salary', 'user_id' => null],
    );
}

test('budget-progress reports what each line may still spend', function () {
    $user = User::factory()->withModules()->create();
    $investing = mcpCostCategory('Investing');

    $budget = Budget::factory()->create(['user_id' => $user->id]);
    BudgetLine::factory()->percent(50)->create([
        'budget_id' => $budget->id,
        'category_id' => $investing->id,
    ]);

    $user->transactions()->create([
        'category_id' => mcpIncomeCategory()->id,
        'type' => TransactionType::Income,
        'amount' => 24000000,
        'currency' => 'toman',
        'title' => 'Salary',
        'occurred_at' => now()->toDateString(),
    ]);
    $user->transactions()->create([
        'category_id' => $investing->id,
        'type' => TransactionType::Cost,
        'amount' => 4200000,
        'currency' => 'toman',
        'title' => 'Gold',
        'occurred_at' => now()->toDateString(),
    ]);

    FinanceServer::actingAs($user)
        ->tool(BudgetProgressTool::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $content) => $content
            // Floats throughout: BudgetMath rounds to 2dp and returns numbers,
            // so JSON carries 24000000.0 rather than an int.
            ->where('income', 24000000.0)
            ->where('lines.0.category', 'Investing')
            ->where('lines.0.allowance', 12000000.0)
            ->where('lines.0.spent', 4200000.0)
            // The number an assistant is actually asked for.
            ->where('lines.0.remaining', 7800000.0)
            ->where('lines.0.over_budget', false)
            ->etc());
});

test('budget-progress says so rather than inventing a plan', function () {
    $user = User::factory()->withModules()->create();

    FinanceServer::actingAs($user)
        ->tool(BudgetProgressTool::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $content) => $content
            ->where('budget', null)
            ->where('message', 'No budget has been set up yet.'));
});

test('budget-progress never reaches another user\'s plan', function () {
    $user = User::factory()->withModules()->create();
    $stranger = User::factory()->withModules()->create();

    $theirs = Budget::factory()->create(['user_id' => $stranger->id, 'title' => 'Their secret plan']);
    BudgetLine::factory()->remainder()->create(['budget_id' => $theirs->id]);

    FinanceServer::actingAs($user)
        ->tool(BudgetProgressTool::class)
        ->assertOk()
        ->assertDontSee('Their secret plan');
});

test('budget-progress is hidden when the budgets module is off', function () {
    $user = User::factory()->withoutModules(Feature::Budgets)->create();

    FinanceServer::actingAs($user)
        ->tool(BudgetProgressTool::class)
        ->assertHasErrors();
});

test('list-savings-goals reports reached separately from on track', function () {
    $user = User::factory()->withModules()->create();
    $asset = InvestmentAsset::query()->where('slug', AssetType::Gold->value)->firstOrFail();

    SavingsGoal::factory()->started(100)->create([
        'user_id' => $user->id,
        'investment_asset_id' => $asset->id,
        'title' => 'Nowruz fund',
        'target_quantity' => 3,
    ]);

    $user->investments()->create([
        'investment_asset_id' => $asset->id,
        'asset_type' => AssetType::Gold->value,
        'kind' => 'buy',
        'quantity' => 4,
        'occurred_at' => now()->toDateString(),
    ]);

    FinanceServer::actingAs($user)
        ->tool(ListSavingsGoalsTool::class)
        ->assertOk()
        ->assertSee('Nowruz fund')
        ->assertStructuredContent(fn (AssertableJson $content) => $content
            // Floats: quantities are rounded to 8dp and returned as numbers.
            ->where('goals.0.current_quantity', 4.0)
            ->where('goals.0.target_quantity', 3.0)
            // Past the target, so reached — not merely "on track".
            ->where('goals.0.reached', true)
            ->etc());
});

test('list-savings-goals says so rather than returning an empty report', function () {
    $user = User::factory()->withModules()->create();

    FinanceServer::actingAs($user)
        ->tool(ListSavingsGoalsTool::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $content) => $content
            ->where('goals', [])
            ->where('message', 'No savings goals have been set up yet.'));
});

test('list-savings-goals never reaches another user\'s goals', function () {
    $user = User::factory()->withModules()->create();
    $stranger = User::factory()->withModules()->create();
    $asset = InvestmentAsset::query()->where('slug', AssetType::Gold->value)->firstOrFail();

    SavingsGoal::factory()->create([
        'user_id' => $stranger->id,
        'investment_asset_id' => $asset->id,
        'title' => 'Their private goal',
    ]);

    FinanceServer::actingAs($user)
        ->tool(ListSavingsGoalsTool::class)
        ->assertOk()
        ->assertDontSee('Their private goal');
});
