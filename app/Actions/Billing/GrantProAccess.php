<?php

namespace App\Actions\Billing;

use App\Enums\GrantReason;
use App\Models\SubscriptionGrant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only writer of `users.pro_until`.
 *
 * Everything that can make somebody Pro — a settled payment, an administrator,
 * a console command — funnels through here, so the entitlement can never move
 * without a matching {@see SubscriptionGrant} row explaining why.
 *
 * Deliberately knows nothing about crypto. It takes a payment id as an opaque
 * string rather than a model, which is what keeps the entitlement layer
 * independent of how (or whether) the months were paid for.
 */
final readonly class GrantProAccess
{
    /**
     * Add months to a user's Pro entitlement.
     *
     * Extends from the current expiry when one is still in the future, and from
     * today when it is not — so renewing early never costs the buyer the time
     * they had left, and renewing after lapsing does not silently backdate.
     */
    public function __invoke(
        User $user,
        int $months,
        GrantReason $reason,
        ?string $paymentId = null,
        ?User $admin = null,
        ?string $note = null,
    ): SubscriptionGrant {
        if ($months < 1) {
            throw new InvalidArgumentException('A grant must add at least one month.');
        }

        return DB::transaction(function () use ($user, $months, $reason, $paymentId, $admin, $note): SubscriptionGrant {
            // Locking the user row, not the payment, is what makes two payments
            // settling at the same instant stack instead of one overwriting the
            // other's expiry with a value read before it was written.
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $before = $locked->pro_until;

            $base = $before !== null && $before->isFuture()
                ? CarbonImmutable::parse($before)
                : CarbonImmutable::now();

            // addMonths() would turn 31 January into 3 March. Buyers of a
            // month-long plan get a month.
            $after = $base->addMonthsNoOverflow($months);

            // forceFill, because pro_until is kept out of the model's fillable
            // list: a mass-assignment path into it would hand out free Pro.
            $locked->forceFill(['pro_until' => $after])->save();

            // Keep the caller's instance honest without refetching it, so an
            // isPro() check later in the same request sees the new expiry.
            $user->setAttribute('pro_until', $after);
            $user->syncOriginalAttribute('pro_until');

            return SubscriptionGrant::create([
                'user_id' => $locked->getKey(),
                'subscription_payment_id' => $paymentId,
                'granted_by_admin_id' => $admin?->getKey(),
                'months' => $months,
                'pro_until_before' => $before,
                'pro_until_after' => $after,
                'reason' => $reason,
                'note' => $note,
            ]);
        });
    }
}
