<?php

namespace App\Models;

use App\Enums\Feature;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A per-user override of a feature's default state.
 *
 * Absence of a row is meaningful: it means the feature sits at its enum default.
 */
#[Fillable(['user_id', 'feature', 'enabled', 'show_promo'])]
class UserFeature extends Model
{
    /**
     * @return BelongsTo<User, UserFeature>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'feature' => Feature::class,
            'enabled' => 'boolean',
            'show_promo' => 'boolean',
        ];
    }
}
