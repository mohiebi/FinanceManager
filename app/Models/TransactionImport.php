<?php

namespace App\Models;

use App\Casts\UserEncrypted;
use App\Concerns\OwnsEncryptedAttributes;
use App\Contracts\HasEncryptionOwner;
use Database\Factories\TransactionImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A parsed CSV import held between the preview the user approves and the
 * confirmation that writes it. The rows live here rather than in the session
 * because the session table stores its payload unencrypted, and because a
 * per-preview row is what makes a confirmation idempotent and tab-safe.
 *
 * `claimed` is taken by a conditional update, so a second confirmation blocks on
 * it and then reads the committed `result` instead of importing again.
 */
#[Fillable([
    'user_id',
    'rows',
    'summary',
    'claimed',
    'result',
    'expires_at',
])]
class TransactionImport extends Model implements HasEncryptionOwner
{
    /** @use HasFactory<TransactionImportFactory> */
    use HasFactory, HasUlids, OwnsEncryptedAttributes;

    /**
     * @return BelongsTo<User, TransactionImport>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rows' => UserEncrypted::class.':json',
            'summary' => 'array',
            'claimed' => 'boolean',
            'result' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
