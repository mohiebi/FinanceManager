<?php

namespace App\Models;

use App\Support\StreakCalculator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The parts of a user's streak that a walk over their dates cannot recover.
 *
 * Deliberately holds no current-run counter — see the table's migration.
 */
#[Fillable(['user_id', 'best_run', 'best_run_ended_on', 'grace_dates', 'last_nudged_on'])]
class UserStreak extends Model
{
    /**
     * How far back forgiven gaps are worth keeping.
     *
     * Must not be shorter than StreakCalculator::WINDOW_DAYS: a date the walk can
     * still reach but that has been pruned from here is a gap the next
     * recomputation is free to forgive differently, which is exactly the
     * non-determinism this column exists to prevent.
     */
    public const GRACE_RETENTION_DAYS = StreakCalculator::WINDOW_DAYS;

    /**
     * @return BelongsTo<User, UserStreak>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Forgiven gap dates as `Y-m-d` strings, oldest first.
     *
     * @return array<int, string>
     */
    public function graceDates(): array
    {
        $dates = $this->grace_dates;

        if (! is_array($dates)) {
            return [];
        }

        $dates = array_values(array_unique(array_filter($dates, 'is_string')));
        sort($dates);

        return $dates;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'best_run' => 'integer',
            'best_run_ended_on' => 'date:Y-m-d',
            'grace_dates' => 'array',
            'last_nudged_on' => 'date:Y-m-d',
        ];
    }
}
