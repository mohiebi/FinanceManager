<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('transaction workspaces expose display currency options', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('currencies', 3)
            ->where('selectedCurrency', 'toman'),
        );
});

test('investment workspaces do not expose display currency options', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('investments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Investments')
            ->missing('currencies')
            ->missing('selectedCurrency'),
        );

    $this->actingAs($user)
        ->get(route('portfolio'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portfolio')
            ->missing('currencies')
            ->missing('selectedCurrency'),
        );
});
