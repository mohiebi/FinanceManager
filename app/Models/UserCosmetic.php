<?php

namespace App\Models;

use Database\Factories\UserCosmeticFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'key', 'type', 'selected', 'mile_ledger_entry_id', 'acquired_at'])]
class UserCosmetic extends Model
{
    /** @use HasFactory<UserCosmeticFactory> */
    use HasFactory;

    /** @return BelongsTo<User, UserCosmetic> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['selected' => 'boolean', 'acquired_at' => 'datetime'];
    }
}
