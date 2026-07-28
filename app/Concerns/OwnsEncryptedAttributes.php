<?php

namespace App\Concerns;

use App\Casts\UserEncrypted;
use App\Contracts\HasEncryptionOwner;
use App\Exceptions\EncryptionOwnerUnresolved;
use App\Exceptions\VaultLocked;
use App\Support\Encryption\UserCrypto;
use App\Support\Encryption\UserKeyRing;
use Illuminate\Database\Eloquent\Model;

/**
 * Encrypts a model's {@see UserEncrypted} attributes at save time.
 *
 * Save time rather than set time because Eloquent fills attributes *before* it
 * sets a relation's foreign key — so `$user->bills()->create(['title' => ...])`
 * would otherwise reach the cast with no owner. By the time we save, `user_id` is
 * authoritative, which also removes any possibility of encrypting a row under one
 * user's key and storing it against another.
 *
 * @see HasEncryptionOwner
 */
trait OwnsEncryptedAttributes
{
    public static function bootOwnsEncryptedAttributes(): void
    {
        static::saving(function (Model $model): void {
            $model->encryptPendingAttributes();
        });
    }

    public function encryptionOwnerId(): ?int
    {
        // Raw attribute access on purpose: strict mode would throw for a model
        // hydrated by a partial select, and this must stay usable there.
        $ownerId = $this->attributes['user_id'] ?? null;

        return $ownerId === null ? null : (int) $ownerId;
    }

    /**
     * Encrypt any encrypted-cast attribute still sitting in plaintext.
     *
     * @throws EncryptionOwnerUnresolved when the row has no owner to key against
     * @throws VaultLocked when the owner's vault is armed and the server has no key
     */
    public function encryptPendingAttributes(): void
    {
        $pending = $this->pendingEncryptedAttributes();

        if ($pending === []) {
            return;
        }

        $ownerId = $this->encryptionOwnerId();

        if ($ownerId === null) {
            throw new EncryptionOwnerUnresolved(sprintf(
                'Cannot encrypt [%s] on [%s]: the row has no owning user.',
                implode(', ', $pending),
                static::class,
            ));
        }

        $dek = app(UserKeyRing::class)->for($ownerId);

        if ($dek === null) {
            // Refuse rather than degrade. Persisting plaintext into a column
            // everything else treats as ciphertext is the worse failure.
            throw new VaultLocked(sprintf(
                'Cannot write [%s] on [%s]: the vault is armed for user [%d].',
                implode(', ', $pending),
                $this->getTable(),
                $ownerId,
            ));
        }

        foreach ($pending as $key) {
            $this->attributes[$key] = UserCrypto::encrypt(
                (string) $this->attributes[$key],
                $dek,
                UserCrypto::aadFor($this->getTable(), $key),
            );
        }
    }

    /**
     * Encrypted-cast attributes whose current raw value is still plaintext.
     *
     * @return array<int, string>
     */
    private function pendingEncryptedAttributes(): array
    {
        $pending = [];

        foreach ($this->getCasts() as $key => $cast) {
            if (! is_string($cast) || ! str_starts_with($cast, UserEncrypted::class)) {
                continue;
            }

            $value = $this->attributes[$key] ?? null;

            if ($value === null || $value === '' || UserCrypto::looksEncrypted((string) $value)) {
                continue;
            }

            $pending[] = $key;
        }

        return $pending;
    }
}
