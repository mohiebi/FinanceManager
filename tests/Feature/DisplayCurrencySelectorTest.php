<?php

use App\Enums\Currency;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('transaction workspaces expose display currency options', function () {
    $user = User::factory()->withModules()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('currencies', 3)
            ->where('selectedCurrency', 'toman'),
        );
});

test('investment workspaces expose display currency options', function () {
    $user = User::factory()->withModules()->create();

    $this->actingAs($user)
        ->get(route('investments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Investments')
            ->has('currencies', 3)
            ->where('selectedCurrency', 'toman'),
        );

    $this->actingAs($user)
        ->get(route('portfolio'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portfolio')
            ->has('currencies', 3)
            ->where('selectedCurrency', 'toman'),
        );
});

test('finance pages use the saved default currency when there is no page override', function () {
    $user = User::factory()->withModules()->create([
        'default_currency' => Currency::Usd->value,
    ]);

    $pages = [
        [route('dashboard'), 'Dashboard'],
        [route('transactions.index'), 'Transactions'],
        [route('report'), 'Report'],
        [route('bills.index'), 'Bills'],
        [route('investments.index'), 'Investments'],
        [route('portfolio'), 'Portfolio'],
    ];

    foreach ($pages as [$url, $component]) {
        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->has('currencies', 3)
                ->where('selectedCurrency', Currency::Usd->value),
            );
    }
});

test('a page currency override does not persist over the saved default currency', function () {
    $user = User::factory()->withModules()->create([
        'default_currency' => Currency::Usd->value,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', ['currency' => Currency::Eur->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('selectedCurrency', Currency::Eur->value),
        );

    expect($user->refresh()->default_currency)->toBe(Currency::Usd->value);

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Transactions')
            ->where('selectedCurrency', Currency::Usd->value),
        );
});
