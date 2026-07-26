<?php

namespace App\Actions\Vault;

use App\Actions\Features\UpdateUserFeature;
use App\Enums\Feature;
use App\Models\User;
use App\Support\Encryption\UserKeyRing;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SensitiveParameter;

/**
 * Moves a user between server-held and user-held keys.
 *
 * Neither direction re-encrypts a single row. The data key is unchanged
 * throughout — only its wrapping moves — so this costs one row update whether the
 * user has fifty transactions or five hundred thousand. That is the whole reason
 * the encryption is enveloped.
 */
class ArmVault
{
    public function __construct(private readonly UpdateUserFeature $updateUserFeature) {}

    /**
     * Hand the browser the data key so it can wrap it under the user's passphrase.
     *
     * The uncomfortable, unavoidable step: the server gives up a key it already
     * holds, over TLS, to a session it already fully trusts. The alternative —
     * the client sending its passphrase so the server derives the wrapping key —
     * is strictly worse.
     */
    public function enroll(User $user): string
    {
        $dek = app(UserKeyRing::class)->for($user->getKey());

        if ($dek === null) {
            throw new RuntimeException('The vault is already armed for this user.');
        }

        return base64_encode($dek);
    }

    /**
     * Destroy the server's copy of the key.
     *
     * Everything the client sends here is opaque to us: two ciphertexts we cannot
     * open, two salts, and a hash. The fingerprint check is the only thing that
     * proves the client wrapped the *right* key — without it a bug or a hostile
     * client could lock the user out of their own rows permanently.
     *
     * @param  array{wrapped_passphrase: string, wrapped_recovery: string, kdf: string, kdf_iterations: int, kdf_salt: string, recovery_salt: string, fingerprint: string}  $wrapping
     */
    public function arm(User $user, array $wrapping): void
    {
        $key = $user->ensureEncryptionKey();

        if ($key->vaultIsArmed()) {
            throw new RuntimeException('The vault is already armed for this user.');
        }

        if (! hash_equals($key->dek_fingerprint, $wrapping['fingerprint'])) {
            throw new RuntimeException('The wrapped key does not match the key on record.');
        }

        DB::transaction(function () use ($user, $key, $wrapping): void {
            $key->forceFill([
                'wrapped_dek_passphrase' => $wrapping['wrapped_passphrase'],
                'wrapped_dek_recovery' => $wrapping['wrapped_recovery'],
                'kdf' => $wrapping['kdf'],
                'kdf_iterations' => $wrapping['kdf_iterations'],
                'kdf_salt' => $wrapping['kdf_salt'],
                'recovery_salt' => $wrapping['recovery_salt'],
                'vault_enabled_at' => now(),
                'recovery_key_issued_at' => now(),
                'recovery_key_acknowledged_at' => now(),
                // The point of no return.
                'wrapped_dek_server' => null,
            ])->save();

            app(UserKeyRing::class)->forget($user->getKey());
            $user->forgetEncryptionKey();

            // Evicts Telegram, the AI assistant and Portfolio — none of them have a
            // browser in the loop, so none can work without a readable server.
            $this->updateUserFeature->force($user, Feature::Vault, true);
        });
    }

    /**
     * Take the key back under server management.
     *
     * A privacy downgrade, and only possible while the client is unlocked: the
     * server cannot recover the key on its own, which is exactly the guarantee
     * being given up here.
     */
    public function disarm(User $user, #[SensitiveParameter] string $dekBase64): void
    {
        $key = $user->ensureEncryptionKey();

        if (! $key->vaultIsArmed()) {
            throw new RuntimeException('The vault is not armed for this user.');
        }

        $dek = base64_decode($dekBase64, true);

        if ($dek === false || ! hash_equals($key->dek_fingerprint, hash('sha256', $dek))) {
            throw new RuntimeException('That is not this account\'s data key.');
        }

        DB::transaction(function () use ($user, $key, $dek): void {
            $key->forceFill([
                'wrapped_dek_server' => Crypt::encryptString(base64_encode($dek)),
                'wrapped_dek_passphrase' => null,
                'wrapped_dek_recovery' => null,
                'kdf_salt' => null,
                'recovery_salt' => null,
                'vault_enabled_at' => null,
                'recovery_key_issued_at' => null,
                'recovery_key_acknowledged_at' => null,
            ])->save();

            app(UserKeyRing::class)->forget($user->getKey());
            $user->forgetEncryptionKey();

            // Telegram, the AI assistant and Portfolio stay off deliberately.
            // Re-enabling an integration behind the user's back would be worse than
            // making them click.
            $this->updateUserFeature->force($user, Feature::Vault, false);
        });
    }
}
