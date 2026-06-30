<?php

use App\Enums\Currency;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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

test('the shared notifications prop is deferred rather than eagerly loaded on every page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('notifications')
            ->loadDeferredProps('default', fn (Assert $page) => $page
                ->has('notifications.unread_count')
                ->has('notifications.recent')
            )
        );
});

test('dashboard converts mixed currency totals and transaction amounts to the selected currency', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-22 12:00:00'));

    // Seed deterministic live prices so this test asserts conversion arithmetic
    // independent of real tgju prices or local .env overrides.
    Cache::flush();
    config(['services.tgju.enabled' => true]);
    Cache::put('asset-prices.tgju', [
        'usd' => 150000.0,
        'eur' => 175500.0,
    ], now()->addMinutes(5));

    try {
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
    } finally {
        Carbon::setTestNow();
    }
});

test('dashboard current period follows the preferred calendar', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-16 12:00:00'));

    try {
        $user = User::factory()->create([
            'calendar' => 'jalali',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('calendar', 'jalali')
                ->where('period.month', 'خرداد')
                ->where('period.year', 1405)
                ->where('period.dayOfMonth', 26)
                ->where('period.daysInMonth', 31)
                ->where('period.progress', 84),
            );
    } finally {
        Carbon::setTestNow();
    }
});
