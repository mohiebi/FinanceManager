<?php

use App\Exceptions\DecryptionFailed;
use App\Models\User;
use App\Notifications\BillDueNotification;
use App\Support\Encryption\UserCrypto;
use App\Support\Encryption\UserKeyRing;
use Illuminate\Support\Facades\DB;

/**
 * The property this stage exists to deliver: a database dump reveals nothing.
 */
test('transaction amount, title and description never touch the database in plaintext', function () {
    $user = User::factory()->create();

    $transaction = $user->transactions()->create([
        'type' => 'cost',
        'amount' => 1250.75,
        'currency' => 'toman',
        'title' => 'Therapy session',
        'description' => 'weekly appointment',
        'occurred_at' => '2026-07-25',
    ]);

    $row = DB::table('transactions')->where('id', $transaction->id)->first();

    expect($row->amount)->not->toContain('1250')
        ->and($row->title)->not->toContain('Therapy')
        ->and($row->description)->not->toContain('appointment')
        ->and(UserCrypto::looksEncrypted($row->amount))->toBeTrue()
        ->and(UserCrypto::looksEncrypted($row->title))->toBeTrue()
        ->and(UserCrypto::looksEncrypted($row->description))->toBeTrue();

    // And it still reads back exactly as written, at the same shape the old
    // decimal:2 cast produced.
    $fresh = $transaction->fresh();

    expect($fresh->amount)->toBe('1250.75')
        ->and($fresh->title)->toBe('Therapy session')
        ->and($fresh->description)->toBe('weekly appointment');
});

test('bill titles are encrypted too', function () {
    $user = User::factory()->create();

    $bill = $user->bills()->create([
        'title' => 'Divorce lawyer',
        'amount' => 5000000,
        'currency' => 'toman',
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 1,
    ]);

    $row = DB::table('bills')->where('id', $bill->id)->first();

    expect($row->title)->not->toContain('Divorce')
        ->and(UserCrypto::looksEncrypted($row->title))->toBeTrue()
        ->and($bill->fresh()->title)->toBe('Divorce lawyer');
});

test('two users with the same values produce different ciphertext', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $attributes = [
        'type' => 'cost',
        'amount' => 100,
        'currency' => 'toman',
        'title' => 'Coffee',
        'occurred_at' => '2026-07-25',
    ];

    $aliceRow = $alice->transactions()->create($attributes);
    $bobRow = $bob->transactions()->create($attributes);

    $aliceStored = DB::table('transactions')->where('id', $aliceRow->id)->value('title');
    $bobStored = DB::table('transactions')->where('id', $bobRow->id)->value('title');

    expect($aliceStored)->not->toBe($bobStored);

    // And neither key opens the other's row.
    $aliceKey = app(UserKeyRing::class)->for($alice->id);

    expect(fn () => UserCrypto::decrypt($bobStored, $aliceKey, UserCrypto::aadFor('transactions', 'title')))
        ->toThrow(DecryptionFailed::class);
});

test('an amount survives the round trip at full precision', function () {
    $user = User::factory()->create();

    foreach (['0.01', '999999999.99', '1250.00', '42.50'] as $amount) {
        $transaction = $user->transactions()->create([
            'type' => 'cost',
            'amount' => $amount,
            'currency' => 'toman',
            'title' => 'Probe',
            'occurred_at' => '2026-07-25',
        ]);

        expect($transaction->fresh()->amount)->toBe($amount);
    }
});

test('a null description stays null rather than becoming ciphertext', function () {
    $user = User::factory()->create();

    $transaction = $user->transactions()->create([
        'type' => 'cost',
        'amount' => 100,
        'currency' => 'toman',
        'title' => 'No note',
        'description' => null,
        'occurred_at' => '2026-07-25',
    ]);

    expect(DB::table('transactions')->where('id', $transaction->id)->value('description'))->toBeNull()
        ->and($transaction->fresh()->description)->toBeNull();
});

test('updating an encrypted field re-encrypts rather than storing plaintext', function () {
    $user = User::factory()->create();

    $transaction = $user->transactions()->create([
        'type' => 'cost',
        'amount' => 100,
        'currency' => 'toman',
        'title' => 'Before',
        'occurred_at' => '2026-07-25',
    ]);

    $transaction->update(['title' => 'After']);

    $stored = DB::table('transactions')->where('id', $transaction->id)->value('title');

    expect($stored)->not->toContain('After')
        ->and(UserCrypto::looksEncrypted($stored))->toBeTrue()
        ->and($transaction->fresh()->title)->toBe('After');
});

test('a bill reminder body never carries the bill title or amount', function () {
    $user = User::factory()->create();

    $bill = $user->bills()->create([
        'title' => 'Psychiatrist',
        'amount' => 4500000,
        'currency' => 'toman',
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 1,
    ]);

    $occurrence = $bill->occurrences()->create(['due_date' => '2026-08-01']);

    $user->notify(new BillDueNotification($bill, $occurrence, 'day_before'));

    $data = DB::table('notifications')->where('notifiable_id', $user->id)->value('data');

    expect($data)->not->toContain('Psychiatrist')
        ->and($data)->not->toContain('4500000')
        ->and($data)->not->toContain('4,500,000');
});
