<?php

namespace App\Models;

use App\Enums\Feature;
use Database\Factories\UserFeatureUnlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'feature', 'mile_ledger_entry_id', 'unlocked_at'])]
class UserFeatureUnlock extends Model
{
    /** @use HasFactory<UserFeatureUnlockFactory> */
    use HasFactory;

    /** @return BelongsTo<User, UserFeatureUnlock> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['feature' => Feature::class, 'unlocked_at' => 'datetime'];
    }
}
