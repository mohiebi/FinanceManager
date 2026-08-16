<?php

use App\Enums\AssetType;
use App\Enums\Feature;
use App\Mcp\Servers\FinanceServer;
use App\Mcp\Tools\ApplyFinanceChangesTool;
use App\Models\Category;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\McpProposal;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

function transactionOperation(Category $category, int $number): array
{
    return [
        'resource' => 'transaction',
        'action' => 'create',
        'type' => 'cost',
        'category_id' => $category->id,
        'amount' => 1000 + $number,
        'currency' => 'toman',
        'title' => "Imported row {$number}",
        'occurred_at' => '2026-08-01',
    ];
}

test('one approved batch creates many transactions in one tool call', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->create();
    $operations = collect(range(1, 25))
        ->map(fn (int $number): array => transactionOperation($category, $number))
        ->all();

    FinanceServer::actingAs($user)
        ->tool(ApplyFinanceChangesTool::class, ['operations' => $operations])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $content) => $content
            ->where('status', 'applied')
            ->where('applied_count', 25)
            ->where('confirmation_required', false)
            ->count('results', 25)
            ->etc());

    expect($user->transactions()->count())->toBe(25)
        ->and(McpProposal::query()->count())->toBe(0);
});

test('a failed operation rolls back the entire batch', function () {
    $user = User::factory()->create();
    $costCategory = Category::factory()->cost()->create();
    $incomeCategory = Category::factory()->income()->create();

    $invalid = transactionOperation($incomeCategory, 2);

    FinanceServer::actingAs($user)
        ->tool(ApplyFinanceChangesTool::class, [
            'operations' => [
                transactionOperation($costCategory, 1),
                $invalid,
            ],
        ])
        ->assertHasErrors()
        ->assertSee('Batch failed at operation 2')
        ->assertSee('No changes were saved');

    expect(Transaction::query()->count())->toBe(0);
});

test('a batch cannot mutate another users records', function () {
    $owner = User::factory()->create();
    $assistantUser = User::factory()->create();
    $transaction = Transaction::factory()->cost()->create(['user_id' => $owner->id]);

    FinanceServer::actingAs($assistantUser)
        ->tool(ApplyFinanceChangesTool::class, [
            'operations' => [[
                'resource' => 'transaction',
                'action' => 'delete',
                'id' => $transaction->id,
            ]],
        ])
        ->assertHasErrors();

    expect($transaction->fresh())->not->toBeNull();
});

test('module-gated mutations remain unavailable when their module is off', function () {
    $user = User::factory()->create();

    FinanceServer::actingAs($user)
        ->tool(ApplyFinanceChangesTool::class, [
            'operations' => [[
                'resource' => 'bill',
                'action' => 'create',
                'title' => 'Rent',
                'amount' => 5000000,
                'currency' => 'toman',
                'recurrence_type' => 'monthly',
                'due_day_of_month' => 1,
            ]],
        ])
        ->assertHasErrors();

    expect($user->bills()->count())->toBe(0);
});

test('a mixed approved batch applies supported resource changes', function () {
    $user = User::factory()->withModules(Feature::Bills)->create();
    $category = Category::factory()->cost()->create();

    FinanceServer::actingAs($user)
        ->tool(ApplyFinanceChangesTool::class, [
            'operations' => [
                transactionOperation($category, 1),
                [
                    'resource' => 'category',
                    'action' => 'create',
                    'type' => 'cost',
                    'name' => 'Imported costs',
                ],
                [
                    'resource' => 'bill',
                    'action' => 'create',
                    'title' => 'Internet',
                    'amount' => 1200,
                    'currency' => 'toman',
                    'recurrence_type' => 'monthly',
                    'due_day_of_month' => 10,
                ],
            ],
        ])
        ->assertOk();

    $bill = $user->bills()->sole();

    expect($user->transactions()->count())->toBe(1)
        ->and($user->categories()->where('name', 'Imported costs')->exists())->toBeTrue()
        ->and($bill->title)->toBe('Internet')
        ->and($bill->occurrences()->count())->toBe(1);
});

test('batch transaction dates accept jalali input', function () {
    $user = User::factory()->create(['calendar' => 'jalali']);
    $category = Category::factory()->cost()->create();
    $operation = transactionOperation($category, 1);
    $operation['occurred_at'] = '1405-04-15';

    FinanceServer::actingAs($user)
        ->tool(ApplyFinanceChangesTool::class, ['operations' => [$operation]])
        ->assertOk();

    expect($user->transactions()->sole()->occurred_at->toDateString())->toBe('2026-07-06');
});

test('the batch mutation tool advertises one consequential private write call', function () {
    $tool = app(ApplyFinanceChangesTool::class)->toArray();

    expect($tool['annotations'])
        ->toMatchArray([
            'readOnlyHint' => false,
            'destructiveHint' => true,
            'idempotentHint' => false,
            'openWorldHint' => false,
        ])
        ->and($tool['inputSchema']['properties']['operations']['maxItems'])->toBe(100);
});

test('a batch cannot edit a disposal back into a purchase', function () {
    $user = User::factory()->withModules()->create();
    $asset = InvestmentAsset::query()->where('slug', AssetType::Gold->value)->firstOrFail();

    Investment::query()->create([
        'user_id' => $user->id,
        'investment_asset_id' => $asset->id,
        'asset_type' => $asset->slug,
        'kind' => 'buy',
        'quantity' => 5,
        'cost_basis' => 4000000,
        'cost_basis_currency' => 'toman',
        'occurred_at' => '2026-07-01',
    ]);

    // There is no sell action on this surface, so the row is created the way the
    // web sell route creates one.
    $disposal = Investment::query()->create([
        'user_id' => $user->id,
        'investment_asset_id' => $asset->id,
        'asset_type' => $asset->slug,
        'kind' => 'sell',
        'quantity' => -2,
        'cost_basis' => 4000000,
        'cost_basis_currency' => 'toman',
        'sale_price' => 6000000,
        'sale_price_currency' => 'toman',
        'occurred_at' => '2026-07-10',
    ]);

    FinanceServer::actingAs($user)
        ->tool(ApplyFinanceChangesTool::class, [
            'operations' => [[
                'resource' => 'investment',
                'action' => 'update',
                'id' => $disposal->id,
                'investment_asset_id' => $asset->id,
                'quantity' => 3,
                'cost_basis' => 4000000,
                'cost_basis_currency' => 'toman',
                'occurred_at' => '2026-07-10',
            ]],
        ])
        ->assertHasErrors();

    // Untouched, so the holding is still the 3 units the user actually has.
    expect((float) $disposal->fresh()->quantity)->toBe(-2.0)
        ->and($user->investments()->get()->sum(fn (Investment $entry): float => (float) $entry->quantity))
        ->toBe(3.0);
});
