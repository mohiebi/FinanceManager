<?php

use App\Enums\Currency;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page from reports', function () {
    $response = $this->get(route('report'));

    $response->assertRedirect(route('login'));
});

test('report defaults to this month transactions', function () {
    Carbon::setTestNow('2026-04-28');

    $user = User::factory()->create();
    $costCategory = Category::factory()->cost()->forUser($user)->create();
    $incomeCategory = Category::factory()->income()->forUser($user)->create();

    Transaction::factory()
        ->cost()
        ->for($user)
        ->for($costCategory)
        ->create([
            'title' => 'March rent',
            'occurred_at' => '2026-03-30',
        ]);

    Transaction::factory()
        ->income()
        ->for($user)
        ->for($incomeCategory)
        ->create([
            'title' => 'April salary',
            'occurred_at' => '2026-04-10',
        ]);

    $this->actingAs($user)
        ->get(route('report'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Report')
            ->where('filters.range', 'this_month')
            ->where('filters.from', '2026-04-01')
            ->where('filters.to', '2026-04-28')
            ->has('transactions.incomes', 1)
            ->where('transactions.incomes.0.title', 'April salary')
            ->has('transactions.costs', 0)
            ->where('summary.count', 1),
        );

    Carbon::setTestNow();
});

test('report supports seasonal yearly and custom date filters', function () {
    Carbon::setTestNow('2026-04-28');

    $user = User::factory()->create();
    $costCategory = Category::factory()->cost()->forUser($user)->create();
    $incomeCategory = Category::factory()->income()->forUser($user)->create();

    Transaction::factory()
        ->income()
        ->for($user)
        ->for($incomeCategory)
        ->create([
            'title' => 'January invoice',
            'amount' => 150,
            'currency' => Currency::Usd,
            'occurred_at' => '2026-01-15',
        ]);

    Transaction::factory()
        ->cost()
        ->for($user)
        ->for($costCategory)
        ->create([
            'title' => 'April campaign',
            'amount' => 300000,
            'currency' => Currency::Toman,
            'occurred_at' => '2026-04-12',
        ]);

    Transaction::factory()
        ->income()
        ->for($user)
        ->for($incomeCategory)
        ->create([
            'title' => 'Late April bonus',
            'amount' => 2,
            'currency' => Currency::Eur,
            'occurred_at' => '2026-04-25',
        ]);

    $this->actingAs($user)
        ->get(route('report', ['range' => 'this_season']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Report')
            ->where('filters.range', 'this_season')
            ->where('filters.from', '2026-04-01')
            ->where('filters.to', '2026-04-28')
            ->has('transactions.costs', 1)
            ->where('transactions.costs.0.title', 'April campaign')
            ->has('transactions.incomes', 1)
            ->where('transactions.incomes.0.title', 'Late April bonus')
            ->where('summary.count', 2),
        );

    $this->actingAs($user)
        ->get(route('report', ['range' => 'yearly', 'currency' => 'usd']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Report')
            ->where('filters.range', 'yearly')
            ->has('transactions.incomes', 2)
            ->where('transactions.incomes.0.display_currency', 'usd')
            ->where('summary.count', 3),
        );

    $this->actingAs($user)
        ->get(route('report', [
            'range' => 'custom',
            'from' => '2026-04-20',
            'to' => '2026-04-28',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Report')
            ->where('filters.range', 'custom')
            ->where('filters.from', '2026-04-20')
            ->where('filters.to', '2026-04-28')
            ->has('transactions.costs', 0)
            ->has('transactions.incomes', 1)
            ->where('transactions.incomes.0.title', 'Late April bonus')
            ->where('summary.count', 1),
        );

    Carbon::setTestNow();
});
