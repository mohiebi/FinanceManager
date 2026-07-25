<?php

namespace App\Concerns;

use App\Contracts\HasEncryptionOwner;
use App\Exceptions\EncryptionOwnerMismatch;
use Illuminate\Database\Eloquent\Model;

/**
 * Default ownership resolution for models with per-user encrypted columns, plus
 * the save-time guard that catches encrypting under the wrong user's key.
 *
 * @see HasEncryptionOwner
 */
trait OwnsEncryptedAttributes
{
    /**
     * Which user's key the cast actually encrypted with during this lifecycle.
     *
     * A plain property, not an attribute, so it is never persisted or serialized.
     */
    private ?int $encryptionOwnerUsed = null;

    public static function bootOwnsEncryptedAttributes(): void
    {
        static::saving(function (Model $model): void {
            $model->assertEncryptionOwnerMatches();
        });
    }

    public function encryptionOwnerId(): ?int
    {
        // Raw attribute access on purpose: strict mode would throw for a model
        // hydrated by a partial select, and this must stay usable there.
        $ownerId = $this->attributes['user_id'] ?? null;

        return $ownerId === null ? null : (int) $ownerId;
    }

    public function recordEncryptionOwner(int $ownerId): void
    {
        $this->encryptionOwnerUsed = $ownerId;
    }

    /**
     * Refuse to persist a row encrypted under a different user's key than the one
     * it is about to be saved against.
     *
     * Without this, a request acting for user A that creates a row for user B
     * writes ciphertext nobody can ever read — silent, permanent corruption.
     */
    public function assertEncryptionOwnerMatches(): void
    {
        if ($this->encryptionOwnerUsed === null) {
            return;
        }

        $finalOwner = $this->encryptionOwnerId();

        if ($finalOwner !== null && $finalOwner !== $this->encryptionOwnerUsed) {
            throw new EncryptionOwnerMismatch(sprintf(
                'Refusing to save [%s]: encrypted with user [%d]\'s key but owned by user [%d].',
                static::class,
                $this->encryptionOwnerUsed,
                $finalOwner,
            ));
        }
    }
}
