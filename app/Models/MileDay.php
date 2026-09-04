<?php

namespace App\Models;

use Database\Factories\MileDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'local_date', 'timezone', 'claim_step', 'claim_miles', 'activity_miles', 'activity_source', 'claimed_at'])]
class MileDay extends Model
{
    /** @use HasFactory<MileDayFactory> */
    use HasFactory;

    /** @return BelongsTo<User, MileDay> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['local_date' => 'date:Y-m-d', 'claim_step' => 'integer', 'claim_miles' => 'integer', 'activity_miles' => 'integer', 'claimed_at' => 'datetime'];
    }
}
