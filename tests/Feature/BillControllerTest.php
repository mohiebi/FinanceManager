<?php

use App\Models\Bill;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Carbon;

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
