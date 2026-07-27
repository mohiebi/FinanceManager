<?php

use App\Actions\Features\UpdateUserFeature;
use App\Actions\Vault\ArmVault;
use App\Enums\Feature;
use App\Exceptions\VaultLocked;
use App\Models\User;
use App\Support\Encryption\UserCrypto;
use App\Support\Encryption\UserKeyRing;
use Illuminate\Support\Facades\DB;

/**
 * Arms a user's vault the way the browser would: take the key, wrap it under
 * something, hand back only the wrappings.
 */
function armVault(User $user): string
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

function confirmPassword(User $user): void
{
    test()->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->unix()]);
}

test('with the vault armed, a page carries ciphertext and no plaintext', function () {
    $user = User::factory()->create();

    $user->transactions()->create([
        'type' => 'cost',
        'amount' => 1250.75,
        'currency' => 'toman',
        'title' => 'Therapy session',
        'occurred_at' => now()->toDateString(),
    ]);

    armVault($user);

    $response = $this->actingAs($user)->get(route('transactions.index'))->assertOk();

    // The property the whole phase exists to deliver.
    expect($response->getContent())->not->toContain('Therapy session')
        ->and($response->getContent())->not->toContain('1250.75')
        ->and($response->getContent())->toContain('__enc');
});

test('arming destroys the server copy and keeps the two wrappings', function () {
    $user = User::factory()->create();

    armVault($user);

    $key = $user->fresh()->encryptionKey;

    expect($key->wrapped_dek_server)->toBeNull()
        ->and($key->wrapped_dek_passphrase)->not->toBeNull()
        ->and($key->wrapped_dek_recovery)->not->toBeNull()
        ->and($key->vault_enabled_at)->not->toBeNull()
        ->and($user->fresh()->vaultIsArmed())->toBeTrue();
});

test('arming re-keys nothing — the rows are untouched', function () {
    $user = User::factory()->create();

    $transaction = $user->transactions()->create([
        'type' => 'cost',
        'amount' => 100,
        'currency' => 'toman',
        'title' => 'Coffee',
        'occurred_at' => now()->toDateString(),
    ]);

    $before = DB::table('transactions')->where('id', $transaction->id)->value('title');

    armVault($user);

    // One row changes: the key. This is the entire argument for envelope
    // encryption, and it must hold regardless of how much data the user has.
    expect(DB::table('transactions')->where('id', $transaction->id)->value('title'))->toBe($before);
});

test('arming evicts only the modules that run without a browser', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::Portfolio, true);
    app(UpdateUserFeature::class)($user, Feature::Bills, true);
    app(UpdateUserFeature::class)($user, Feature::TelegramBot, true);
    app(UpdateUserFeature::class)($user, Feature::AiAssistant, true);

    armVault($user);

    $fresh = $user->fresh();

    expect($fresh->hasFeature(Feature::Vault))->toBeTrue()
        ->and($fresh->hasFeature(Feature::TelegramBot))->toBeFalse()
        ->and($fresh->hasFeature(Feature::AiAssistant))->toBeFalse()
        // Bills, Portfolio and Investments all decrypt in the browser, so none of
        // them needs a readable server.
        ->and($fresh->hasFeature(Feature::Bills))->toBeTrue()
        ->and($fresh->hasFeature(Feature::Portfolio))->toBeTrue()
        ->and($fresh->hasFeature(Feature::Investments))->toBeTrue();
});

test('a wrapping for the wrong key is refused', function () {
    $user = User::factory()->create();
    app(ArmVault::class)->enroll($user);

    expect(fn () => app(ArmVault::class)->arm($user, [
        'wrapped_passphrase' => 'whatever',
        'wrapped_recovery' => 'whatever',
        'kdf' => 'pbkdf2-sha256',
        'kdf_iterations' => 600000,
        'kdf_salt' => base64_encode(random_bytes(16)),
        'recovery_salt' => base64_encode(random_bytes(16)),
        'fingerprint' => hash('sha256', 'not the key'),
    ]))->toThrow(RuntimeException::class);

    // And the server copy survives, so the user is not locked out.
    expect($user->fresh()->encryptionKey->wrapped_dek_server)->not->toBeNull();
});

test('arming twice is refused', function () {
    $user = User::factory()->create();
    armVault($user);

    expect(fn () => app(ArmVault::class)->enroll($user))->toThrow(RuntimeException::class);
});

test('writes are refused while the server has no key', function () {
    $user = User::factory()->create();
    armVault($user);

    expect(fn () => $user->transactions()->create([
        'type' => 'cost',
        'amount' => 100,
        'currency' => 'toman',
        'title' => 'Should not persist',
        'occurred_at' => now()->toDateString(),
    ]))->toThrow(VaultLocked::class);
});

test('disarming restores the server copy and reads resume', function () {
    $user = User::factory()->create();

    $transaction = $user->transactions()->create([
        'type' => 'cost',
        'amount' => 100,
        'currency' => 'toman',
        'title' => 'Coffee',
        'occurred_at' => now()->toDateString(),
    ]);

    $dek = armVault($user);

    app(ArmVault::class)->disarm($user->fresh(), $dek);
    app(UserKeyRing::class)->flush();

    $fresh = $user->fresh();

    expect($fresh->encryptionKey->wrapped_dek_server)->not->toBeNull()
        ->and($fresh->encryptionKey->wrapped_dek_passphrase)->toBeNull()
        ->and($fresh->vaultIsArmed())->toBeFalse()
        ->and($fresh->hasFeature(Feature::Vault))->toBeFalse()
        ->and($transaction->fresh()->title)->toBe('Coffee');
});

test('disarming with the wrong key is refused', function () {
    $user = User::factory()->create();
    armVault($user);

    expect(fn () => app(ArmVault::class)->disarm($user->fresh(), base64_encode(random_bytes(32))))
        ->toThrow(RuntimeException::class);

    expect($user->fresh()->vaultIsArmed())->toBeTrue();
});

test('disarming does not silently switch the evicted modules back on', function () {
    $user = User::factory()->create();
    app(UpdateUserFeature::class)($user, Feature::TelegramBot, true);

    $dek = armVault($user);
    app(ArmVault::class)->disarm($user->fresh(), $dek);

    // Re-enabling a messaging integration without being asked would be worse than
    // making the user click.
    expect($user->fresh()->hasFeature(Feature::TelegramBot))->toBeFalse();
});

test('the vault descriptor is shared once armed and carries nothing secret', function () {
    $user = User::factory()->create();

    expect($user->fresh()->vaultDescriptor())->toBeNull();

    armVault($user);

    $descriptor = $user->fresh()->vaultDescriptor();

    expect($descriptor['armed'])->toBeTrue()
        ->and($descriptor['kdf'])->toBe('pbkdf2-sha256')
        ->and($descriptor['iterations'])->toBe(600000)
        ->and($descriptor)->toHaveKeys(['salt', 'recoverySalt', 'fingerprint', 'wrappedPassphrase', 'wrappedRecovery']);
});

test('enrolling requires a fresh password confirmation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('vault.enroll'))
        ->assertStatus(423);
});

test('enrolling returns the key once the password is confirmed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => now()->unix()])
        ->postJson(route('vault.enroll'))
        ->assertOk();

    // strlen, not toHaveLength: the latter counts multi-byte characters, and 32
    // random bytes rarely spell 32 valid ones.
    expect(strlen(base64_decode($response->json('dek'), true)))->toBe(32);
});

test('the enable endpoint refuses a mismatched fingerprint', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => now()->unix()])
        ->post(route('vault.enable'), [
            'wrapped_passphrase' => 'blob',
            'wrapped_recovery' => 'blob',
            'kdf' => 'pbkdf2-sha256',
            'kdf_iterations' => 600000,
            'kdf_salt' => base64_encode(random_bytes(16)),
            'recovery_salt' => base64_encode(random_bytes(16)),
            'fingerprint' => str_repeat('a', 64),
            'acknowledged_recovery_key' => true,
        ])
        ->assertSessionHasErrors('vault');

    expect($user->fresh()->vaultIsArmed())->toBeFalse();
});

test('the enable endpoint will not accept a weak derivation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => now()->unix()])
        ->post(route('vault.enable'), [
            'wrapped_passphrase' => 'blob',
            'wrapped_recovery' => 'blob',
            'kdf' => 'pbkdf2-sha256',
            'kdf_iterations' => 10,
            'kdf_salt' => base64_encode(random_bytes(16)),
            'recovery_salt' => base64_encode(random_bytes(16)),
            'fingerprint' => str_repeat('a', 64),
            'acknowledged_recovery_key' => true,
        ])
        ->assertSessionHasErrors('kdf_iterations');
});

test('enabling requires the recovery key to have been acknowledged', function () {
    $user = User::factory()->create();

    // The acknowledgement must block the point of no return: after the server copy
    // is destroyed there is no way back without the passphrase or recovery key.
    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => now()->unix()])
        ->post(route('vault.enable'), [
            'wrapped_passphrase' => 'blob',
            'wrapped_recovery' => 'blob',
            'kdf' => 'pbkdf2-sha256',
            'kdf_iterations' => 600000,
            'kdf_salt' => base64_encode(random_bytes(16)),
            'recovery_salt' => base64_encode(random_bytes(16)),
            'fingerprint' => str_repeat('a', 64),
        ])
        ->assertSessionHasErrors('acknowledged_recovery_key');
});
