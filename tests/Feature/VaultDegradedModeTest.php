<?php

use App\Actions\Features\UpdateUserFeature;
use App\Actions\Vault\ArmVault;
use App\Enums\Feature;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Encryption\UserCrypto;
use App\Support\Encryption\UserKeyRing;
use Illuminate\Http\UploadedFile;
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

/** Encrypt as the browser would, under the user's own key. */
function clientEncrypt(User $user, string $dek, string $field, string $value): string
{
    return UserCrypto::encrypt(
        $value,
        base64_decode($dek, true),
        UserCrypto::aadFor('transactions', $field),
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

test('arming the vault also evicts bills', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::Bills, true);

    armDegradedVault($user);

    // Marking a bill paid writes a Transaction server-side, and creating one
    // writes an encrypted title and amount — neither is possible without a key.
    expect($user->fresh()->hasFeature(Feature::Bills))->toBeFalse();
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
