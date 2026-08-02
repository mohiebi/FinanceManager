<?php

use App\Actions\Budgets\BuildBudgetProgress;
use App\Enums\BudgetIncomeBasis;
use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @param  array<string, mixed>  $attributes
 */
function progressCategory(string $name, array $attributes = []): Category
{
    return Category::query()->firstOrCreate(
        ['type' => TransactionType::Cost, 'slug' => Category::slugForName($name)],
        ['name' => $name, 'user_id' => null, ...$attributes],
    );
}

function incomeCategory(): Category
{
    return Category::query()->firstOrCreate(
        ['type' => TransactionType::Income, 'slug' => 'salary'],
        ['name' => 'Salary', 'user_id' => null],
    );
}

function record(
    User $user,
    Category $category,
    float $amount,
    TransactionType $type,
    ?string $date = null,
): Transaction {
    return $user->transactions()->create([
        'category_id' => $category->id,
        'type' => $type,
        'amount' => $amount,
        'currency' => 'toman',
        'title' => 'Entry',
        'occurred_at' => $date ?? Carbon::today()->toDateString(),
    ]);
}

/**
 * Pin the market rates so a conversion never reaches for the network.
 */
function fakeRates(float $tomanPerUsd, float $tomanPerEur = 100000): void
{
    Cache::flush();
    config(['services.tgju.enabled' => true]);

    Cache::put('asset-prices.tgju', [
        'usd' => $tomanPerUsd,
        'eur' => $tomanPerEur,
    ], now()->addMinutes(5));
}

test('a percentage line is worth its share of the income actually received', function () {
    $user = User::factory()->create();
    $investing = progressCategory('Investing');
    $budget = Budget::factory()->create(['user_id' => $user->id]);

    BudgetLine::factory()->percent(50)->create([
        'budget_id' => $budget->id,
        'category_id' => $investing->id,
    ]);

    record($user, incomeCategory(), 24000000, TransactionType::Income);
    record($user, $investing, 4200000, TransactionType::Cost);

    $progress = app(BuildBudgetProgress::class)->handle($user, $budget->load('lines.category'));

    expect($progress['income'])->toBe(24000000.0)
        ->and($progress['lines'][0]['allocated'])->toBe(12000000.0)
        ->and($progress['lines'][0]['actual'])->toBe(4200000.0)
        ->and($progress['lines'][0]['remaining'])->toBe(7800000.0)
        ->and($progress['lines'][0]['over'])->toBeFalse();
});

test('an expected-income plan has targets before any income lands', function () {
    $user = User::factory()->create();
    $investing = progressCategory('Investing');
    $budget = Budget::factory()->expecting(20000000)->create(['user_id' => $user->id]);

    BudgetLine::factory()->percent(50)->create([
        'budget_id' => $budget->id,
        'category_id' => $investing->id,
    ]);

    $progress = app(BuildBudgetProgress::class)->handle($user, $budget->load('lines.category'));

    expect($progress['income_basis'])->toBe(BudgetIncomeBasis::Expected->value)
        // No income recorded at all, and the target still exists — the whole
        // point of the expected basis.
        ->and($progress['income'])->toBe(20000000.0)
        ->and($progress['lines'][0]['allocated'])->toBe(10000000.0);
});

test('the remainder line takes every cost no other line claimed', function () {
    $user = User::factory()->create();
    $investing = progressCategory('Investing');
    $food = progressCategory('Food');
    $budget = Budget::factory()->create(['user_id' => $user->id]);

    BudgetLine::factory()->percent(50)->create([
        'budget_id' => $budget->id,
        'category_id' => $investing->id,
        'sort_order' => 0,
    ]);
    BudgetLine::factory()->remainder()->create([
        'budget_id' => $budget->id,
        'sort_order' => 1,
    ]);

    record($user, incomeCategory(), 10000000, TransactionType::Income);
    record($user, $investing, 3000000, TransactionType::Cost);
    record($user, $food, 1200000, TransactionType::Cost);

    $progress = app(BuildBudgetProgress::class)->handle($user, $budget->load('lines.category'));

    expect($progress['lines'][0]['actual'])->toBe(3000000.0)
        // Food is unclaimed, so it falls to the remainder — and the remainder's
        // allowance is what the percentage line left behind.
        ->and($progress['lines'][1]['actual'])->toBe(1200000.0)
        ->and($progress['lines'][1]['allocated'])->toBe(5000000.0);
});

test('spending with no category at all falls to the remainder line', function () {
    $user = User::factory()->create();
    $budget = Budget::factory()->create(['user_id' => $user->id]);

    BudgetLine::factory()->remainder()->create(['budget_id' => $budget->id]);

    record($user, incomeCategory(), 5000000, TransactionType::Income);

    $user->transactions()->create([
        'category_id' => null,
        'type' => TransactionType::Cost,
        'amount' => 750000,
        'currency' => 'toman',
        'title' => 'Uncategorised',
        'occurred_at' => Carbon::today()->toDateString(),
    ]);

    $progress = app(BuildBudgetProgress::class)->handle($user, $budget->load('lines.category'));

    expect($progress['lines'][0]['actual'])->toBe(750000.0);
});

test('a plan that promises more than the income says so', function () {
    $user = User::factory()->create();
    $rent = progressCategory('Rent');
    $budget = Budget::factory()->create(['user_id' => $user->id]);

    BudgetLine::factory()->fixed(7000000)->create([
        'budget_id' => $budget->id,
        'category_id' => $rent->id,
    ]);

    record($user, incomeCategory(), 5000000, TransactionType::Income);

    $progress = app(BuildBudgetProgress::class)->handle($user, $budget->load('lines.category'));

    expect($progress['over_allocated'])->toBe(2000000.0)
        ->and($progress['unallocated'])->toBe(0.0);
});

test('only the current period counts', function () {
    $user = User::factory()->create();
    $investing = progressCategory('Investing');
    $budget = Budget::factory()->create(['user_id' => $user->id]);

    BudgetLine::factory()->percent(50)->create([
        'budget_id' => $budget->id,
        'category_id' => $investing->id,
    ]);

    record($user, incomeCategory(), 8000000, TransactionType::Income);
    // Last month's salary is not this month's budget.
    record(
        $user,
        incomeCategory(),
        30000000,
        TransactionType::Income,
        Carbon::today()->startOfMonth()->subDay()->toDateString(),
    );

    $progress = app(BuildBudgetProgress::class)->handle($user, $budget->load('lines.category'));

    expect($progress['income'])->toBe(8000000.0);
});

test('another users transactions never reach the plan', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $investing = progressCategory('Investing');
    $budget = Budget::factory()->create(['user_id' => $user->id]);

    BudgetLine::factory()->percent(50)->create([
        'budget_id' => $budget->id,
        'category_id' => $investing->id,
    ]);

    record($user, incomeCategory(), 4000000, TransactionType::Income);
    record($stranger, incomeCategory(), 90000000, TransactionType::Income);
    record($stranger, $investing, 50000000, TransactionType::Cost);

    $progress = app(BuildBudgetProgress::class)->handle($user, $budget->load('lines.category'));

    expect($progress['income'])->toBe(4000000.0)
        ->and($progress['lines'][0]['actual'])->toBe(0.0);
});

test('the period is described in the users own calendar', function () {
    $user = User::factory()->create(['calendar' => 'jalali']);
    $budget = Budget::factory()->create(['user_id' => $user->id]);

    BudgetLine::factory()->remainder()->create(['budget_id' => $budget->id]);

    $progress = app(BuildBudgetProgress::class)->handle($user, $budget->load('lines.category'));

    // A Jalali month never begins on the 1st of a Gregorian one, so a start date
    // landing mid-month is exactly what proves the conversion happened.
    expect($progress['period']['start'])
        ->not->toBe(Carbon::today()->startOfMonth()->toDateString())
        ->and($progress['period']['days_in_month'])->toBeGreaterThanOrEqual(29)
        ->and($progress['period']['days_in_month'])->toBeLessThanOrEqual(31);
});

test('the armed payload ships sealed amounts and a resolved period', function () {
    $user = User::factory()->create();
    $investing = progressCategory('Investing');
    $budget = Budget::factory()->create(['user_id' => $user->id]);

    BudgetLine::factory()->percent(50)->create([
        'budget_id' => $budget->id,
        'category_id' => $investing->id,
        'sort_order' => 0,
    ]);
    BudgetLine::factory()->remainder()->create([
        'budget_id' => $budget->id,
        'sort_order' => 1,
    ]);

    record($user, incomeCategory(), 6000000, TransactionType::Income);
    record($user, $investing, 1000000, TransactionType::Cost);

    $payload = app(BuildBudgetProgress::class)
        ->clientPayload($user, $budget->load('lines.category'));

    expect($payload['transactions'])->toHaveCount(2)
        // No allowances: the server cannot resolve a plan whose amounts it
        // cannot read, so it ships the parts and the browser does the maths.
        ->and($payload)->not->toHaveKey('allocated')
        ->and($payload['period']['start'])->toBe(Carbon::today()->startOfMonth()->toDateString())
        // The percentage travels in plaintext — it is not money.
        ->and($payload['lines'][0]['percent'])->toBe(50.0)
        ->and($payload['lines'][0]['category_ids'])->toBe([$investing->id])
        // The remainder line carries the claimed set so the browser can subtract it.
        ->and($payload['lines'][1]['category_ids'])->toBe([$investing->id])
        ->and($payload['rates'])->toHaveKeys(['tomanPerUsd', 'tomanPerEur']);
});

test('a fixed line written in another currency is converted into the plans', function () {
    // Seeded rather than fetched, the same way CurrencyConverterTest does it —
    // otherwise this reaches for a live rate and the assertion depends on the
    // network.
    fakeRates(90000);

    $user = User::factory()->create();
    $subscriptions = progressCategory('Subscriptions');
    $budget = Budget::factory()->create(['user_id' => $user->id]);

    BudgetLine::factory()->fixed(50, Currency::Usd)->create([
        'budget_id' => $budget->id,
        'category_id' => $subscriptions->id,
    ]);

    record($user, incomeCategory(), 10000000, TransactionType::Income);

    $progress = app(BuildBudgetProgress::class)->handle($user, $budget->load('lines.category'));

    // An unconverted 50 would sail through as 50 toman and quietly allocate
    // nothing; the conversion is what makes the line mean $50.
    expect($progress['lines'][0]['allocated'])->toBe(4500000.0);
});

test('a fixed line with no currency of its own uses the plans', function () {
    $user = User::factory()->create();
    $rent = progressCategory('Rent');
    $budget = Budget::factory()->create(['user_id' => $user->id]);

    BudgetLine::factory()->fixed(8000000)->create([
        'budget_id' => $budget->id,
        'category_id' => $rent->id,
    ]);

    record($user, incomeCategory(), 20000000, TransactionType::Income);

    $progress = app(BuildBudgetProgress::class)->handle($user, $budget->load('lines.category'));

    // Same currency in and out, so the converter is a no-op and the amount
    // survives untouched.
    expect($progress['lines'][0]['allocated'])->toBe(8000000.0);
});

test('the armed payload tells the browser what currency a sealed amount is in', function () {
    $user = User::factory()->create();
    $subscriptions = progressCategory('Subscriptions');
    $budget = Budget::factory()->create(['user_id' => $user->id]);

    BudgetLine::factory()->fixed(50, Currency::Usd)->create([
        'budget_id' => $budget->id,
        'category_id' => $subscriptions->id,
    ]);

    $payload = app(BuildBudgetProgress::class)
        ->clientPayload($user, $budget->load('lines.category'));

    expect($payload['lines'][0]['currency'])->toBe(Currency::Usd->value);
});
