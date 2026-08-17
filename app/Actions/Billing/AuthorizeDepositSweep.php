<?php

namespace App\Actions\Billing;

use App\Contracts\Billing\AddressScreener;
use App\Enums\DepositAddressStatus;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentStatus;
use App\Enums\ScreeningRisk;
use App\Enums\ScreeningStage;
use App\Exceptions\SweepAuthorizationDenied;
use App\Models\DepositAddress;
use App\Models\PaymentScreening;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\PaymentQuarantinedNotification;
use App\Support\Billing\ScreeningResult;
use App\Support\Billing\ScreeningSubject;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class AuthorizeDepositSweep
{
    public function __construct(private AddressScreener $screener) {}

    public function __invoke(DepositAddress $depositAddress, User $admin): void
    {
        $payment = $depositAddress->payment;
        $passedSettlementScreening = $payment?->screening_risk === ScreeningRisk::NoMatch
            || $payment?->screenings()
                ->where('stage', ScreeningStage::Settlement->value)
                ->where('risk', ScreeningRisk::NoMatch->value)
                ->exists() === true;

        if (
            $payment === null
            || $payment->status !== PaymentStatus::Confirmed
            || ! $passedSettlementScreening
            || ! in_array($depositAddress->status, [DepositAddressStatus::Assigned, DepositAddressStatus::SweepAuthorized], true)
        ) {
            throw new SweepAuthorizationDenied('not_eligible');
        }

        $coolingHours = (int) config('billing.sweep.cooling_hours', 72);

        if ($payment->block_timestamp === null || $payment->block_timestamp->isAfter(now()->subHours($coolingHours))) {
            throw new SweepAuthorizationDenied('cooling');
        }

        try {
            $result = blank($payment->from_address)
                ? ScreeningResult::unknown('application', 'missing_sender')
                : $this->screener->screen(ScreeningSubject::fromPayment($payment));
        } catch (Throwable) {
            $result = ScreeningResult::unknown('application', 'provider_exception');
        }

        $outcome = DB::transaction(function () use ($depositAddress, $admin, $result): string {
            $locked = DepositAddress::query()
                ->with('payment')
                ->whereKey($depositAddress->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $payment = $locked->payment;
            $passedSettlementScreening = $payment?->screening_risk === ScreeningRisk::NoMatch
                || $payment?->screenings()
                    ->where('stage', ScreeningStage::Settlement->value)
                    ->where('risk', ScreeningRisk::NoMatch->value)
                    ->exists() === true;

            if (
                $payment === null
                || $payment->status !== PaymentStatus::Confirmed
                || ! $passedSettlementScreening
                || ! in_array($locked->status, [DepositAddressStatus::Assigned, DepositAddressStatus::SweepAuthorized], true)
            ) {
                return 'not_eligible';
            }

            $this->recordScreening($payment, $result);

            if ($result->risk === ScreeningRisk::Unknown) {
                return 'screening_unknown';
            }

            if ($result->risk->quarantinesFunds()) {
                $failureReason = $result->risk === ScreeningRisk::Sanctioned
                    ? PaymentFailureReason::SanctionedSender
                    : PaymentFailureReason::FlaggedSender;

                $locked->forceFill([
                    'status' => DepositAddressStatus::Quarantined,
                    'quarantine_reason' => $failureReason->value,
                    'quarantined_at' => now(),
                    'sweep_authorized_at' => null,
                    'sweep_authorization_expires_at' => null,
                    'sweep_authorized_by_admin_id' => null,
                ])->save();

                DB::afterCommit(function (): void {
                    $adminEmail = trim((string) config('app.admin_email'));
                    $recipient = $adminEmail === '' ? null : User::query()->where('email', $adminEmail)->first();
                    $recipient?->notify(new PaymentQuarantinedNotification(
                        DepositAddress::query()->where('status', DepositAddressStatus::Quarantined->value)->count(),
                    ));
                });

                return 'quarantined';
            }

            $locked->forceFill([
                'status' => DepositAddressStatus::SweepAuthorized,
                'sweep_authorized_at' => now(),
                'sweep_authorization_expires_at' => now()->addMinutes(
                    (int) config('billing.sweep.authorization_minutes', 30),
                ),
                'sweep_authorized_by_admin_id' => $admin->getKey(),
            ])->save();

            return 'authorized';
        });

        if ($outcome !== 'authorized') {
            throw new SweepAuthorizationDenied($outcome);
        }
    }

    private function recordScreening(SubscriptionPayment $payment, ScreeningResult $result): void
    {
        $timestamp = $result->timestamp();
        $categories = array_values(array_unique(array_filter(array_map(
            static fn (mixed $category): string => mb_substr(mb_strtolower(trim((string) $category)), 0, 80),
            $result->categories,
        ))));

        PaymentScreening::create([
            'subscription_payment_id' => $payment->getKey(),
            'stage' => ScreeningStage::Sweep,
            'risk' => $result->risk,
            'provider' => mb_substr($result->provider, 0, 80),
            'categories' => $categories,
            'provider_reference' => $result->providerReference === null
                ? null
                : mb_substr($result->providerReference, 0, 160),
            'error_code' => $result->errorCode === null ? null : mb_substr($result->errorCode, 0, 80),
            'screened_at' => $timestamp,
        ]);

        $payment->forceFill([
            'screening_risk' => $result->risk,
            'screening_provider' => mb_substr($result->provider, 0, 80),
            'screening_categories' => $categories,
            'screening_reference' => $result->providerReference === null
                ? null
                : mb_substr($result->providerReference, 0, 160),
            'screened_at' => $timestamp,
        ])->save();
    }
}
