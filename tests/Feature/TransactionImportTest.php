<?php

use App\Actions\Transactions\SaveTransaction;
use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Encryption\UserCrypto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Morilog\Jalali\Jalalian;

function transactionImportFile(string $csv): UploadedFile
{
    return UploadedFile::fake()->createWithContent('transactions.csv', $csv);
}

/** @return array<string, mixed> */
function prepareTransactionImport(User $user, string ...$rows): array
{
    return test()->actingAs($user)->postJson(route('transactions.imports.preview'), [
        'file' => transactionImportFile(implode("\n", [
            'occurred_at,type,category,amount,currency,title,description',
            ...$rows,
        ])),
    ])->assertOk()->json();
}

test('users can preview and import valid english csv transactions', function () {
    $user = User::factory()->create();
    $food = Category::factory()->cost()->create(['name' => 'Food']);
    $salary = Category::factory()->income()->create(['name' => 'Salary']);

    $csv = implode("\n", [
        'occurred_at,type,category,amount,currency,title,description',
        '2026-06-15,cost,Food,125000,toman,Lunch,Optional note',
        '2026-06-16,income,Salary,8500000,toman,Monthly salary,',
    ]);

    $preview = $this->actingAs($user)
        ->postJson(route('transactions.imports.preview'), [
            'file' => transactionImportFile($csv),
        ])
        ->assertOk()->json();

    expect($preview['summary']['total'])->toBe(2)
        ->and($preview['summary']['importable'])->toBe(2)
        ->and($preview['summary']['invalid'])->toBe(0)
        ->and($preview['summary']['duplicate'])->toBe(0);

    $this->actingAs($user)
        ->postJson(route('transactions.imports.store'), ['token' => $preview['token']])
        ->assertOk();

    assertTransactionExists([
        'user_id' => $user->id,
        'category_id' => $food->id,
        'type' => TransactionType::Cost->value,
        'amount' => '125000.00',
        'currency' => Currency::Toman->value,
        'title' => 'Lunch',
        'occurred_at' => '2026-06-15',
    ]);

    assertTransactionExists([
        'user_id' => $user->id,
        'category_id' => $salary->id,
        'type' => TransactionType::Income->value,
        'amount' => '8500000.00',
        'currency' => Currency::Toman->value,
        'title' => 'Monthly salary',
        'occurred_at' => '2026-06-16',
    ]);
});

test('persian csv values normalize digits jalali dates aliases and rial amounts', function () {
    $user = User::factory()->create();
    $salary = Category::factory()->income()->create(['name' => 'Salary']);
    $gregorianDate = Jalalian::fromFormat('Y/m/d', '1403/03/27')->toCarbon()->toDateString();
    $persianDate = "\u{06F1}\u{06F4}\u{06F0}\u{06F3}/\u{06F0}\u{06F3}/\u{06F2}\u{06F7}";
    $persianAmount = "\u{06F8}\u{06F5}\u{06F0}\u{06F0}\u{06F0}\u{06F0}\u{06F0}\u{06F0}";
    $deposit = "\u{0648}\u{0627}\u{0631}\u{06CC}\u{0632}";
    $salaryCategory = "\u{062D}\u{0642}\u{0648}\u{0642}";
    $rial = "\u{0631}\u{06CC}\u{0627}\u{0644}";
    $salaryTitle = "\u{062D}\u{0642}\u{0648}\u{0642} \u{0645}\u{0627}\u{0647}\u{0627}\u{0646}\u{0647}";
    $salaryDescription = "\u{0648}\u{0627}\u{0631}\u{06CC}\u{0632} \u{062D}\u{0642}\u{0648}\u{0642}";

    $csv = implode("\n", [
        'occurred_at,type,category,amount,currency,title,description',
        "{$persianDate},{$deposit},{$salaryCategory},{$persianAmount},{$rial},{$salaryTitle},{$salaryDescription}",
    ]);

    $preview = $this->actingAs($user)
        ->postJson(route('transactions.imports.preview'), [
            'file' => transactionImportFile($csv),
        ])
        ->assertOk()->json();

    expect($preview['summary']['importable'])->toBe(1)
        ->and($preview['rows'][0]['warnings'])->toContain('Rial amount was converted to toman.')
        ->and($preview['rows'][0]['data']['occurred_at'])->toBe($gregorianDate)
        ->and($preview['rows'][0]['data']['amount'])->toBe('8500000.00')
        ->and($preview['rows'][0]['data']['currency'])->toBe(Currency::Toman->value);

    $this->actingAs($user)->postJson(route('transactions.imports.store'), ['token' => $preview['token']]);

    assertTransactionExists([
        'user_id' => $user->id,
        'category_id' => $salary->id,
        'type' => TransactionType::Income->value,
        'amount' => '8500000.00',
        'currency' => Currency::Toman->value,
        'title' => $salaryTitle,
        'occurred_at' => $gregorianDate,
    ]);
});

test('unknown categories are imported as custom user categories', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Category::factory()->cost()->forUser($otherUser)->create(['name' => 'Secret']);

    $csv = implode("\n", [
        'occurred_at,type,category,amount,currency,title,description',
        '2026-06-15,cost,Secret,125000,toman,Hidden category,',
    ]);

    $preview = $this->actingAs($user)
        ->postJson(route('transactions.imports.preview'), [
            'file' => transactionImportFile($csv),
        ])
        ->assertOk()->json();

    expect($preview['summary']['importable'])->toBe(1)
        ->and($preview['summary']['invalid'])->toBe(0)
        ->and($preview['rows'][0]['data']['category_is_new'])->toBeTrue()
        ->and($preview['rows'][0]['warnings'])->toContain('A custom category will be created for this user.');

    $this->actingAs($user)
        ->postJson(route('transactions.imports.store'), ['token' => $preview['token']])
        ->assertOk();

    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'type' => TransactionType::Cost->value,
        'name' => 'Secret',
        'is_default' => false,
    ]);

    expect(Category::query()->where('user_id', $otherUser->id)->where('name', 'Secret')->count())->toBe(1)
        ->and(Category::query()->where('user_id', $user->id)->where('name', 'Secret')->count())->toBe(1);
});

test('duplicate rows are shown in preview and skipped', function () {
    $user = User::factory()->create();
    $food = Category::factory()->cost()->create(['name' => 'Food']);

    Transaction::factory()
        ->cost()
        ->for($user)
        ->for($food)
        ->create([
            'amount' => '125000.00',
            'currency' => Currency::Toman,
            'title' => 'Lunch',
            'occurred_at' => '2026-06-15',
        ]);

    $csv = implode("\n", [
        'occurred_at,type,category,amount,currency,title,description',
        '2026-06-15,cost,Food,125000,toman,Lunch,Optional note',
    ]);

    $preview = $this->actingAs($user)
        ->postJson(route('transactions.imports.preview'), [
            'file' => transactionImportFile($csv),
        ])
        ->assertOk()->json();

    expect($preview['summary']['duplicate'])->toBe(1)
        ->and($preview['summary']['importable'])->toBe(0);

    $this->actingAs($user)
        ->postJson(route('transactions.imports.store'), ['token' => $preview['token']])
        ->assertOk();

    // Filtered after decryption — title is encrypted, so a SQL where would find nothing.
    expect(Transaction::query()->where('user_id', $user->id)->get()->where('title', 'Lunch')->count())->toBe(1);
});

test('final import rechecks duplicates before saving', function () {
    $user = User::factory()->create();
    $food = Category::factory()->cost()->create(['name' => 'Food']);

    $csv = implode("\n", [
        'occurred_at,type,category,amount,currency,title,description',
        '2026-06-15,cost,Food,125000,toman,Lunch,Optional note',
    ]);

    $preview = $this->actingAs($user)
        ->postJson(route('transactions.imports.preview'), [
            'file' => transactionImportFile($csv),
        ])
        ->assertOk()->json();

    Transaction::factory()
        ->cost()
        ->for($user)
        ->for($food)
        ->create([
            'amount' => '125000.00',
            'currency' => Currency::Toman,
            'title' => 'Lunch',
            'occurred_at' => '2026-06-15',
        ]);

    $result = $this->actingAs($user)
        ->postJson(route('transactions.imports.store'), ['token' => $preview['token']])
        ->assertOk()->json();

    expect($result['imported'])->toBe(0)
        ->and($result['skipped_duplicates'])->toBe(1);

    // Filtered after decryption — title is encrypted, so a SQL where would find nothing.
    expect(Transaction::query()->where('user_id', $user->id)->get()->where('title', 'Lunch')->count())->toBe(1);
});

test('confirming a preview again returns its receipt without saving again', function () {
    $user = User::factory()->create();
    $preview = prepareTransactionImport($user, '2026-06-15,cost,New category,125000,toman,Lunch,Private note');
    $payload = DB::table('transaction_imports')->where('id', $preview['token'])->value('payload');

    expect(UserCrypto::looksEncrypted($payload))->toBeTrue()
        ->and($payload)->not->toContain('Lunch', 'Private note');

    $result = $this->postJson(route('transactions.imports.store'), ['token' => $preview['token']])
        ->assertOk()->assertJsonPath('imported', 1)->json();
    $transaction = $user->transactions()->firstOrFail();

    foreach (['title', 'amount', 'description'] as $field) {
        expect(UserCrypto::looksEncrypted($transaction->getRawOriginal($field)))->toBeTrue();
    }

    $this->postJson(route('transactions.imports.store'), ['token' => $preview['token']])
        ->assertOk()->assertExactJson($result);
    expect($user->transactions()->count())->toBe(1);

    $transaction->delete();
    $this->postJson(route('transactions.imports.store'), ['token' => $preview['token']])
        ->assertOk()->assertExactJson($result);
    expect($user->transactions()->count())->toBe(0)
        ->and(DB::table('transaction_imports')->where('id', $preview['token'])->value('payload'))->toBeNull();
});

test('two tabs confirm their own previews in either order', function (bool $reverse) {
    $user = User::factory()->create();
    $first = prepareTransactionImport($user, '2026-06-15,cost,First category,100,toman,First tab,');
    $second = prepareTransactionImport($user, '2026-06-16,cost,Second category,200,toman,Second tab,');

    expect($first['token'])->not->toBe($second['token'])
        ->and(session()->has('transaction_import_rows'))->toBeFalse()
        ->and(session()->has('transaction_import_preview'))->toBeFalse();

    foreach ($reverse ? [$second, $first] : [$first, $second] as $index => $preview) {
        $this->postJson(route('transactions.imports.store'), [
            'token' => $preview['token'],
            'rows' => [['title' => 'Untrusted replacement']],
        ])->assertOk()->assertJsonPath('imported', 1);

        expect($user->transactions()->count())->toBe($index + 1);
        assertTransactionExists(['user_id' => $user->id, 'title' => $preview['rows'][0]['data']['title']]);
    }
})->with([false, true]);

test('a failed row rolls back transactions and new categories and the same preview can be retried', function () {
    $user = User::factory()->create();
    $existing = Category::factory()->cost()->forUser($user)->create(['name' => 'Existing']);
    $preview = prepareTransactionImport($user,
        '2026-06-15,cost,Existing,100,toman,First,',
        '2026-06-16,cost,New category,200,toman,Second,',
    );
    $realSaver = new SaveTransaction;
    $this->mock(SaveTransaction::class, function ($mock) use ($realSaver) {
        $mock->shouldReceive('handle')->twice()->andReturnUsing(function (User $user, array $row) use ($realSaver) {
            $transaction = $realSaver->handle($user, $row);

            if ($row['title'] === 'Second') {
                throw new RuntimeException('Simulated row failure');
            }

            return $transaction;
        });
    });
    $this->withoutExceptionHandling();

    expect(fn () => $this->postJson(route('transactions.imports.store'), ['token' => $preview['token']]))
        ->toThrow(RuntimeException::class, 'Simulated row failure');

    expect($user->transactions()->count())->toBe(0)
        ->and(Category::query()->where('user_id', $user->id)->pluck('id')->all())->toBe([$existing->id]);
    $pending = DB::table('transaction_imports')->where('id', $preview['token'])->first();
    expect((bool) $pending->claimed)->toBeFalse()
        ->and($pending->result)->toBeNull()
        ->and($pending->payload)->not->toBeNull();

    $this->app->instance(SaveTransaction::class, $realSaver);
    $this->postJson(route('transactions.imports.store'), ['token' => $preview['token']])
        ->assertOk()->assertJsonPath('imported', 2)->assertJsonPath('skipped', 0);
    expect($user->transactions()->count())->toBe(2)
        ->and(Category::query()->where('user_id', $user->id)->count())->toBe(2);
});

test('confirmation accounts for invalid and repeated csv rows and duplicates created after preview', function () {
    $user = User::factory()->create();
    $preview = prepareTransactionImport($user,
        '2026-06-15,cost,Food,100,toman,New duplicate,',
        '2026-06-15,cost,Food,100,toman,New duplicate,',
        '2026-06-16,cost,Food,200,toman,Fresh,',
        '2026-06-16,cost,Food,invalid,toman,Bad amount,',
    );
    expect($preview['summary'])->toMatchArray(['total' => 4, 'importable' => 2, 'duplicate' => 1, 'invalid' => 1]);
    Transaction::factory()->cost()->for($user)->create([
        'amount' => 100, 'currency' => Currency::Toman, 'title' => 'New duplicate', 'occurred_at' => '2026-06-15',
    ]);

    $this->postJson(route('transactions.imports.store'), ['token' => $preview['token']])
        ->assertOk()->assertExactJson(['imported' => 1, 'skipped' => 3, 'skipped_duplicates' => 2, 'skipped_invalid' => 1]);
    expect($user->transactions()->count())->toBe(2);
});

test('tokens are required scoped to their owner and expire without importing', function () {
    $owner = User::factory()->create();
    $preview = prepareTransactionImport($owner, '2026-06-15,cost,Food,100,toman,Private,');

    foreach ([[], ['token' => 'invalid'], ['token' => (string) Str::uuid()]] as $data) {
        $this->postJson(route('transactions.imports.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('token');
    }

    $this->actingAs(User::factory()->create())
        ->postJson(route('transactions.imports.store'), ['token' => $preview['token']])
        ->assertUnprocessable()->assertJsonValidationErrors('token');
    expect((bool) DB::table('transaction_imports')->where('id', $preview['token'])->value('claimed'))->toBeFalse();

    $this->travel(1)->days();
    $this->actingAs($owner)->postJson(route('transactions.imports.store'), ['token' => $preview['token']])
        ->assertUnprocessable()->assertJsonValidationErrors('token');
    expect($owner->transactions()->count())->toBe(0);
});

test('scheduled cleanup removes expired previews and receipts while retaining live previews', function () {
    $user = User::factory()->create();
    $expired = prepareTransactionImport($user, '2026-06-15,cost,Food,100,toman,Old,');
    $receipt = prepareTransactionImport($user, '2026-06-16,cost,Food,100,toman,Imported,');
    $this->postJson(route('transactions.imports.store'), ['token' => $receipt['token']])->assertOk();
    $this->travel(1)->days();
    $live = prepareTransactionImport($user, '2026-06-17,cost,Food,100,toman,Live,');

    $event = collect(Schedule::events())->first(fn ($event) => $event->description === 'transactions:prune-imports');
    expect($event)->not->toBeNull();
    $event->run($this->app);

    expect(DB::table('transaction_imports')->pluck('id')->all())->toBe([$live['token']])
        ->and($user->transactions()->count())->toBe(1);
    $this->postJson(route('transactions.imports.store'), ['token' => $expired['token']])->assertUnprocessable();
});
