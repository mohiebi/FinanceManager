<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A day the user stated they spent nothing.
 *
 * Sustains a logging streak the way a transaction does, so a genuinely frugal
 * day cannot break the chain.
 */
#[Fillable(['user_id', 'date'])]
class NoSpendDay extends Model
{
    /**
     * @return BelongsTo<User, NoSpendDay>
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
            'date' => 'date:Y-m-d',
        ];
    }
}
