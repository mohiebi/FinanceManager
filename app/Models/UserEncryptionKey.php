<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's wrapped data key.
 *
 * `wrapped_dek_server` being NULL is the vault-armed state: the server has no way
 * back to the DEK and can no longer read that user's encrypted columns.
 */
#[Fillable([
    'user_id',
    'version',
    'wrapped_dek_server',
    'wrapped_dek_passphrase',
    'wrapped_dek_recovery',
    'kdf',
    'kdf_iterations',
    'kdf_salt',
    'recovery_salt',
    'dek_fingerprint',
    'vault_enabled_at',
    'recovery_key_issued_at',
    'recovery_key_acknowledged_at',
])]
#[Hidden(['wrapped_dek_server', 'wrapped_dek_passphrase', 'wrapped_dek_recovery'])]
class UserEncryptionKey extends Model
{
    /**
     * @return BelongsTo<User, UserEncryptionKey>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vaultIsArmed(): bool
    {
        return $this->wrapped_dek_server === null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'kdf_iterations' => 'integer',
            'vault_enabled_at' => 'datetime',
            'recovery_key_issued_at' => 'datetime',
            'recovery_key_acknowledged_at' => 'datetime',
        ];
    }
}
