<?php

namespace App\Models;

use Database\Factories\ReferralFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['referrer_id', 'referred_user_id', 'code', 'status', 'review_reason', 'attributed_at'])]
class Referral extends Model
{
    /** @use HasFactory<ReferralFactory> */
    use HasFactory;

    /** @return BelongsTo<User, Referral> */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /** @return BelongsTo<User, Referral> */
    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    /** @return HasMany<ReferralReward, Referral> */
    public function rewards(): HasMany
    {
        return $this->hasMany(ReferralReward::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['attributed_at' => 'datetime'];
    }
}
