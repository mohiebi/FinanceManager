<?php

namespace App\Casts;

use App\Contracts\HasEncryptionOwner;
use App\Exceptions\EncryptionOwnerUnresolved;
use App\Exceptions\VaultLocked;
use App\Support\Encryption\EncryptedValue;
use App\Support\Encryption\UserCrypto;
use App\Support\Encryption\UserKeyRing;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Encrypts an attribute under the owning user's data key.
 *
 * Usage in casts():
 *   'title'  => UserEncrypted::class,
 *   'amount' => UserEncrypted::class.':decimal,2',
 *   'meta'   => UserEncrypted::class.':json',
 *
 * Reads degrade, writes refuse. When the owner's vault is armed the server has no
 * key: `get()` hands back an {@see EncryptedValue} so pages still render for
 * client-side decryption, while `set()` throws, because writing plaintext into a
 * column everything else treats as ciphertext is the worse failure.
 *
 * @implements CastsAttributes<mixed, mixed>
 */
class UserEncrypted implements CastsAttributes
{
    public function __construct(
        private readonly string $type = 'string',
        private readonly ?string $argument = null,
    ) {}

    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $dek = app(UserKeyRing::class)->for($this->ownerFor($model));

        if ($dek === null) {
            return new EncryptedValue((string) $value, $key);
        }

        return $this->decode(
            UserCrypto::decrypt((string) $value, $dek, UserCrypto::aadFor($model->getTable(), $key))
        );
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        // Already wrapped by the browser under the user's own key — store verbatim
        // so the server never handles the plaintext.
        if ($value instanceof EncryptedValue) {
            return [$key => $value->ciphertext()];
        }

        $ownerId = $this->ownerFor($model);
        $dek = app(UserKeyRing::class)->for($ownerId);

        if ($dek === null) {
            throw new VaultLocked(sprintf(
                'Cannot write [%s.%s]: the vault is armed for user [%d].',
                $model->getTable(),
                $key,
                $ownerId,
            ));
        }

        if (method_exists($model, 'recordEncryptionOwner')) {
            $model->recordEncryptionOwner($ownerId);
        }

        return [$key => UserCrypto::encrypt(
            $this->encode($value),
            $dek,
            UserCrypto::aadFor($model->getTable(), $key),
        )];
    }

    private function ownerFor(Model $model): int
    {
        $ownerId = $model instanceof HasEncryptionOwner ? $model->encryptionOwnerId() : null;

        // HasMany::create() calls newInstance($attributes) — which runs this cast —
        // before setForeignAttributesForCreate() sets user_id, so the idiomatic
        // $user->transactions()->create([...]) arrives here with no owner yet.
        $ownerId ??= Auth::id();

        if ($ownerId === null) {
            throw new EncryptionOwnerUnresolved(sprintf(
                'Cannot resolve the encryption owner for [%s]. Set user_id before the '
                .'encrypted attribute, or run inside an authenticated context.',
                $model::class,
            ));
        }

        return (int) $ownerId;
    }

    private function encode(mixed $value): string
    {
        return match ($this->type) {
            'decimal' => number_format((float) $value, (int) ($this->argument ?? 2), '.', ''),
            'json' => (string) json_encode($value),
            default => (string) $value,
        };
    }

    private function decode(string $plaintext): mixed
    {
        return match ($this->type) {
            'json' => json_decode($plaintext, true),
            // Returned as a string, matching Laravel's own decimal cast, so nothing
            // downstream sees a changed shape.
            default => $plaintext,
        };
    }
}
