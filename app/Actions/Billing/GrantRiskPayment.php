<?php

namespace App\Actions\Billing;

use App\Enums\GrantReason;
use App\Enums\PaymentStatus;
use App\Enums\RiskCaseStatus;
use App\Enums\SettlementStatus;
use App\Models\PaymentRiskCase;
use App\Models\SubscriptionGrant;
use App\Models\User;
use App\Notifications\FlaggedPaymentGrantedNotification;
use Illuminate\Support\Facades\DB;

final readonly class GrantRiskPayment
{
    public function __construct(
        private GrantProAccess $grantProAccess,
        private SettleCouponRedemption $settleCouponRedemption,
    ) {}

    public function __invoke(PaymentRiskCase $case, User $admin, string $note): void
    {
        DB::transaction(function () use ($case, $admin, $note): void {
            $locked = PaymentRiskCase::query()->with(['payment.user', 'payment.settlement'])
                ->whereKey($case->getKey())->lockForUpdate()->firstOrFail();

            abort_unless($locked->status === RiskCaseStatus::Settled, 409);
            abort_unless($locked->payment->settlement?->status === SettlementStatus::Completed, 409);
            abort_if(SubscriptionGrant::query()->where('subscription_payment_id', $locked->payment->getKey())->exists(), 409);

            $grant = ($this->grantProAccess)(
                user: $locked->payment->user,
                months: (int) $locked->payment->months,
                reason: GrantReason::AdminApprovePayment,
                paymentId: $locked->payment->getKey(),
                admin: $admin,
                note: $note,
            );
            $this->settleCouponRedemption->consume($locked->payment, $grant);
            $locked->payment->forceFill(['status' => PaymentStatus::Confirmed, 'verified_at' => now(), 'failure_reason' => null])->save();
            $locked->forceFill([
                'status' => RiskCaseStatus::Granted,
                'granted_by_admin_id' => $admin->getKey(),
                'granted_at' => now(),
                'grant_note' => $note,
            ])->save();

            DB::afterCommit(fn () => $locked->payment->user->notify(new FlaggedPaymentGrantedNotification($locked)));
        });
    }
}
