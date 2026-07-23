<?php

use App\Mcp\Servers\FinanceServer;
use App\Mcp\Tools\GetUserContextTool;
use App\Mcp\Tools\Reports\SpendingSummaryTool;
use App\Mcp\Tools\Transactions\ListTransactionsTool;
use App\Mcp\Tools\Transactions\ProposeTransactionTool;
use App\Models\Category;
use App\Models\McpProposal;
use App\Models\Transaction;
use App\Models\User;
use App\Support\CalendarDates;
use Illuminate\Testing\Fluent\AssertableJson;

test('get-user-context reports calendar, currency, and today in both calendars', function () {
    $user = User::factory()->create([
        'calendar' => 'jalali',
        'locale' => 'fa',
        'default_currency' => 'toman',
    ]);

    $expectedJalaliToday = CalendarDates::toJalali(now()->toDateString());

    FinanceServer::actingAs($user)
        ->tool(GetUserContextTool::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $content) => $content
            ->where('calendar', 'jalali')
            ->where('locale', 'fa')
            ->where('default_currency', 'toman')
            ->where('today_gregorian', now()->toDateString())
            ->where('today_jalali', $expectedJalaliToday)
            ->etc());
});

test('jalali date filters are converted server-side instead of being misread as ancient gregorian dates', function () {
    $user = User::factory()->create(['calendar' => 'jalali']);

    // 1405-04-15 Jalali = 2026-07-06 Gregorian.
    $inRange = Transaction::factory()->cost()->create([
        'user_id' => $user->id,
        'title' => 'Inside Tir',
        'occurred_at' => '2026-07-06',
    ]);
    Transaction::factory()->cost()->create([
        'user_id' => $user->id,
        'title' => 'Outside Tir',
        'occurred_at' => '2026-08-15',
    ]);

    // Filter by the Jalali month of Tir 1405 (1405-04-01 to 1405-04-31).
    FinanceServer::actingAs($user)
        ->tool(ListTransactionsTool::class, [
            'from_date' => '1405-04-01',
            'to_date' => '1405-04-31',
        ])
        ->assertOk()
        ->assertSee('Inside Tir')
        ->assertDontSee('Outside Tir')
        ->assertStructuredContent(fn (AssertableJson $content) => $content
            ->where('calendar', 'jalali')
            ->where('total', 1)
            ->where('transactions.0.id', $inRange->id)
            ->where('transactions.0.occurred_at_jalali', '1405-04-15')
            ->etc());
});

test('spending-summary accepts jalali ranges and groups months by the jalali calendar', function () {
    $user = User::factory()->create(['calendar' => 'jalali']);
    $category = Category::factory()->cost()->create(['name' => 'Food']);

    // Two Gregorian dates in DIFFERENT Gregorian months but the SAME Jalali
    // month (Tir 1405 spans 2026-06-22 to 2026-07-22).
    Transaction::factory()->cost()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'amount' => 100,
        'currency' => 'toman',
        'occurred_at' => '2026-06-25',
    ]);
    Transaction::factory()->cost()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'amount' => 50,
        'currency' => 'toman',
        'occurred_at' => '2026-07-05',
    ]);

    FinanceServer::actingAs($user)
        ->tool(SpendingSummaryTool::class, [
            'from_date' => '1405-04-01',
            'to_date' => '1405-04-31',
            'group_by' => 'month',
            'currency' => 'toman',
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $content) => $content
            ->where('calendar', 'jalali')
            ->where('month_calendar', 'jalali')
            ->where('from_date_jalali', '1405-04-01')
            ->where('total_cost', 150.0)
            ->count('groups', 1)
            ->where('groups.0.group', '1405-04')
            ->where('groups.0.count', 2)
            ->etc());
});

test('gregorian users get gregorian month buckets and no jalali fields', function () {
    $user = User::factory()->create(['calendar' => 'gregorian']);
    $category = Category::factory()->cost()->create();

    Transaction::factory()->cost()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'amount' => 100,
        'currency' => 'toman',
        'occurred_at' => '2026-06-25',
    ]);

    FinanceServer::actingAs($user)
        ->tool(SpendingSummaryTool::class, [
            'from_date' => '2026-06-01',
            'to_date' => '2026-06-30',
            'group_by' => 'month',
            'currency' => 'toman',
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $content) => $content
            ->where('calendar', 'gregorian')
            ->where('month_calendar', 'gregorian')
            ->where('from_date_jalali', null)
            ->where('groups.0.group', '2026-06')
            ->etc());
});

test('proposing a transaction with a jalali date stores the gregorian equivalent and shows both in the diff', function () {
    $user = User::factory()->create(['calendar' => 'jalali']);
    $category = Category::factory()->cost()->create();

    FinanceServer::actingAs($user)
        ->tool(ProposeTransactionTool::class, [
            'action' => 'create',
            'type' => 'cost',
            'category_id' => $category->id,
            'amount' => 250,
            'currency' => 'toman',
            'title' => 'Jalali-dated coffee',
            'occurred_at' => '1405-04-15',
        ])
        ->assertOk();

    $proposal = McpProposal::query()->first();

    expect($proposal->payload['occurred_at'])->toBe('2026-07-06')
        ->and($proposal->diff_summary['occurred_at']['new'])->toBe('2026-07-06')
        ->and($proposal->diff_summary['occurred_at']['new_jalali'])->toBe('1405-04-15');
});

test('invalid jalali dates fall through to a validation error instead of silently querying', function () {
    $user = User::factory()->create(['calendar' => 'jalali']);

    // Month 13 is not a valid Jalali month and not a valid Gregorian date.
    FinanceServer::actingAs($user)
        ->tool(ListTransactionsTool::class, ['from_date' => '1405-13-01'])
        ->assertHasErrors();
});
