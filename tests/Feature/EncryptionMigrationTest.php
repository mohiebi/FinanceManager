<?php

use App\Models\Bill;
use App\Models\InvestmentAsset;
use App\Models\User;
use App\Support\Encryption\UserCrypto;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Exercises the consolidation migration in both directions.
 *
 * A one-way data migration nobody has ever run backwards is a migration you
 * cannot safely deploy.
 */
function consolidationMigration(): object
{
    return require database_path('migrations/2026_07_26_063928_move_encrypted_columns_to_user_keys.php');
}

function makeBill(User $user, string $amount = '5000000'): Bill
{
    return $user->bills()->create([
        'title' => 'Rent',
        'amount' => $amount,
        'currency' => 'toman',
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 1,
    ]);
}

test('rolling back returns columns to APP_KEY encryption', function () {
    $user = User::factory()->create();
    $bill = makeBill($user);

    consolidationMigration()->down();

    $raw = DB::table('bills')->where('id', $bill->id)->value('amount');

    expect(UserCrypto::looksEncrypted($raw))->toBeFalse()
        ->and(Crypt::decryptString($raw))->toBe('5000000');
});

test('rolling back and forward again preserves the value', function () {
    $user = User::factory()->create();
    $bill = makeBill($user, '1234567');

    $migration = consolidationMigration();
    $migration->down();
    $migration->up();

    $raw = DB::table('bills')->where('id', $bill->id)->value('amount');

    expect(UserCrypto::looksEncrypted($raw))->toBeTrue()
        ->and($bill->fresh()->amount)->toBe('1234567');
});

test('running the migration twice is a no-op', function () {
    $user = User::factory()->create();
    $bill = makeBill($user);

    $before = DB::table('bills')->where('id', $bill->id)->value('amount');

    // Already in the new format, so the skip guard must leave it untouched rather
    // than double-encrypting it into something unreadable.
    consolidationMigration()->up();

    expect(DB::table('bills')->where('id', $bill->id)->value('amount'))->toBe($before)
        ->and($bill->fresh()->amount)->toBe('5000000');
});

test('investment quantities and notes move with it', function () {
    $user = User::factory()->create();
    $asset = InvestmentAsset::query()->whereNull('user_id')->firstOrFail();

    $investment = $user->investments()->create([
        'investment_asset_id' => $asset->id,
        'asset_type' => $asset->slug,
        'quantity' => '2.5',
        'cost_basis' => '1000',
        'note' => 'inherited',
        'occurred_at' => '2026-07-25',
    ]);

    $row = DB::table('investments')->where('id', $investment->id)->first();

    expect(UserCrypto::looksEncrypted($row->quantity))->toBeTrue()
        ->and(UserCrypto::looksEncrypted($row->cost_basis))->toBeTrue()
        ->and(UserCrypto::looksEncrypted($row->note))->toBeTrue()
        ->and($row->note)->not->toContain('inherited');

    $fresh = $investment->fresh();

    expect($fresh->quantity)->toBe('2.5')
        ->and($fresh->cost_basis)->toBe('1000')
        ->and($fresh->note)->toBe('inherited');
});

test('a null column is left alone by the migration', function () {
    $user = User::factory()->create();
    $asset = InvestmentAsset::query()->whereNull('user_id')->firstOrFail();

    $investment = $user->investments()->create([
        'investment_asset_id' => $asset->id,
        'asset_type' => $asset->slug,
        'quantity' => '1',
        'cost_basis' => null,
        'note' => null,
        'occurred_at' => '2026-07-25',
    ]);

    $migration = consolidationMigration();
    $migration->down();
    $migration->up();

    $row = DB::table('investments')->where('id', $investment->id)->first();

    expect($row->cost_basis)->toBeNull()
        ->and($row->note)->toBeNull()
        ->and($investment->fresh()->quantity)->toBe('1');
});
