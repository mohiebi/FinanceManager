<?php

use App\Actions\Transactions\SaveTransaction;
use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

function transactionImportFile(string $csv): UploadedFile
{
    return UploadedFile::fake()->createWithContent('transactions.csv', $csv);
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

    $this->actingAs($user)
        ->post(route('transactions.imports.preview'), [
            'file' => transactionImportFile($csv),
        ])
        ->assertRedirect();

    $preview = session('transaction_import_preview');

    expect($preview['summary']['total'])->toBe(2)
        ->and($preview['summary']['importable'])->toBe(2)
        ->and($preview['summary']['invalid'])->toBe(0)
        ->and($preview['summary']['duplicate'])->toBe(0)
        ->and(DB::table('transaction_imports')->where('id', $preview['id'])->value('rows'))
        ->not->toContain('Lunch');

    $this->actingAs($user)
        ->post(route('transactions.imports.store'), ['preview_id' => $preview['id']])
        ->assertRedirect(route('transactions.index'));

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

    $this->actingAs($user)
        ->post(route('transactions.imports.preview'), [
            'file' => transactionImportFile($csv),
        ])
        ->assertRedirect();

    $preview = session('transaction_import_preview');

    expect($preview['summary']['importable'])->toBe(1)
        ->and($preview['rows'][0]['warnings'])->toContain('Rial amount was converted to toman.')
        ->and($preview['rows'][0]['data']['occurred_at'])->toBe($gregorianDate)
        ->and($preview['rows'][0]['data']['amount'])->toBe('8500000.00')
        ->and($preview['rows'][0]['data']['currency'])->toBe(Currency::Toman->value);

    $this->actingAs($user)->post(route('transactions.imports.store'), ['preview_id' => $preview['id']]);

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

    $this->actingAs($user)
        ->post(route('transactions.imports.preview'), [
            'file' => transactionImportFile($csv),
        ])
        ->assertRedirect();

    $preview = session('transaction_import_preview');

    expect($preview['summary']['importable'])->toBe(1)
        ->and($preview['summary']['invalid'])->toBe(0)
        ->and($preview['rows'][0]['data']['category_is_new'])->toBeTrue()
        ->and($preview['rows'][0]['warnings'])->toContain('A custom category will be created for this user.');

    $this->actingAs($user)
        ->post(route('transactions.imports.store'), ['preview_id' => $preview['id']])
        ->assertRedirect(route('transactions.index'));

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

    $this->actingAs($user)
        ->post(route('transactions.imports.preview'), [
            'file' => transactionImportFile($csv),
        ])
        ->assertRedirect();

    $preview = session('transaction_import_preview');

    expect($preview['summary']['duplicate'])->toBe(1)
        ->and($preview['summary']['importable'])->toBe(0);

    $this->actingAs($user)
        ->post(route('transactions.imports.store'), ['preview_id' => $preview['id']])
        ->assertRedirect(route('transactions.index'));

    $result = session('transaction_import_result');

    expect($result['imported'])->toBe(0)
        ->and($result['skipped'])->toBe(1)
        ->and($result['skipped_duplicates'])->toBe(1);

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

    $this->actingAs($user)
        ->post(route('transactions.imports.preview'), [
            'file' => transactionImportFile($csv),
        ])
        ->assertRedirect();

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

    $preview = session('transaction_import_preview');

    $this->actingAs($user)
        ->post(route('transactions.imports.store'), ['preview_id' => $preview['id']])
        ->assertRedirect(route('transactions.index'));

    $result = session('transaction_import_result');

    expect($result['imported'])->toBe(0)
        ->and($result['skipped'])->toBe(1)
        ->and($result['skipped_duplicates'])->toBe(1);

    // Filtered after decryption — title is encrypted, so a SQL where would find nothing.
    expect(Transaction::query()->where('user_id', $user->id)->get()->where('title', 'Lunch')->count())->toBe(1);
});

test('replaying a confirmation imports its preview only once', function () {
    $user = User::factory()->create();
    Category::factory()->cost()->create(['name' => 'Food']);
    $csv = implode("\n", [
        'occurred_at,type,category,amount,currency,title,description',
        '2026-06-15,cost,Food,125000,toman,Lunch,',
    ]);

    $this->actingAs($user)
        ->post(route('transactions.imports.preview'), ['file' => transactionImportFile($csv)])
        ->assertRedirect();

    $previewId = session('transaction_import_preview')['id'];

    $this->actingAs($user)
        ->post(route('transactions.imports.store'), ['preview_id' => $previewId])
        ->assertRedirect(route('transactions.index'));

    $firstResult = session('transaction_import_result');

    $this->actingAs($user)
        ->post(route('transactions.imports.store'), ['preview_id' => $previewId])
        ->assertRedirect(route('transactions.index'));

    expect(session('transaction_import_result'))->toBe($firstResult)
        ->and($firstResult['imported'])->toBe(1)
        ->and(Transaction::query()->where('user_id', $user->id)->get()->where('title', 'Lunch')->count())->toBe(1);
});

test('different previews remain independently confirmable', function () {
    $user = User::factory()->create();
    Category::factory()->cost()->create(['name' => 'Food']);

    $firstCsv = implode("\n", [
        'occurred_at,type,category,amount,currency,title,description',
        '2026-06-15,cost,Food,125000,toman,First tab,',
    ]);
    $secondCsv = implode("\n", [
        'occurred_at,type,category,amount,currency,title,description',
        '2026-06-16,cost,Food,95000,toman,Second tab,',
    ]);

    $this->actingAs($user)
        ->post(route('transactions.imports.preview'), ['file' => transactionImportFile($firstCsv)]);
    $firstPreviewId = session('transaction_import_preview')['id'];

    $this->actingAs($user)
        ->post(route('transactions.imports.preview'), ['file' => transactionImportFile($secondCsv)]);
    $secondPreviewId = session('transaction_import_preview')['id'];

    expect($firstPreviewId)->not->toBe($secondPreviewId);

    $this->actingAs($user)
        ->post(route('transactions.imports.store'), ['preview_id' => $firstPreviewId])
        ->assertRedirect(route('transactions.index'));

    $this->actingAs($user)
        ->post(route('transactions.imports.store'), ['preview_id' => $secondPreviewId])
        ->assertRedirect(route('transactions.index'));

    $titles = Transaction::query()->where('user_id', $user->id)->get()->pluck('title');

    expect($titles)->toHaveCount(2)
        ->toContain('First tab', 'Second tab');
});

test('users cannot confirm another users preview', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    Category::factory()->cost()->create(['name' => 'Food']);
    $csv = implode("\n", [
        'occurred_at,type,category,amount,currency,title,description',
        '2026-06-15,cost,Food,125000,toman,Private import,',
    ]);

    $this->actingAs($owner)
        ->post(route('transactions.imports.preview'), ['file' => transactionImportFile($csv)]);
    $previewId = session('transaction_import_preview')['id'];

    $this->actingAs($otherUser)
        ->post(route('transactions.imports.store'), ['preview_id' => $previewId])
        ->assertNotFound();

    expect(Transaction::query()->count())->toBe(0);
});

test('a failed row rolls back the entire import and leaves the preview retryable', function () {
    $user = User::factory()->create();
    $csv = implode("\n", [
        'occurred_at,type,category,amount,currency,title,description',
        '2026-06-15,cost,Travel,125000,toman,Train,',
        '2026-06-16,cost,Pets,95000,toman,Vet,',
    ]);

    $this->actingAs($user)
        ->post(route('transactions.imports.preview'), ['file' => transactionImportFile($csv)]);
    $previewId = session('transaction_import_preview')['id'];

    $failingSaveTransaction = new class extends SaveTransaction
    {
        private int $calls = 0;

        public function handle(User $user, array $data, ?Transaction $transaction = null): Transaction
        {
            $this->calls++;

            if ($this->calls === 2) {
                throw new RuntimeException('Simulated row save failure.');
            }

            return parent::handle($user, $data, $transaction);
        }
    };

    $this->app->instance(SaveTransaction::class, $failingSaveTransaction);
    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($user)->post(
        route('transactions.imports.store'),
        ['preview_id' => $previewId],
    ))->toThrow(RuntimeException::class, 'Simulated row save failure.');

    expect(Transaction::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and(Category::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('transaction_imports')->where('id', $previewId)->value('consumed_at'))->toBeNull();

    $this->withExceptionHandling();
    $this->app->forgetInstance(SaveTransaction::class);

    $this->actingAs($user)
        ->post(route('transactions.imports.store'), ['preview_id' => $previewId])
        ->assertRedirect(route('transactions.index'));

    expect(Transaction::query()->where('user_id', $user->id)->count())->toBe(2)
        ->and(Category::query()->where('user_id', $user->id)->count())->toBe(2)
        ->and(session('transaction_import_result')['imported'])->toBe(2);
});
