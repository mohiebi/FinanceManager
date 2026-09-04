<?php

namespace App\Models;

use App\Enums\ReferralStage;
use Database\Factories\ReferralRewardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['referral_id', 'stage', 'referrer_ledger_entry_id', 'friend_ledger_entry_id', 'awarded_at'])]
class ReferralReward extends Model
{
    /** @use HasFactory<ReferralRewardFactory> */
    use HasFactory;

    /** @return BelongsTo<Referral, ReferralReward> */
    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['stage' => ReferralStage::class, 'awarded_at' => 'datetime'];
    }
}
