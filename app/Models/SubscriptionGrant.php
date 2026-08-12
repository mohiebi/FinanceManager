<?php

namespace App\Models;

use App\Actions\Billing\GrantProAccess;
use App\Casts\UserEncrypted;
use App\Enums\GrantReason;
use Database\Factories\SubscriptionGrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement of a user's Pro entitlement, written by {@see GrantProAccess}.
 *
 * Append-only: rows are never updated or deleted, so the table reconstructs how
 * a user arrived at their current `pro_until` — which payment paid for it, or
 * which administrator granted it and why.
 *
 * Deliberately not encrypted with {@see UserEncrypted}, unlike the
 * user's own financial records. These are the operator's business records, and
 * a queue worker settling a payment has no browser and no per-user data key —
 * an armed vault would otherwise make a real payment impossible to honour.
 */
#[Fillable([
    'user_id',
    'subscription_payment_id',
    'granted_by_admin_id',
    'months',
    'pro_until_before',
    'pro_until_after',
    'reason',
    'note',
])]
class SubscriptionGrant extends Model
{
    /** @use HasFactory<SubscriptionGrantFactory> */
    use HasFactory, HasUlids;

    /**
     * Append-only, so there is no updated_at to maintain.
     */
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, SubscriptionGrant>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, SubscriptionGrant>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_admin_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'months' => 'integer',
            'pro_until_before' => 'datetime',
            'pro_until_after' => 'datetime',
            'reason' => GrantReason::class,
        ];
    }
}
