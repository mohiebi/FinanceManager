<?php

use App\Actions\Bills\MarkBillOccurrencePaid;
use App\Actions\Bills\SyncBillOccurrence;
use App\Enums\Currency;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

test('it renders the bills index page', function () {
    $user = User::factory()->create();

    $user->bills()->create([
        'title' => 'Rent',
        'amount' => 5000000,
        'currency' => 'toman',
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('bills.index'))
        ->assertOk();
});

test('it converts bill amounts to the selected currency and sorts by next due date', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-06 12:00:00'));
    Cache::flush();
    config(['services.tgju.enabled' => true]);
    Cache::put('asset-prices.tgju', [
        'usd' => 150000.0,
        'eur' => 175500.0,
    ], now()->addMinutes(5));

    try {
        $user = User::factory()->create();

        $farBill = $user->bills()->create([
            'title' => 'Far bill',
            'amount' => 300000,
            'currency' => Currency::Toman->value,
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 20,
        ]);
        $farBill->occurrences()->create(['due_date' => '2026-08-20']);

        $currentMonthTomanBill = $user->bills()->create([
            'title' => 'Current month toman bill',
            'amount' => 300000,
            'currency' => Currency::Toman->value,
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 20,
        ]);
        $currentMonthTomanBill->occurrences()->create(['due_date' => '2026-07-20']);

        $closeBill = $user->bills()->create([
            'title' => 'Close bill',
            'amount' => 2,
            'currency' => Currency::Usd->value,
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 10,
        ]);
        $closeBill->occurrences()->create(['due_date' => '2026-07-10']);

        $user->bills()->create([
            'title' => 'No upcoming bill',
            'amount' => 1,
            'currency' => Currency::Usd->value,
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('bills.index', ['currency' => Currency::Usd->value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Bills')
                ->where('selectedCurrency', Currency::Usd->value)
                ->where('monthlyBillSummary.amount', '4.00')
                ->where('monthlyBillSummary.currency', Currency::Usd->value)
                ->where('monthlyBillSummary.count', 2)
                ->where('bills.0.title', 'Close bill')
                ->where('bills.0.display_amount', '2.00')
                ->where('bills.0.display_currency', Currency::Usd->value)
                ->where('bills.1.title', 'Current month toman bill')
                ->where('bills.1.display_amount', '2.00')
                ->where('bills.1.display_currency', Currency::Usd->value)
                ->where('bills.2.title', 'No upcoming bill')
                ->where('bills.3.title', 'Far bill')
            );
    } finally {
        Carbon::setTestNow();
    }
});

test('it creates a recurring bill and generates the first occurrence', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 10));

    try {
        $user = User::factory()->create();
        $category = Category::factory()->cost()->create();

        $this->actingAs($user)
            ->post(route('bills.store'), [
                'title' => 'Rent',
                'amount' => 5000000,
                'currency' => 'toman',
                'category_id' => $category->id,
                'recurrence_type' => 'monthly',
                'due_day_of_month' => 1,
            ])
            ->assertRedirect();

        $bill = Bill::query()->sole();

        expect((float) $bill->amount)->toBe(5000000.0)
            ->and($bill->due_day_of_month)->toBe(1)
            ->and($bill->due_date)->toBeNull();

        $occurrence = $bill->occurrences()->sole();
        expect($occurrence->due_date->toDateString())->toBe('2026-08-01');
    } finally {
        Carbon::setTestNow();
    }
});

test('updating the due day recomputes the pending occurrence instead of leaving it stale', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 10));

    try {
        $user = User::factory()->create();

        $bill = $user->bills()->create([
            'title' => 'Room bill',
            'amount' => 5000000,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 1,
        ]);

        $occurrence = $bill->occurrences()->create([
            'due_date' => '2026-08-01',
            'reminder_day_before_sent_at' => now(),
        ]);

        $this->actingAs($user)
            ->put(route('bills.update', $bill), [
                'title' => 'Room bill',
                'amount' => 6724000,
                'currency' => 'toman',
                'recurrence_type' => 'monthly',
                'due_day_of_month' => 12,
            ])
            ->assertRedirect();

        expect($bill->occurrences()->count())->toBe(1);

        $occurrence->refresh();
        expect($occurrence->due_date->toDateString())->toBe('2026-07-12')
            ->and($occurrence->reminder_day_before_sent_at)->toBeNull();
    } finally {
        Carbon::setTestNow();
    }
});

test('updating a bill that already matches its due day leaves the occurrence untouched', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 10));

    try {
        $user = User::factory()->create();

        $bill = $user->bills()->create([
            'title' => 'Rent',
            'amount' => 100,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 1,
        ]);

        $occurrence = $bill->occurrences()->create([
            'due_date' => '2026-08-01',
            'reminder_day_before_sent_at' => now(),
        ]);

        $this->actingAs($user)
            ->put(route('bills.update', $bill), [
                'title' => 'Rent (updated title only)',
                'amount' => 100,
                'currency' => 'toman',
                'recurrence_type' => 'monthly',
                'due_day_of_month' => 1,
            ])
            ->assertRedirect();

        $occurrence->refresh();
        expect($occurrence->due_date->toDateString())->toBe('2026-08-01')
            ->and($occurrence->reminder_day_before_sent_at)->not->toBeNull();
    } finally {
        Carbon::setTestNow();
    }
});

test('updating a bill preserves the telegram reminder preference', function () {
    $user = User::factory()->create();

    $bill = $user->bills()->create([
        'title' => 'Rent',
        'amount' => 100,
        'currency' => 'toman',
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 1,
        'telegram_reminder_enabled' => false,
    ]);

    $this->actingAs($user)
        ->put(route('bills.update', $bill), [
            'title' => 'Rent',
            'amount' => 100,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 1,
            'telegram_reminder_enabled' => 1,
        ])
        ->assertRedirect();

    expect($bill->fresh()->telegram_reminder_enabled)->toBeTrue();

    $this->actingAs($user)
        ->put(route('bills.update', $bill), [
            'title' => 'Rent',
            'amount' => 100,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 1,
            'telegram_reminder_enabled' => 0,
        ])
        ->assertRedirect();

    expect($bill->fresh()->telegram_reminder_enabled)->toBeFalse();
});

test('it creates a one-time bill with the given due date', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('bills.store'), [
            'title' => 'Annual fee',
            'amount' => 1200000,
            'currency' => 'toman',
            'recurrence_type' => 'one_time',
            'due_date' => '2026-09-01',
        ])
        ->assertRedirect();

    $bill = Bill::query()->sole();
    $occurrence = $bill->occurrences()->sole();

    expect($occurrence->due_date->toDateString())->toBe('2026-09-01');
});

test('marking an occurrence paid creates a cost transaction and stops it from being reminded again', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->create();

    $bill = $user->bills()->create([
        'title' => 'Phone',
        'amount' => 500000,
        'currency' => 'toman',
        'category_id' => $category->id,
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 15,
    ]);

    $occurrence = $bill->occurrences()->create(['due_date' => '2026-07-15']);

    $this->actingAs($user)
        ->post(route('bills.occurrences.pay', ['bill' => $bill->id, 'occurrence' => $occurrence->id]))
        ->assertRedirect();

    $occurrence->refresh();

    expect($occurrence->isPaid())->toBeTrue()
        ->and($occurrence->transaction)->not->toBeNull()
        ->and((float) $occurrence->transaction->amount)->toBe(500000.0)
        ->and($occurrence->transaction->category_id)->toBe($category->id);
});

test('users cannot mark another users bill occurrence as paid', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $bill = $owner->bills()->create([
        'title' => 'Rent',
        'amount' => 100,
        'currency' => 'toman',
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 1,
    ]);

    $occurrence = $bill->occurrences()->create(['due_date' => '2026-07-01']);

    $this->actingAs($intruder)
        ->post(route('bills.occurrences.pay', ['bill' => $bill->id, 'occurrence' => $occurrence->id]))
        ->assertNotFound();
});

test('it deletes a bill', function () {
    $user = User::factory()->create();

    $bill = $user->bills()->create([
        'title' => 'Subscription',
        'amount' => 100,
        'currency' => 'toman',
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 1,
    ]);

    $this->actingAs($user)
        ->delete(route('bills.destroy', $bill))
        ->assertRedirect();

    expect(Bill::query()->find($bill->id))->toBeNull();
});

test('calling MarkBillOccurrencePaid twice does not create a duplicate transaction', function () {
    $user = User::factory()->create();

    $bill = $user->bills()->create([
        'title' => 'Rent',
        'amount' => 100,
        'currency' => 'toman',
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 1,
    ]);
    $occurrence = $bill->occurrences()->create(['due_date' => '2026-07-01']);

    $action = app(MarkBillOccurrencePaid::class);
    $action($bill, $occurrence);
    $action($bill, $occurrence->fresh());

    expect(Transaction::query()->count())->toBe(1)
        ->and($occurrence->fresh()->isPaid())->toBeTrue();
});

test('store rejects a category_id that does not belong to the user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $category = Category::factory()->cost()->create(['user_id' => $owner->id, 'is_default' => false]);

    $this->actingAs($intruder)
        ->post(route('bills.store'), [
            'title' => 'Rent',
            'amount' => 100,
            'currency' => 'toman',
            'category_id' => $category->id,
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 1,
        ])
        ->assertSessionHasErrors('category_id');

    expect(Bill::query()->count())->toBe(0);
});

test('marking a bill occurrence paid without a category falls back to the default bills category', function () {
    $user = User::factory()->create();
    $billsCategory = Category::query()->firstOrCreate(
        ['user_id' => null, 'type' => 'cost', 'slug' => 'bills'],
        ['name' => 'Bills', 'is_default' => true],
    );

    $bill = $user->bills()->create([
        'title' => 'Electricity',
        'amount' => 200000,
        'currency' => 'toman',
        'category_id' => null,
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 10,
    ]);

    $occurrence = $bill->occurrences()->create(['due_date' => '2026-07-10']);

    app(MarkBillOccurrencePaid::class)($bill, $occurrence);

    $transaction = $occurrence->fresh()->transaction;
    expect($transaction)->not->toBeNull()
        ->and($transaction->category_id)->toBe($billsCategory->id);
});

test('store rejects an income category for a bill', function () {
    $user = User::factory()->create();
    $incomeCategory = Category::factory()->income()->create();

    $this->actingAs($user)
        ->post(route('bills.store'), [
            'title' => 'Rent',
            'amount' => 100,
            'currency' => 'toman',
            'category_id' => $incomeCategory->id,
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 1,
        ])
        ->assertSessionHasErrors('category_id');
});

test('syncPending skips the update instead of crashing when the recomputed date collides with another occurrence', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 10));

    try {
        $user = User::factory()->create();

        $bill = $user->bills()->create([
            'title' => 'Rent',
            'amount' => 100,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 12,
        ]);

        // A paid occurrence already sitting on the date that recomputing "due
        // day 12" from today would land on (2026-07-12).
        $bill->occurrences()->create([
            'due_date' => '2026-07-12',
            'paid_at' => now(),
        ]);

        $pending = $bill->occurrences()->create(['due_date' => '2026-07-20']);

        app(SyncBillOccurrence::class)->syncPending($bill);

        // Update did not throw, and the pending occurrence was left alone
        // since updating it to 2026-07-12 would collide with the paid one.
        expect($pending->fresh()->due_date->toDateString())->toBe('2026-07-20');
    } finally {
        Carbon::setTestNow();
    }
});
