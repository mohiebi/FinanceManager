<?php

namespace App\Models;

use App\Enums\CouponKind;
use App\Enums\CouponRedemptionStatus;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A discount code an administrator issued.
 *
 * Deliberately not encrypted, like the rest of the billing tables: these are the
 * operator's own pricing records rather than anybody's financial data, and the
 * code is meant to be shared.
 *
 * A coupon is never edited once redemptions exist — {@see CouponRedemption}
 * snapshots what each use actually took off, so changing the terms here cannot
 * rewrite what a past buyer received.
 */
#[Fillable([
    'code',
    'kind',
    'percent_off',
    'amount_off_usd',
    'user_id',
    'max_redemptions',
    'max_per_user',
    'valid_until',
    'note',
    'created_by_admin_id',
])]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, Coupon>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, Coupon>
     */
    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }

    /**
     * @return HasMany<CouponRedemption, Coupon>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /** The canonical form of a code, however the buyer typed it. */
    public static function normalizeCode(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }

    /** Whether this code was issued to one particular account. */
    public function isReservedFor(User $user): bool
    {
        return $this->user_id !== null && (int) $this->user_id === (int) $user->getKey();
    }

    public function isPublic(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Uses that still count against the limits.
     *
     * Released rows are excluded: an intent that lapsed unpaid put its claim
     * back into the pool.
     */
    public function claimedCount(): int
    {
        return $this->redemptions()
            ->whereNot('status', CouponRedemptionStatus::Released->value)
            ->count();
    }

    public function claimedCountFor(User $user): int
    {
        return $this->redemptions()
            ->where('user_id', $user->getKey())
            ->whereNot('status', CouponRedemptionStatus::Released->value)
            ->count();
    }

    /**
     * Codes that could still be redeemed by somebody today.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('disabled_at')
            ->where(fn (Builder $inner) => $inner
                ->whereNull('valid_until')
                ->orWhere('valid_until', '>', now()));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => CouponKind::class,
            'percent_off' => 'integer',
            'max_redemptions' => 'integer',
            'max_per_user' => 'integer',
            'valid_until' => 'datetime',
            'disabled_at' => 'datetime',
            // amount_off_usd stays uncast: it is a decimal string, and a numeric
            // cast is the float this whole billing domain avoids.
        ];
    }
}
