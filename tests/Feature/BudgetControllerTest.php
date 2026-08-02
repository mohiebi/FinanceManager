<?php

use App\Enums\Currency;
use App\Enums\Feature;
use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function budgetUser(): User
{
    return User::factory()->withModules(Feature::Budgets)->create();
}

/**
 * Cost categories are global and unique per slug, and the app seeds an
 * `investment` one — so this adopts whatever already exists rather than
 * colliding with the seeder.
 */
function costCategory(string $name = 'Investment'): Category
{
    return Category::query()->firstOrCreate(
        ['type' => TransactionType::Cost, 'slug' => Category::slugForName($name)],
        ['name' => $name, 'user_id' => null],
    );
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function budgetPayload(array $overrides = []): array
{
    return [
        'title' => 'Monthly plan',
        'income_basis' => 'actual',
        'currency' => 'toman',
        'lines' => [
            [
                'category_id' => costCategory()->id,
                'rule_type' => 'percent',
                'percent' => 50,
            ],
        ],
        ...$overrides,
    ];
}

test('a user can file a plan that gives half of every income to a category', function () {
    $user = budgetUser();
    $category = costCategory('Investment');

    $this->actingAs($user)
        ->post(route('budgets.store'), budgetPayload([
            'lines' => [
                ['category_id' => $category->id, 'rule_type' => 'percent', 'percent' => 50],
                ['rule_type' => 'remainder'],
            ],
        ]))
        ->assertRedirect();

    $budget = $user->budgets()->with('lines')->firstOrFail();

    expect($budget->title)->toBe('Monthly plan')
        ->and($budget->lines)->toHaveCount(2)
        ->and((float) $budget->lines[0]->percent)->toBe(50.0)
        ->and($budget->lines[0]->category_id)->toBe($category->id)
        // The remainder line owns no category — it is "everything else".
        ->and($budget->lines[1]->category_id)->toBeNull()
        // Ordering is the order the client sent, so the page renders it back the
        // same way it was written.
        ->and($budget->lines[1]->sort_order)->toBe(1);
});

test('the plan starts in the period it was written in', function () {
    $user = budgetUser();

    $this->actingAs($user)->post(route('budgets.store'), budgetPayload());

    expect($user->budgets()->firstOrFail()->starts_on->toDateString())
        ->toBe(now()->startOfMonth()->toDateString());
});

test('percentage lines cannot promise more than the whole income', function () {
    $user = budgetUser();

    $this->actingAs($user)
        ->post(route('budgets.store'), budgetPayload([
            'lines' => [
                ['category_id' => costCategory('Rent')->id, 'rule_type' => 'percent', 'percent' => 60],
                ['category_id' => costCategory('Food')->id, 'rule_type' => 'percent', 'percent' => 50],
            ],
        ]))
        ->assertSessionHasErrors('lines');

    expect($user->budgets()->count())->toBe(0);
});

test('a plan can only have one everything-else line', function () {
    $user = budgetUser();

    $this->actingAs($user)
        ->post(route('budgets.store'), budgetPayload([
            'lines' => [
                ['rule_type' => 'remainder'],
                ['rule_type' => 'remainder'],
            ],
        ]))
        ->assertSessionHasErrors('lines');
});

test('a category cannot be claimed by two lines', function () {
    $user = budgetUser();
    $category = costCategory('Food');

    $this->actingAs($user)
        ->post(route('budgets.store'), budgetPayload([
            'lines' => [
                ['category_id' => $category->id, 'rule_type' => 'percent', 'percent' => 20],
                ['category_id' => $category->id, 'rule_type' => 'fixed', 'fixed_amount' => 500000],
            ],
        ]))
        ->assertSessionHasErrors('lines');
});

test('a line cannot point at an income category', function () {
    $user = budgetUser();
    $salary = Category::factory()->income()->create(['name' => 'Salary']);

    $this->actingAs($user)
        ->post(route('budgets.store'), budgetPayload([
            'lines' => [
                ['category_id' => $salary->id, 'rule_type' => 'percent', 'percent' => 50],
            ],
        ]))
        ->assertSessionHasErrors('lines');
});

test('a line cannot point at another users category', function () {
    $user = budgetUser();
    $stranger = User::factory()->create();
    $private = Category::factory()->cost()->forUser($stranger)->create();

    $this->actingAs($user)
        ->post(route('budgets.store'), budgetPayload([
            'lines' => [
                ['category_id' => $private->id, 'rule_type' => 'percent', 'percent' => 50],
            ],
        ]))
        ->assertSessionHasErrors('lines');
});

test('an expected-income plan must say what income it expects', function () {
    $user = budgetUser();

    $this->actingAs($user)
        ->post(route('budgets.store'), budgetPayload(['income_basis' => 'expected']))
        ->assertSessionHasErrors('expected_income');
});

test('editing a plan replaces its lines', function () {
    $user = budgetUser();
    $rent = costCategory('Rent');

    $this->actingAs($user)->post(route('budgets.store'), budgetPayload());
    $budget = $user->budgets()->firstOrFail();

    $this->actingAs($user)
        ->put(route('budgets.update', $budget), budgetPayload([
            'title' => 'Leaner plan',
            'lines' => [
                ['category_id' => $rent->id, 'rule_type' => 'fixed', 'fixed_amount' => 8000000],
            ],
        ]))
        ->assertRedirect();

    $budget->refresh()->load('lines');

    expect($budget->title)->toBe('Leaner plan')
        ->and($budget->lines)->toHaveCount(1)
        ->and((float) $budget->lines[0]->fixed_amount)->toBe(8000000.0)
        ->and($budget->lines[0]->rule_type->value)->toBe('fixed');
});

test('a plan belonging to someone else is not confirmed to exist', function () {
    $user = budgetUser();
    $stranger = User::factory()->withModules(Feature::Budgets)->create();
    $theirs = Budget::factory()->create(['user_id' => $stranger->id]);

    $this->actingAs($user)
        ->put(route('budgets.update', $theirs), budgetPayload())
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('budgets.destroy', $theirs))
        ->assertNotFound();
});

test('deleting a plan takes its lines with it and leaves transactions alone', function () {
    $user = budgetUser();
    $category = costCategory('Food');

    $user->transactions()->create([
        'category_id' => $category->id,
        'type' => TransactionType::Cost,
        'amount' => 120000,
        'currency' => 'toman',
        'title' => 'Groceries',
        'occurred_at' => now()->toDateString(),
    ]);

    $this->actingAs($user)->post(route('budgets.store'), budgetPayload([
        'lines' => [['category_id' => $category->id, 'rule_type' => 'percent', 'percent' => 30]],
    ]));

    $budget = $user->budgets()->firstOrFail();

    $this->actingAs($user)
        ->delete(route('budgets.destroy', $budget))
        ->assertRedirect();

    expect($user->budgets()->count())->toBe(0)
        ->and(DB::table('budget_lines')->count())->toBe(0)
        ->and($user->transactions()->count())->toBe(1);
});

test('the page is gated behind the module', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('budgets.index'))
        ->assertRedirect();

    $this->actingAs(budgetUser())
        ->get(route('budgets.index'))
        ->assertOk();
});

test('a fixed line remembers the currency it was written in', function () {
    $user = budgetUser();
    $subscriptions = costCategory('Subscriptions');

    $this->actingAs($user)
        ->post(route('budgets.store'), budgetPayload([
            'currency' => 'toman',
            'lines' => [
                [
                    'category_id' => $subscriptions->id,
                    'rule_type' => 'fixed',
                    'fixed_amount' => 50,
                    'currency' => 'usd',
                ],
            ],
        ]))
        ->assertRedirect();

    $line = $user->budgets()->firstOrFail()->lines()->firstOrFail();

    expect($line->currency)->toBe(Currency::Usd)
        ->and((float) $line->fixed_amount)->toBe(50.0);
});

test('only a fixed line keeps a currency', function () {
    $user = budgetUser();
    $investing = costCategory('Investing');

    $this->actingAs($user)
        ->post(route('budgets.store'), budgetPayload([
            'lines' => [
                [
                    'category_id' => $investing->id,
                    'rule_type' => 'percent',
                    'percent' => 50,
                    // Sent by the form, which carries a currency on every row so
                    // the select can bind. It is a fact about nothing here.
                    'currency' => 'usd',
                ],
            ],
        ]))
        ->assertRedirect();

    expect($user->budgets()->firstOrFail()->lines()->firstOrFail()->currency)->toBeNull();
});

test('an unknown currency is refused', function () {
    $user = budgetUser();

    $this->actingAs($user)
        ->post(route('budgets.store'), budgetPayload([
            'lines' => [
                [
                    'category_id' => costCategory('Rent')->id,
                    'rule_type' => 'fixed',
                    'fixed_amount' => 100,
                    'currency' => 'dogecoin',
                ],
            ],
        ]))
        ->assertSessionHasErrors('lines.0.currency');
});
