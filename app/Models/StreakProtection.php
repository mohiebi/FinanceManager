<?php

namespace App\Models;

use App\Enums\StreakProtectionType;
use Database\Factories\StreakProtectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'protected_date', 'type', 'mile_ledger_entry_id', 'timezone'])]
class StreakProtection extends Model
{
    /** @use HasFactory<StreakProtectionFactory> */
    use HasFactory;

    /** @return BelongsTo<User, StreakProtection> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['protected_date' => 'date:Y-m-d', 'type' => StreakProtectionType::class];
    }
}
