<?php

use App\Enums\Currency;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard converts mixed currency totals and transaction amounts to the selected currency', function () {
    $user = User::factory()->create();

    Transaction::factory()->cost()->create([
        'user_id' => $user->id,
        'amount' => 150000,
        'currency' => Currency::Toman,
        'occurred_at' => '2026-04-20',
    ]);

    Transaction::factory()->income()->create([
        'user_id' => $user->id,
        'amount' => 1,
        'currency' => Currency::Eur,
        'occurred_at' => '2026-04-21',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', ['currency' => 'usd']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('selectedCurrency', 'usd')
            ->where('summary.cost', '1.00')
            ->where('summary.income', '1.17')
            ->where('transactions.costs.0.display_amount', '1.00')
            ->where('transactions.costs.0.display_currency', 'usd')
            ->where('transactions.incomes.0.display_amount', '1.17')
            ->where('transactions.incomes.0.display_currency', 'usd'),
        );
});
