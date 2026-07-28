<?php

namespace App\Casts;

use App\Concerns\OwnsEncryptedAttributes;
use App\Contracts\HasEncryptionOwner;
use App\Support\Encryption\EncryptedValue;
use App\Support\Encryption\UserCrypto;
use App\Support\Encryption\UserKeyRing;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Encrypts an attribute under the owning user's data key.
 *
 * Usage in casts():
 *   'title'  => UserEncrypted::class,
 *   'amount' => UserEncrypted::class.':decimal,2',
 *   'meta'   => UserEncrypted::class.':json',
 *
 * Setting only *encodes*; the actual encryption happens at save time via
 * {@see OwnsEncryptedAttributes}. That is deliberate: Eloquent fills
 * attributes before it sets a relation's foreign key, so during the idiomatic
 * `$user->bills()->create([...])` the owning user is simply not known yet. Waiting
 * until save is the only point where `user_id` is authoritative.
 *
 * Reads degrade rather than throw — when the owner's vault is armed the server has
 * no key, so `get()` hands back an {@see EncryptedValue} and the page still renders
 * for client-side decryption.
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

        // Set but not yet saved, so still plaintext. Reading back an attribute you
        // just assigned must return what you assigned.
        if (! UserCrypto::looksEncrypted((string) $value)) {
            return $this->decode((string) $value);
        }

        $ownerId = $model instanceof HasEncryptionOwner
            ? $model->encryptionOwnerId()
            : null;

        $dek = $ownerId === null ? null : app(UserKeyRing::class)->for($ownerId);

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

        return [$key => $this->encode($value)];
    }

    public function encode(mixed $value): string
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
