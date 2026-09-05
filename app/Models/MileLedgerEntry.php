<?php

namespace App\Models;

use App\Enums\MilesReason;
use Database\Factories\MileLedgerEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'amount', 'balance_after', 'reason', 'source_type', 'source_id', 'idempotency_key', 'transfer_id', 'metadata'])]
class MileLedgerEntry extends Model
{
    /** @use HasFactory<MileLedgerEntryFactory> */
    use HasFactory, HasUlids;

    public const UPDATED_AT = null;

    /** @return BelongsTo<User, MileLedgerEntry> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, MileLedgerEntry> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_after' => 'integer', 'reason' => MilesReason::class, 'metadata' => 'array'];
    }
}
