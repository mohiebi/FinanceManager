<?php

namespace App\Actions\Billing;

use App\Enums\GrantReason;
use App\Models\SubscriptionGrant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Ends a user's Pro entitlement immediately.
 *
 * The counterpart to {@see GrantProAccess}, and the only other writer of
 * `users.pro_until`. Sets the expiry to now rather than to null, so the row
 * still records that the user was Pro and until when — nulling it would erase
 * the only trace outside the grant log.
 */
final readonly class RevokeProAccess
{
    public function __invoke(User $user, ?User $admin = null, ?string $note = null): SubscriptionGrant
    {
        return DB::transaction(function () use ($user, $admin, $note): SubscriptionGrant {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $before = $locked->pro_until;
            $after = CarbonImmutable::now();

            $locked->forceFill(['pro_until' => $after])->save();

            $user->setAttribute('pro_until', $after);
            $user->syncOriginalAttribute('pro_until');

            return SubscriptionGrant::create([
                'user_id' => $locked->getKey(),
                'granted_by_admin_id' => $admin?->getKey(),
                // Zero rather than a negative span: a revoke removes whatever was
                // left, which is not a fixed number of months.
                'months' => 0,
                'pro_until_before' => $before,
                'pro_until_after' => $after,
                'reason' => GrantReason::AdminRevoke,
                'note' => $note,
            ]);
        });
    }
}
