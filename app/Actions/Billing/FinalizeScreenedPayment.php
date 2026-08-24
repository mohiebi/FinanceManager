<?php

namespace App\Actions\Billing;

use App\Enums\DepositAddressStatus;
use App\Enums\GrantReason;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentStatus;
use App\Enums\RiskCaseStatus;
use App\Enums\ScreeningRisk;
use App\Enums\ScreeningStage;
use App\Models\PaymentRiskCase;
use App\Models\PaymentScreening;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\FlaggedAddressDeniedNotification;
use App\Notifications\FlaggedAddressLimitNotification;
use App\Notifications\FlaggedPaymentReviewNotification;
use App\Notifications\PaymentQuarantinedNotification;
use App\Notifications\SubscriptionActivatedNotification;
use App\Notifications\SubscriptionPaymentQuarantinedNotification;
use App\Support\Billing\ScreeningResult;
use Illuminate\Support\Facades\DB;

final readonly class FinalizeScreenedPayment
{
    public function __construct(
        private GrantProAccess $grantProAccess,
        private SettleCouponRedemption $settleCouponRedemption,
        private QueuePaymentSettlement $queuePaymentSettlement,
    ) {}

    public function __invoke(
        SubscriptionPayment $payment,
        ScreeningResult $result,
        ScreeningStage $stage = ScreeningStage::Settlement,
    ): void {
        DB::transaction(function () use ($payment, $result, $stage): void {
            $locked = SubscriptionPayment::query()
                ->with(['user', 'depositAddress'])
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->status !== PaymentStatus::Submitted) {
                return;
            }

            $categories = array_values(array_unique(array_filter(array_map(
                static fn (mixed $category): string => mb_substr(mb_strtolower(trim((string) $category)), 0, 80),
                $result->categories,
            ))));

            PaymentScreening::create([
                'subscription_payment_id' => $locked->getKey(),
                'stage' => $stage,
                'risk' => $result->risk,
                'provider' => mb_substr($result->provider, 0, 80),
                'categories' => $categories,
                'provider_reference' => $result->providerReference === null
                    ? null
                    : mb_substr($result->providerReference, 0, 160),
                'error_code' => $result->errorCode === null
                    ? null
                    : mb_substr($result->errorCode, 0, 80),
                'screened_at' => $result->timestamp(),
            ]);

            $locked->forceFill([
                'screening_risk' => $result->risk,
                'screening_provider' => mb_substr($result->provider, 0, 80),
                'screening_categories' => $categories,
                'screening_reference' => $result->providerReference === null
                    ? null
                    : mb_substr($result->providerReference, 0, 160),
                'screened_at' => $result->timestamp(),
                'failure_reason' => $result->risk === ScreeningRisk::Unknown
                    ? PaymentFailureReason::ScreeningUnavailable
                    : null,
            ])->save();

            if ($result->risk === ScreeningRisk::Unknown) {
                return;
            }

            if ($result->risk === ScreeningRisk::Flagged) {
                $this->openFlaggedCase($locked);

                return;
            }

            if ($result->risk->quarantinesFunds()) {
                $this->quarantine($locked, $result);

                return;
            }

            $locked->forceFill([
                'status' => PaymentStatus::Confirmed,
                'verified_at' => now(),
                'failure_reason' => null,
            ])->save();

            $grant = ($this->grantProAccess)(
                user: $locked->user,
                months: (int) $locked->months,
                reason: GrantReason::Payment,
                paymentId: $locked->getKey(),
            );

            $this->settleCouponRedemption->consume($locked, $grant);

            DB::afterCommit(fn () => $locked->user->notify(
                new SubscriptionActivatedNotification($locked, $grant->pro_until_after)
            ));

            ($this->queuePaymentSettlement)($locked);
        });
    }

    private function openFlaggedCase(SubscriptionPayment $payment): void
    {
        // Serializes this account against itself before the cases are counted.
        // Without it two flagged payments arriving together both read the same
        // count and both open a case, which is how a three-address limit
        // quietly becomes a four-address one.
        User::query()->whereKey($payment->user_id)->lockForUpdate()->first();

        $sourceAddress = $payment->network->normalizeAddress((string) $payment->from_address);
        $wasAlreadyUsed = PaymentRiskCase::query()
            ->where('source_address', $sourceAddress)
            ->exists();
        $caseCount = PaymentRiskCase::query()->where('user_id', $payment->user_id)->count();

        if ($wasAlreadyUsed || $caseCount >= (int) config('billing.risk.max_flagged_addresses_per_user', 3)) {
            $reason = $wasAlreadyUsed
                ? PaymentFailureReason::ReusedFlaggedAddress
                : PaymentFailureReason::FlaggedAddressLimit;

            $payment->forceFill(['status' => PaymentStatus::Failed, 'failure_reason' => $reason])->save();
            $payment->depositAddress?->forceFill(['status' => DepositAddressStatus::Retired])->save();
            $this->settleCouponRedemption->release($payment);
            DB::afterCommit(function () use ($payment, $reason): void {
                $payment->user->notify(new FlaggedAddressDeniedNotification($payment, $reason->value));

                if ($reason === PaymentFailureReason::FlaggedAddressLimit) {
                    $adminEmail = trim((string) config('app.admin_email'));
                    User::query()->where('email', $adminEmail)->first()?->notify(new FlaggedAddressLimitNotification($payment));
                }
            });

            return;
        }

        $case = PaymentRiskCase::query()->create([
            'subscription_payment_id' => $payment->getKey(),
            'user_id' => $payment->user_id,
            'network' => $payment->network,
            'source_address' => $sourceAddress,
            'user_case_number' => $caseCount + 1,
            'status' => RiskCaseStatus::Pending,
            'review_expires_at' => now()->addHours((int) config('billing.risk.review_hours', 48)),
        ]);

        $payment->forceFill(['status' => PaymentStatus::RiskReview, 'failure_reason' => PaymentFailureReason::FlaggedSender])->save();
        $payment->depositAddress?->forceFill(['status' => DepositAddressStatus::RiskReview])->save();
        DB::afterCommit(fn () => $payment->user->notify(new FlaggedPaymentReviewNotification($case)));
    }

    private function quarantine(SubscriptionPayment $payment, ScreeningResult $result): void
    {
        $failureReason = $result->risk === ScreeningRisk::Sanctioned
            ? PaymentFailureReason::SanctionedSender
            : PaymentFailureReason::FlaggedSender;

        $payment->forceFill([
            'status' => PaymentStatus::Quarantined,
            'failure_reason' => $failureReason,
        ])->save();

        $payment->depositAddress?->forceFill([
            'status' => DepositAddressStatus::Quarantined,
            'quarantine_reason' => $failureReason->value,
            'quarantined_at' => now(),
        ])->save();

        $this->settleCouponRedemption->release($payment);

        DB::afterCommit(function () use ($payment): void {
            $payment->user->notify(new SubscriptionPaymentQuarantinedNotification($payment));

            $adminEmail = trim((string) config('app.admin_email'));
            $admin = $adminEmail === ''
                ? null
                : User::query()->where('email', $adminEmail)->first();

            $admin?->notify(new PaymentQuarantinedNotification(
                SubscriptionPayment::query()->where('status', PaymentStatus::Quarantined->value)->count(),
            ));
        });
    }
}
