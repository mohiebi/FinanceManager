<?php

use App\Actions\Features\UpdateUserFeature;
use App\Actions\Vault\ArmVault;
use App\Enums\AssetType;
use App\Enums\Feature;
use App\Models\Bill;
use App\Models\Category;
use App\Models\InvestmentAsset;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Encryption\UserCrypto;
use App\Support\Encryption\UserKeyRing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * How the app behaves for a user whose server cannot read their data.
 */
function armDegradedVault(User $user): string
{
    $dek = app(ArmVault::class)->enroll($user);
    $kek = random_bytes(32);
    $aad = UserCrypto::aadFor('vault', 'dek');

    app(ArmVault::class)->arm($user, [
        'wrapped_passphrase' => UserCrypto::encrypt($dek, $kek, $aad),
        'wrapped_recovery' => UserCrypto::encrypt($dek, $kek, $aad),
        'kdf' => 'pbkdf2-sha256',
        'kdf_iterations' => 600000,
        'kdf_salt' => base64_encode(random_bytes(16)),
        'recovery_salt' => base64_encode(random_bytes(16)),
        'fingerprint' => hash('sha256', base64_decode($dek, true)),
    ]);

    $user->forgetFeatureSet();

    return $dek;
}

/** A plaintext bill, written before the vault takes the server's key away. */
function makePlaintextBill(User $user): Bill
{
    return $user->bills()->create([
        'title' => 'Rent',
        'amount' => 18500000,
        'currency' => 'toman',
        'recurrence_type' => 'monthly',
        'due_day_of_month' => 5,
    ]);
}

/** Encrypt as the browser would, under the user's own key. */
function clientEncrypt(User $user, string $dek, string $field, string $value, string $table = 'transactions'): string
{
    return UserCrypto::encrypt(
        $value,
        base64_decode($dek, true),
        UserCrypto::aadFor($table, $field),
    );
}

test('a client-encrypted transaction is accepted and stored verbatim', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->create();
    $dek = armDegradedVault($user);

    $amount = clientEncrypt($user, $dek, 'amount', '1250.00');
    $title = clientEncrypt($user, $dek, 'title', 'Therapy session');

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'cost',
            'category_id' => $category->id,
            'amount' => $amount,
            'currency' => 'toman',
            'title' => $title,
            'occurred_at' => '2026-07-25',
        ])
        ->assertRedirect();

    $stored = DB::table('transactions')->where('user_id', $user->id)->first();

    // Stored exactly as the browser wrapped it — the server never re-encrypts,
    // because it has no key to do so with.
    expect($stored->amount)->toBe($amount)
        ->and($stored->title)->toBe($title);
});

test('plaintext is refused while the vault is armed', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->create();
    armDegradedVault($user);

    // The server cannot validate an amount it cannot read, but it can insist the
    // client actually encrypted it — otherwise plaintext would sit unencrypted in
    // a column everything else treats as ciphertext.
    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'cost',
            'category_id' => $category->id,
            'amount' => '1250.00',
            'currency' => 'toman',
            'title' => 'Plain title',
            'occurred_at' => '2026-07-25',
        ])
        ->assertSessionHasErrors(['amount', 'title']);

    expect(Transaction::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('an empty optional field is accepted rather than told to encrypt nothing', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->create();
    $dek = armDegradedVault($user);

    // `nullable` only short-circuits on null, so a blank description would
    // otherwise fail the "must be encrypted" check and block every transaction
    // that has no note.
    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'cost',
            'category_id' => $category->id,
            'amount' => clientEncrypt($user, $dek, 'amount', '10.00'),
            'currency' => 'toman',
            'title' => clientEncrypt($user, $dek, 'title', 'No note'),
            'description' => '',
            'occurred_at' => '2026-07-25',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Transaction::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('a normal user still sends plaintext and gets numeric validation', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->create();

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'cost',
            'category_id' => $category->id,
            'amount' => 'not a number',
            'currency' => 'toman',
            'title' => 'Lunch',
            'occurred_at' => '2026-07-25',
        ])
        ->assertSessionHasErrors('amount');
});

test('the transactions page ships no computed totals it cannot compute', function () {
    $user = User::factory()->create();

    $user->transactions()->create([
        'type' => 'cost',
        'amount' => 1250.75,
        'currency' => 'toman',
        'title' => 'Therapy',
        'occurred_at' => now()->toDateString(),
    ]);

    armDegradedVault($user);

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // A converted zero would read as "you spent nothing", which is worse
            // than sending nothing at all.
            ->where('summary', null)
            ->where('transactions.costs.0.display_amount', null)
            ->has('rates.tomanPerUsd')
            ->has('rates.tomanPerEur')
            ->where('transactionCount', 1)
        );
});

test('a normal user still gets server-computed totals', function () {
    $user = User::factory()->create();

    $user->transactions()->create([
        'type' => 'cost',
        'amount' => 1250.75,
        'currency' => 'toman',
        'title' => 'Therapy',
        'occurred_at' => now()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.cost', '1250.75')
            ->where('rates', null)
            ->where('transactions.costs.0.display_amount', '1250.75')
        );
});

test('spreadsheet import and export are gated while the vault is armed', function () {
    $user = User::factory()->create();
    armDegradedVault($user);

    // Both are generated server-side; neither survives a server that cannot read.
    $this->actingAs($user)
        ->post(route('transactions.imports.preview'), [
            'file' => UploadedFile::fake()->createWithContent('t.csv', "occurred_at\n2026-07-01\n"),
        ])
        ->assertRedirect();

    $this->actingAs($user)->get(route('transactions.export'))->assertRedirect();

    expect(session('status'))->not->toBeNull();
});

test('import and export work normally without a vault', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('transactions.export'))->assertOk();
});

test('bills and portfolio survive arming the vault', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::Bills, true);
    app(UpdateUserFeature::class)($user, Feature::Portfolio, true);

    armDegradedVault($user);

    // Both have a client-side path now: bills are sealed in the browser before
    // they are submitted, and the portfolio breakdown is computed there.
    $fresh = $user->fresh();

    expect($fresh->hasFeature(Feature::Bills))->toBeTrue()
        ->and($fresh->hasFeature(Feature::Portfolio))->toBeTrue();

    $this->actingAs($fresh)->get(route('bills.index'))->assertOk();
    $this->actingAs($fresh)->get(route('portfolio'))->assertOk();
});

test('the bills page ships ciphertext and rates instead of a total it cannot compute', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::Bills, true);

    $bill = makePlaintextBill($user);
    $bill->occurrences()->create(['due_date' => now()->startOfMonth()->addDays(4)->toDateString()]);

    $ciphertext = DB::table('bills')->where('id', $bill->id)->value('amount');

    armDegradedVault($user);

    $this->actingAs($user->fresh())
        ->get(route('bills.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Bills')
            ->where('bills.0.amount.c', $ciphertext)
            // A converted zero would read as "this bill costs nothing".
            ->where('bills.0.display_amount', null)
            ->where('bills.0.month_occurrence_count', 1)
            ->where('monthlyBillSummary.amount', null)
            ->has('rates')
            ->etc());
});

test('a client-encrypted bill is accepted and stored verbatim', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::Bills, true);
    $dek = armDegradedVault($user);

    $title = clientEncrypt($user, $dek, 'title', 'Rent', 'bills');
    $amount = clientEncrypt($user, $dek, 'amount', '18500000.00', 'bills');

    $this->actingAs($user->fresh())
        ->post(route('bills.store'), [
            'title' => $title,
            'amount' => $amount,
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 5,
        ])
        ->assertRedirect();

    $stored = DB::table('bills')->where('user_id', $user->id)->first();

    expect($stored->title)->toBe($title)
        ->and($stored->amount)->toBe($amount);
});

test('a plaintext bill is refused while the vault is armed', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::Bills, true);
    armDegradedVault($user);

    $this->actingAs($user->fresh())
        ->post(route('bills.store'), [
            'title' => 'Rent',
            'amount' => '18500000',
            'currency' => 'toman',
            'recurrence_type' => 'monthly',
            'due_day_of_month' => 5,
        ])
        ->assertSessionHasErrors(['title', 'amount']);

    expect(DB::table('bills')->where('user_id', $user->id)->count())->toBe(0);
});

test('marking a bill paid stores the transaction the browser sealed', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::Bills, true);

    // Created before the vault is armed — afterwards the server has no key to
    // write one with, which is the whole point of the client path.
    $bill = makePlaintextBill($user);
    $occurrence = $bill->occurrences()->create(['due_date' => '2026-07-05']);

    $dek = armDegradedVault($user);

    // Re-sealed for `transactions`: a ciphertext is bound to its table by the AAD,
    // so the bill's own blobs cannot be reused here.
    $title = clientEncrypt($user, $dek, 'title', 'Rent', 'transactions');
    $amount = clientEncrypt($user, $dek, 'amount', '18500000.00', 'transactions');

    $this->actingAs($user->fresh())
        ->post(route('bills.occurrences.pay', ['bill' => $bill, 'occurrence' => $occurrence]), [
            'title' => $title,
            'amount' => $amount,
        ])
        ->assertRedirect();

    $stored = DB::table('transactions')->where('user_id', $user->id)->first();

    expect($stored->title)->toBe($title)
        ->and($stored->amount)->toBe($amount)
        ->and(DB::table('bill_occurrences')->where('id', $occurrence->id)->value('paid_at'))
        ->not->toBeNull();
});

test('marking a bill paid without a sealed transaction is refused', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::Bills, true);

    $bill = makePlaintextBill($user);
    $occurrence = $bill->occurrences()->create(['due_date' => '2026-07-05']);

    armDegradedVault($user);

    // Without this the server would have to read the bill to build the
    // transaction, and it would write a `•••` amount instead of failing.
    $this->actingAs($user->fresh())
        ->post(route('bills.occurrences.pay', ['bill' => $bill, 'occurrence' => $occurrence]))
        ->assertSessionHasErrors(['title', 'amount']);

    expect(DB::table('bill_occurrences')->where('id', $occurrence->id)->value('paid_at'))->toBeNull();
});

test('the portfolio page ships holdings for the browser to total', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::Portfolio, true);
    $asset = InvestmentAsset::query()->where('slug', AssetType::Gold->value)->firstOrFail();
    $dek = armDegradedVault($user);

    $quantity = clientEncrypt($user, $dek, 'quantity', '2.50000000', 'investments');

    DB::table('investments')->insert([
        'user_id' => $user->id,
        'investment_asset_id' => $asset->id,
        'asset_type' => $asset->slug,
        'quantity' => $quantity,
        'cost_basis' => null,
        'cost_basis_currency' => null,
        'occurred_at' => '2026-07-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user->fresh())
        ->get(route('portfolio'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portfolio')
            // Ciphertext travels; the server never sees a quantity it could sum.
            ->where('vaultPortfolio.entries.0.quantity.c', $quantity)
            ->where('vaultPortfolio.assets.0.id', $asset->id)
            ->has('vaultPortfolio.rates')
            // Present but empty, so <Deferred> resolves instead of spinning forever.
            ->where('assets', [])
            ->where('summary', null));
});

test('portfolio export stays unavailable while the vault is armed', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::Portfolio, true);
    armDegradedVault($user);

    $this->actingAs($user->fresh())->get(route('portfolio.export'))->assertRedirect();
});

test('leaving the vault restores plaintext validation', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->create();
    $dek = armDegradedVault($user);

    app(ArmVault::class)->disarm($user->fresh(), $dek);
    app(UserKeyRing::class)->flush();

    $this->actingAs($user->fresh())
        ->post(route('transactions.store'), [
            'type' => 'cost',
            'category_id' => $category->id,
            'amount' => '42.50',
            'currency' => 'toman',
            'title' => 'Lunch',
            'occurred_at' => '2026-07-25',
        ])
        ->assertRedirect();

    expect(Transaction::query()->where('user_id', $user->id)->get()->first()->amount)->toBe('42.50');
});

test('the dashboard ships the trend rows for the browser to bucket', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-15 12:00:00'));

    try {
        $user = User::factory()->create();

        $user->transactions()->create([
            'type' => 'cost',
            'amount' => 250.50,
            'currency' => 'toman',
            'title' => 'Therapy',
            'occurred_at' => '2026-06-10',
        ]);

        $ciphertext = DB::table('transactions')->where('user_id', $user->id)->value('amount');

        armDegradedVault($user);

        $this->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                // Totals the server cannot compute come back null rather than as a
                // zero the chart would draw as a real, flat month.
                ->where('monthlyTrend.1.cost', null)
                ->where('monthlyTrend.1.income', null)
                ->where('monthlyTrend.1.from', '2026-06-01')
                // The three months the workspace does not load travel as ciphertext.
                ->where('trendTransactions.0.amount.c', $ciphertext)
                ->has('rates.tomanPerUsd')
                ->etc());
    } finally {
        Carbon::setTestNow();
    }
});

test('the report page ships ciphertext and rates instead of totals it cannot compute', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->create();

    $user->transactions()->create([
        'category_id' => $category->id,
        'type' => 'cost',
        'amount' => 1250.75,
        'currency' => 'toman',
        'title' => 'Therapy',
        'occurred_at' => now()->toDateString(),
    ]);

    $ciphertext = DB::table('transactions')->where('user_id', $user->id)->value('amount');

    armDegradedVault($user);

    $this->actingAs($user->fresh())
        ->get(route('report'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Report')
            ->where('transactions.costs.0.amount.c', $ciphertext)
            ->where('transactions.costs.0.display_amount', null)
            ->where('analyticsTransactions.costs.0.display_amount', null)
            ->where('summary.cost', null)
            ->where('summary.income', null)
            // The row count is not a secret, only the money is.
            ->where('summary.count', 1)
            ->has('rates.tomanPerUsd')
            ->has('rates.tomanPerEur')
            ->etc());
});

test('the report page still totals server-side without a vault', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->create();

    $user->transactions()->create([
        'category_id' => $category->id,
        'type' => 'cost',
        'amount' => 1250.75,
        'currency' => 'toman',
        'title' => 'Therapy',
        'occurred_at' => now()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('report'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Report')
            ->where('summary.cost', '1250.75')
            ->where('transactions.costs.0.display_amount', '1250.75')
            ->where('rates', null)
            ->etc());
});
