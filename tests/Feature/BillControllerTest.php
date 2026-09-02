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
    $user = User::factory()->withModules()->create();

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
        $user = User::factory()->withModules()->create();

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

test('it localizes default bill category options and labels', function () {
    $user = User::factory()->withModules()->create(['locale' => 'fa']);
    $category = Category::factory()->cost()->create([
        'name' => 'Bills',
        'slug' => 'bills',
    ]);
    $expectedCategoryName = __('finance.categories.cost.bills', [], 'fa');

    $bill = $user->bills()->create([
        'title' => 'Water',
        'amount' => 100,
        'currency' => Currency::Toman->value,
        'category_id' => $category->id,
        'recurrence_type' => 'one_time',
        'due_date' => '2026-07-10',
    ]);
    $bill->occurrences()->create(['due_date' => '2026-07-10']);

    $this->actingAs($user)
        ->get(route('bills.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Bills')
            ->where('categories.0.name', $expectedCategoryName)
            ->where('bills.0.category_name', $expectedCategoryName)
        );
});

test('it creates a recurring bill and generates the first occurrence', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 10));

    try {
        $user = User::factory()->withModules()->create();
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

test('a payment-count limit stops monthly occurrence generation', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 10));

    try {
        $user = User::factory()->withModules()->create();

        $this->actingAs($user)
            ->post(route('bills.store'), [
                'title' => 'Phone installment',
                'amount' => 750000,
                'currency' => 'toman',
                'recurrence_type' => 'monthly',
                'due_day_of_month' => 15,
                'recurrence_limit_type' => 'count',
                'recurrence_count' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $bill = Bill::query()->sole();
        app(SyncBillOccurrence::class)->lookahead($bill, 12);

        expect($bill->recurrence_limit_type->value)->toBe('count')
            ->and($bill->recurrence_count)->toBe(2)
            ->and($bill->occurrences()->orderBy('due_date')->get()->map(
                fn ($occurrence): string => $occurrence->due_date->toDateString(),
            )->all())
            ->toBe(['2026-07-15', '2026-08-15']);
    } finally {
        Carbon::setTestNow();
    }
});

test('an end date is converted into a total payment count and exposed with payment progress', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 10));

    try {
        $user = User::factory()->withModules()->create();

        $this->actingAs($user)
            ->post(route('bills.store'), [
                'title' => 'Course plan',
                'amount' => 100,
                'currency' => 'usd',
                'recurrence_type' => 'monthly',
                'due_day_of_month' => 15,
                'recurrence_limit_type' => 'date',
                'recurrence_end_date' => '2026-10-31',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $bill = Bill::query()->sole();

        expect($bill->recurrence_count)->toBe(4)
            ->and($bill->recurrence_end_date->toDateString())->toBe('2026-10-31');

        $this->actingAs($user)
            ->get(route('bills.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('bills.0.recurrence_limit_type', 'date')
                ->where('bills.0.recurrence_count', 4)
                ->where('bills.0.next_occurrence.payment_number', 1)
                ->where('upcomingOccurrences.0.payment_number', 1)
                ->where('upcomingOccurrences.0.payment_count', 4)
            );
    } finally {
        Carbon::setTestNow();
    }
});

test('an existing bill with occurrences can be given an end date', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 10));

    try {
        $user = User::factory()->withModules()->create();

        $bill = $user->bills()->create([
            'title' => 'Room bill',
            'amount' => 2316000,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 1,
        ]);

        // The search date comes off this row, and a model date cast is a
        // CarbonImmutable — the schedule below it takes the mutable Carbon.
        $bill->occurrences()->create(['due_date' => '2026-08-01']);
        $bill->occurrences()->create(['due_date' => '2026-07-01', 'paid_at' => now()]);

        $this->actingAs($user)
            ->put(route('bills.update', $bill), [
                'title' => 'Room bill',
                'amount' => 2316000,
                'currency' => 'toman',
                'recurrence_type' => 'monthly',
                'due_day_of_month' => 1,
                'recurrence_limit_type' => 'date',
                'recurrence_end_date' => '2026-11-30',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // One payment already made, plus August through November.
        expect($bill->fresh()->recurrence_count)->toBe(5)
            ->and($bill->fresh()->recurrence_end_date->toDateString())->toBe('2026-11-30');
    } finally {
        Carbon::setTestNow();
    }
});

test('paying the final limited occurrence completes the bill', function () {
    $user = User::factory()->withModules()->create();
    $bill = $user->bills()->create([
        'title' => 'Laptop installment',
        'amount' => 100,
        'currency' => 'usd',
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 15,
        'recurrence_limit_type' => 'count',
        'recurrence_count' => 2,
    ]);
    $bill->occurrences()->create([
        'due_date' => '2026-07-15',
        'paid_at' => now()->subMonth(),
    ]);
    $finalOccurrence = $bill->occurrences()->create(['due_date' => '2026-08-15']);

    app(MarkBillOccurrencePaid::class)($bill, $finalOccurrence);

    expect($bill->fresh()->is_active)->toBeFalse()
        ->and($finalOccurrence->fresh()->isPaid())->toBeTrue();
});

test('a finite total cannot be shortened below payments already made', function () {
    $user = User::factory()->withModules()->create();
    $bill = $user->bills()->create([
        'title' => 'Loan',
        'amount' => 100,
        'currency' => 'usd',
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 15,
        'recurrence_limit_type' => 'count',
        'recurrence_count' => 3,
    ]);
    $bill->occurrences()->create(['due_date' => '2026-06-15', 'paid_at' => now()]);
    $bill->occurrences()->create(['due_date' => '2026-07-15', 'paid_at' => now()]);

    $this->actingAs($user)
        ->put(route('bills.update', $bill), [
            'title' => 'Loan',
            'amount' => 100,
            'currency' => 'usd',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 15,
            'recurrence_limit_type' => 'count',
            'recurrence_count' => 1,
        ])
        ->assertSessionHasErrors('recurrence_count');

    expect($bill->fresh()->recurrence_count)->toBe(3);
});

test('updating the due day recomputes the pending occurrence instead of leaving it stale', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 10));

    try {
        $user = User::factory()->withModules()->create();

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

test('updating the due day skips an already paid occurrence and updates the next due date', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 10));

    try {
        $user = User::factory()->withModules()->create();
        $bill = $user->bills()->create([
            'title' => 'Server',
            'amount' => 7.5,
            'currency' => 'usd',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 1,
        ]);

        $bill->occurrences()->create([
            'due_date' => '2026-07-12',
            'paid_at' => now(),
        ]);
        $pending = $bill->occurrences()->create([
            'due_date' => '2026-08-01',
            'reminder_due_day_sent_at' => now(),
        ]);

        $this->actingAs($user)
            ->put(route('bills.update', $bill), [
                'title' => 'Server',
                'amount' => 7.5,
                'currency' => 'usd',
                'recurrence_type' => 'monthly',
                'due_day_of_month' => 12,
            ])
            ->assertRedirect();

        expect($pending->refresh()->due_date->toDateString())->toBe('2026-08-12')
            ->and($pending->reminder_due_day_sent_at)->toBeNull();
    } finally {
        Carbon::setTestNow();
    }
});

test('updating a bill that already matches its due day leaves the occurrence untouched', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 10));

    try {
        $user = User::factory()->withModules()->create();

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
    $user = User::factory()->withModules()->create();

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
    $user = User::factory()->withModules()->create();

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
    $user = User::factory()->withModules()->create();
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
    $owner = User::factory()->withModules()->create();
    $intruder = User::factory()->withModules()->create();

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
    $user = User::factory()->withModules()->create();

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
    $user = User::factory()->withModules()->create();

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
    $owner = User::factory()->withModules()->create();
    $intruder = User::factory()->withModules()->create();
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
    $user = User::factory()->withModules()->create();
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
    $user = User::factory()->withModules()->create();
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

test('syncPending skips a paid collision and moves the pending occurrence to the next cycle', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 10));

    try {
        $user = User::factory()->withModules()->create();

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

        // Update did not throw, and the pending occurrence moved past the
        // already-paid 2026-07-12 occurrence to the next monthly cycle.
        expect($pending->fresh()->due_date->toDateString())->toBe('2026-08-12');
    } finally {
        Carbon::setTestNow();
    }
});

test('the upcoming horizon does not jump a whole month on the 31st', function () {
    // The day before, and the day itself. Plain addMonths() turns 2026-08-31
    // into 2026-10-01, and endOfMonth() then pushed the horizon out to 2026-10-31.
    $horizonFor = function (string $today): array {
        Carbon::setTestNow(Carbon::parse($today.' 09:00:00'));

        try {
            $user = User::factory()->withModules()->create();

            $bill = $user->bills()->create([
                'title' => 'Rent',
                'amount' => 5000000,
                'currency' => Currency::Toman->value,
                'recurrence_type' => 'monthly',
                'due_day_of_month' => 15,
            ]);

            // Comfortably inside next month, and comfortably beyond it.
            $bill->occurrences()->create(['due_date' => '2026-09-15']);
            $bill->occurrences()->create(['due_date' => '2026-10-15']);

            $dueDates = [];

            $this->actingAs($user)
                ->get(route('bills.index'))
                ->assertOk()
                ->assertInertia(function (Assert $page) use (&$dueDates): void {
                    $dueDates = collect($page->toArray()['props']['upcomingOccurrences'] ?? [])
                        ->pluck('due_date')
                        ->all();
                });

            return $dueDates;
        } finally {
            Carbon::setTestNow();
        }
    };

    expect($horizonFor('2026-08-31'))->toBe($horizonFor('2026-08-30'))
        ->and($horizonFor('2026-08-31'))->toContain('2026-09-15')
        ->and($horizonFor('2026-08-31'))->not->toContain('2026-10-15');
});

test('a bill paid within the last 10 days is exposed as recently paid, and one paid earlier is not', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-20 09:00:00'));

    try {
        $user = User::factory()->withModules()->create();

        $recentlyPaidBill = $user->bills()->create([
            'title' => 'Recently paid',
            'amount' => 100,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 15,
        ]);
        $recentlyPaidBill->occurrences()->create([
            'due_date' => '2026-07-15',
            'paid_at' => Carbon::now()->subDays(3),
        ]);

        $stalePaidBill = $user->bills()->create([
            'title' => 'Paid a while ago',
            'amount' => 200,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 10,
        ]);
        $stalePaidBill->occurrences()->create([
            'due_date' => '2026-07-10',
            'paid_at' => Carbon::now()->subDays(15),
        ]);

        $bills = collect();

        $this->actingAs($user)
            ->get(route('bills.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$bills): void {
                $bills = collect($page->toArray()['props']['bills'])->keyBy('title');
            });

        expect($bills['Recently paid']['recent_paid_occurrence']['due_date'])->toBe('2026-07-15')
            ->and($bills['Paid a while ago']['recent_paid_occurrence'])->toBeNull();
    } finally {
        Carbon::setTestNow();
    }
});

test('the due soon summary totals occurrences within the next 30 days, independent of calendar month boundaries', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-06 09:00:00'));

    try {
        $user = User::factory()->withModules()->create();

        $insideWindow = $user->bills()->create([
            'title' => 'Inside window',
            'amount' => 100,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 1,
        ]);
        // 26 days out — inside the 30-day window.
        $insideWindow->occurrences()->create(['due_date' => '2026-08-01']);

        $outsideWindow = $user->bills()->create([
            'title' => 'Outside window',
            'amount' => 300,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 20,
        ]);
        // 45 days out — outside the window, even though it's next calendar month too.
        $outsideWindow->occurrences()->create(['due_date' => '2026-08-20']);

        $this->actingAs($user)
            ->get(route('bills.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Bills')
                ->where('dueSoonSummary.amount', '100.00')
                ->where('dueSoonSummary.count', 1)
            );
    } finally {
        Carbon::setTestNow();
    }
});

test('the due soon summary excludes an occurrence already paid ahead of its due date', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-06 09:00:00'));

    try {
        $user = User::factory()->withModules()->create();

        $bill = $user->bills()->create([
            'title' => 'Paid early',
            'amount' => 100,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 20,
        ]);
        // Due within the window, but already paid — should not count toward
        // "still owed", since the balance already absorbed this expense.
        $bill->occurrences()->create(['due_date' => '2026-07-20', 'paid_at' => now()]);

        $this->actingAs($user)
            ->get(route('bills.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Bills')
                ->where('dueSoonSummary.amount', '0.00')
                ->where('dueSoonSummary.count', 0)
                ->where('bills.0.due_soon_occurrence_count', 0)
            );
    } finally {
        Carbon::setTestNow();
    }
});

test('calendarOccurrences lists every occurrence due this month, paid or not', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-15 09:00:00'));

    try {
        $user = User::factory()->withModules()->create();
        $category = Category::factory()->cost()->create(['color' => '#02CD86']);

        $bill = $user->bills()->create([
            'title' => 'Rent',
            'amount' => 100,
            'currency' => 'toman',
            'category_id' => $category->id,
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 5,
        ]);
        $paid = $bill->occurrences()->create(['due_date' => '2026-07-05', 'paid_at' => now()]);

        $outOfMonthBill = $user->bills()->create([
            'title' => 'Next month only',
            'amount' => 50,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 5,
        ]);
        $outOfMonthBill->occurrences()->create(['due_date' => '2026-08-05']);

        $this->actingAs($user)
            ->get(route('bills.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($paid): void {
                $occurrences = collect($page->toArray()['props']['calendarOccurrences']);

                expect($occurrences->pluck('due_date')->all())->toContain('2026-07-05')
                    ->and($occurrences->pluck('due_date')->all())->not->toContain('2026-08-05');

                $paidRow = $occurrences->firstWhere('id', $paid->id);
                expect($paidRow['is_paid'])->toBeTrue()
                    ->and($paidRow['color'])->toBe('#02CD86');
            });
    } finally {
        Carbon::setTestNow();
    }
});

test('balanceSummary nets this period income minus cost', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-15 09:00:00'));

    try {
        $user = User::factory()->withModules()->create();
        $costCategory = Category::factory()->cost()->create();
        $incomeCategory = Category::factory()->income()->create();

        $user->transactions()->create([
            'category_id' => $incomeCategory->id,
            'type' => 'income',
            'amount' => 1000,
            'currency' => Currency::Toman->value,
            'title' => 'Salary',
            'occurred_at' => '2026-07-01',
        ]);
        $user->transactions()->create([
            'category_id' => $costCategory->id,
            'type' => 'cost',
            'amount' => 400,
            'currency' => Currency::Toman->value,
            'title' => 'Groceries',
            'occurred_at' => '2026-07-10',
        ]);

        $this->actingAs($user)
            ->get(route('bills.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Bills')
                ->where('balanceSummary.balance', 600)
                ->where('balanceSummary.currency', Currency::Toman->value)
            );
    } finally {
        Carbon::setTestNow();
    }
});
