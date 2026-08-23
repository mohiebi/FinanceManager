<?php

namespace App\Actions\Billing;

use App\Enums\SettlementStatus;
use App\Jobs\ProcessPaymentSettlementJob;
use App\Models\PaymentSettlement;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class QueuePaymentSettlement
{
    public function __invoke(SubscriptionPayment $payment, bool $riskAuthorized = false): PaymentSettlement
    {
        $settlement = PaymentSettlement::query()->firstOrCreate(
            ['subscription_payment_id' => $payment->getKey()],
            [
                'operation_id' => (string) Str::uuid(),
                'status' => SettlementStatus::Queued,
                'risk_authorized' => $riskAuthorized,
            ],
        );

        if ($riskAuthorized && ! $settlement->risk_authorized) {
            $settlement->forceFill(['risk_authorized' => true])->save();
        }

        DB::afterCommit(fn () => ProcessPaymentSettlementJob::dispatch($settlement->getKey()));

        return $settlement;
    }
}
