<?php

use App\Enums\Currency;
use App\Enums\InvestmentAssetPriceSource;
use App\Models\DailyStat;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\SocialAccount;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['app.admin_email' => 'admin@example.com']);
});

test('only the configured administrator can access the admin dashboard', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

    $customer = User::factory()->create();
    $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();

    $admin = User::factory()->create(['email' => 'ADMIN@example.com']);
    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Dashboard')
            ->where('auth.isAdmin', true)
            ->missing('auth.adminEmail')
        );
});

test('a blank admin email grants access to nobody', function () {
    config(['app.admin_email' => '']);

    $user = User::factory()->create(['email' => 'admin@example.com']);

    $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
});

test('admin summary uses defined activity boundaries and excludes the administrator', function () {
    Carbon::setTestNow('2026-07-15 12:00:00');

    try {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'telegram_chat_id' => 'admin-chat',
            'last_active_at' => now(),
        ]);
        User::factory()->create([
            'email' => 'online@example.com',
            'telegram_chat_id' => 'customer-chat',
            'created_at' => now()->subDays(10),
            'last_active_at' => now()->subMinutes(10),
        ]);
        User::factory()->unverified()->create([
            'email' => 'monthly@example.com',
            'birthdate' => null,
            'created_at' => now()->subDays(40),
            'last_active_at' => now()->subDays(20),
        ]);
        User::factory()->create([
            'email' => 'inactive@example.com',
            'created_at' => now()->subDays(70),
            'last_active_at' => now()->subDays(40),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('range', '12m')
                ->where('summary.total_customers', 3)
                ->where('summary.total_customers_change', 50)
                ->where('summary.new_customers', 3)
                ->where('summary.new_customers_change', null)
                ->where('summary.online_customers', 1)
                ->where('summary.active_customers_7d', 1)
                ->where('summary.active_customers_30d', 2)
                ->where('summary.active_customers_30d_change', null)
                ->where('summary.stickiness', 50)
                ->where('summary.telegram_customers', 1)
                ->where('summary.telegram_adoption', 33.3)
                ->where('summary.telegram_customers_change', null)
                ->where('summary.verified_customers', 2)
                ->where('summary.verification_rate', 66.7)
                ->where('summary.verified_customers_change', null)
                ->where('summary.completed_profiles', 2)
                ->where('summary.profile_completion_rate', 66.7)
                ->where('summary.activated_customers', 0)
                ->where('summary.activation_rate', 0)
                ->where('summary.median_days_to_first_transaction', null)
                ->where('users.total', 3)
            );
    } finally {
        Carbon::setTestNow();
    }
});

test('admin analytics expose growth adoption authentication and locale data as deferred props', function () {
    Carbon::setTestNow('2026-07-15 12:00:00');

    try {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $customer = User::factory()->create([
            'email' => 'adopter@example.com',
            'locale' => 'fa',
            'created_at' => now()->subMonth(),
            'telegram_chat_id' => 'adopter-chat',
        ]);
        $googleCustomer = User::factory()->passwordless()->create([
            'email' => 'google@example.com',
            'locale' => 'de',
            'created_at' => now(),
        ]);
        SocialAccount::factory()->create(['user_id' => $googleCustomer->id]);
        Transaction::factory()->create(['user_id' => $customer->id]);
        $customer->bills()->create([
            'title' => 'Hosting',
            'amount' => 100,
            'currency' => Currency::Usd->value,
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 20,
        ]);
        $asset = InvestmentAsset::query()->create([
            'user_id' => $customer->id,
            'name' => 'Admin test asset',
            'unit' => 'unit',
            'color' => '#02CD86',
            'price_source_type' => InvestmentAssetPriceSource::Manual,
            'price_source_config' => ['price' => 1],
        ]);
        Investment::query()->create([
            'user_id' => $customer->id,
            'investment_asset_id' => $asset->id,
            'asset_type' => $asset->slug,
            'quantity' => 1,
            'cost_basis' => 1,
            'cost_basis_currency' => Currency::Usd->value,
            'occurred_at' => now()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('analytics')
                ->loadDeferredProps('analytics', fn (Assert $page) => $page
                    ->has('analytics.growth.labels', 12)
                    ->where('analytics.growth.new_customers.10', 1)
                    ->where('analytics.growth.new_customers.11', 1)
                    ->where('analytics.product_adoption.values', [1, 1, 1, 1])
                    ->where('analytics.authentication_mix.values', [1, 1, 0])
                    ->where('analytics.locales.values', [0, 1, 1])
                    ->where('analytics.funnel.values', [2, 2, 2, 1, 0])
                    ->where('analytics.acquisition.labels', ['Direct / unknown'])
                    ->where('analytics.acquisition.values', [2])
                    // Pro and "AI assistant connected" are absent rather than
                    // zero: a segment with nobody in it is skipped entirely, so
                    // only their complements appear for this fixture.
                    ->where('analytics.retention_segments.labels', [
                        'Persian', 'Telegram linked', 'Free', 'No AI assistant',
                    ])
                    ->where('analytics.engagement_trend.labels', [])
                )
            );
    } finally {
        Carbon::setTestNow();
    }
});

test('the analysis range scopes new customer metrics and growth granularity', function () {
    Carbon::setTestNow('2026-07-15 12:00:00');

    try {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        User::factory()->create([
            'email' => 'recent@example.com',
            'created_at' => now()->subDays(5),
        ]);
        User::factory()->create([
            'email' => 'older@example.com',
            'created_at' => now()->subDays(45),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['range' => '30d']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('range', '30d')
                ->where('summary.new_customers', 1)
                ->loadDeferredProps('analytics', fn (Assert $page) => $page
                    ->has('analytics.growth.labels', 30)
                )
            );

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['range' => 'bogus']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('range', '12m')
                ->where('summary.new_customers', 2)
            );
    } finally {
        Carbon::setTestNow();
    }
});

test('summary deltas are computed from the baseline daily snapshot', function () {
    Carbon::setTestNow('2026-07-15 12:00:00');

    try {
        DailyStat::query()->create([
            'date' => now()->subDays(35)->toDateString(),
            'total_customers' => 1,
            'active_customers_30d' => 1,
            'telegram_customers' => 1,
            'verified_customers' => 1,
        ]);

        $admin = User::factory()->create(['email' => 'admin@example.com']);
        User::factory()->create([
            'telegram_chat_id' => 'chat-a',
            'last_active_at' => now()->subDays(2),
        ]);
        User::factory()->create([
            'last_active_at' => now()->subDays(3),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.active_customers_30d_change', 100)
                ->where('summary.telegram_customers_change', 0)
                ->where('summary.verified_customers_change', 100)
            );
    } finally {
        Carbon::setTestNow();
    }
});

test('activation tracks first transactions within a week of signup', function () {
    Carbon::setTestNow('2026-07-15 12:00:00');

    try {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $activated = User::factory()->create(['created_at' => now()->subDays(20)]);
        $late = User::factory()->create(['created_at' => now()->subDays(20)]);
        User::factory()->create(['created_at' => now()->subDays(20)]);

        Transaction::factory()->create([
            'user_id' => $activated->id,
            'created_at' => now()->subDays(18),
        ]);
        Transaction::factory()->create([
            'user_id' => $late->id,
            'created_at' => now()->subDays(2),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.activated_customers', 1)
                ->where('summary.activation_rate', 33.3)
                ->where('summary.median_days_to_first_transaction', 10)
            );
    } finally {
        Carbon::setTestNow();
    }
});

test('the directory supports filters and never exposes sensitive customer fields', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    User::factory()->create([
        'name' => 'Telegram Customer',
        'email' => 'telegram@example.com',
        'telegram_chat_id' => 'secret-chat-id',
        'telegram_connect_token' => 'secret-connect-token',
        'last_active_at' => now()->subDay(),
    ]);
    User::factory()->unverified()->create([
        'name' => 'Other Customer',
        'email' => 'other@example.com',
        'last_active_at' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard', [
            'search' => 'telegram',
            'activity' => '7d',
            'telegram' => 'connected',
            'verification' => 'verified',
            'sort' => 'last_active',
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', 'telegram')
            ->where('filters.activity', '7d')
            ->where('filters.telegram', 'connected')
            ->where('filters.verification', 'verified')
            ->where('filters.sort', 'last_active')
            ->where('users.total', 1)
            ->where('users.data.0.email', 'telegram@example.com')
            ->where('users.data.0.telegram_connected', true)
            ->missing('users.data.0.password')
            ->missing('users.data.0.telegram_chat_id')
            ->missing('users.data.0.telegram_connect_token')
            ->missing('users.data.0.two_factor_secret')
        );
});

test('the directory can sort customers by product usage', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $light = User::factory()->create(['email' => 'light@example.com']);
    $heavy = User::factory()->create(['email' => 'heavy@example.com']);
    Transaction::factory()->create(['user_id' => $light->id]);
    Transaction::factory()->count(3)->create(['user_id' => $heavy->id]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard', ['sort' => 'transactions']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'transactions')
            ->where('users.data.0.email', 'heavy@example.com')
            ->where('users.data.0.transaction_count', 3)
        );
});

test('the directory paginates twenty customers and supports partial reloads', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    User::factory()->count(21)->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.per_page', 20)
            ->where('users.total', 21)
            ->has('users.data', 20)
            ->reloadOnly(['users', 'filters'], fn (Assert $reload) => $reload
                ->has('users')
                ->has('filters')
                ->missing('summary')
                ->missing('analytics')
            )
        );
});
