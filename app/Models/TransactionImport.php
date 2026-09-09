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

#[Fillable([
    'user_id',
    'rows',
    'summary',
    'result',
    'consumed_at',
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
            'result' => 'array',
            'consumed_at' => 'datetime',
        ];
    }
}
