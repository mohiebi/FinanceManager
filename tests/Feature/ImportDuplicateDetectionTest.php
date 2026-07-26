<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * The import duplicate check compares amount and title in PHP, because both are
 * encrypted with a fresh IV per write and a SQL equality against them can never
 * match. These tests pin that comparison.
 */
function duplicateCsv(string ...$rows): UploadedFile
{
    $csv = implode("\n", [
        'occurred_at,type,category,amount,currency,title,description',
        ...$rows,
    ]);

    return UploadedFile::fake()->createWithContent('transactions.csv', $csv);
}

function previewDuplicateCount(User $user, UploadedFile $file): int
{
    test()->actingAs($user)
        ->post(route('transactions.imports.preview'), ['file' => $file])
        ->assertRedirect();

    return session('transaction_import_preview')['summary']['duplicate'];
}

function existingRent(User $user): Transaction
{
    return $user->transactions()->create([
        'type' => 'cost',
        'amount' => 1250.00,
        'currency' => 'toman',
        'title' => 'Rent',
        'occurred_at' => '2026-07-01',
    ]);
}

test('a row identical to an existing transaction is flagged as a duplicate', function () {
    $user = User::factory()->create();
    Category::factory()->cost()->create(['name' => 'Housing']);
    existingRent($user);

    // "1250" against a stored "1250.00" — the old SQL check relied on the database
    // coercing these, so the PHP comparison has to normalise the scale itself.
    expect(previewDuplicateCount($user, duplicateCsv('2026-07-01,cost,Housing,1250,toman,Rent,')))->toBe(1);
});

test('an amount that differs by a cent is not a duplicate', function () {
    $user = User::factory()->create();
    Category::factory()->cost()->create(['name' => 'Housing']);
    existingRent($user);

    expect(previewDuplicateCount($user, duplicateCsv('2026-07-01,cost,Housing,1250.01,toman,Rent,')))->toBe(0);
});

test('a different title on the same day and amount is not a duplicate', function () {
    $user = User::factory()->create();
    Category::factory()->cost()->create(['name' => 'Housing']);
    existingRent($user);

    expect(previewDuplicateCount($user, duplicateCsv('2026-07-01,cost,Housing,1250,toman,Electricity,')))->toBe(0);
});

test('another user identical transaction does not count as a duplicate', function () {
    $user = User::factory()->create();
    Category::factory()->cost()->create(['name' => 'Housing']);
    existingRent(User::factory()->create());

    expect(previewDuplicateCount($user, duplicateCsv('2026-07-01,cost,Housing,1250,toman,Rent,')))->toBe(0);
});

test('a crowded candidate day still resolves correctly', function () {
    $user = User::factory()->create();
    Category::factory()->cost()->create(['name' => 'Housing']);

    // Same day, type and currency, so every one of these is a SQL candidate and
    // only the PHP comparison can tell them apart.
    foreach (range(1, 40) as $i) {
        Transaction::factory()->cost()->create([
            'user_id' => $user->id,
            'amount' => 1000 + $i,
            'currency' => 'toman',
            'title' => "Item {$i}",
            'occurred_at' => '2026-07-01',
        ]);
    }

    $count = previewDuplicateCount($user, duplicateCsv(
        '2026-07-01,cost,Housing,1007,toman,Item 7,',
        '2026-07-01,cost,Housing,9999,toman,Brand new,',
    ));

    expect($count)->toBe(1);
});
