<?php

namespace App\Jobs;

use App\Enums\DepositAddressStatus;
use App\Enums\RiskCaseStatus;
use App\Enums\SettlementStatus;
use App\Models\PaymentSettlement;
use App\Models\User;
use App\Notifications\SettlementNeedsAttentionNotification;
use App\Services\Billing\WalletSignerClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ProcessPaymentSettlementJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 20;

    public int $timeout = 45;

    public int $uniqueFor = 300;

    public function __construct(public readonly string $settlementId)
    {
        $this->onQueue('billing');
    }

    public function uniqueId(): string
    {
        return $this->settlementId;
    }

    public function handle(WalletSignerClient $signer): void
    {
        $settlement = PaymentSettlement::query()->with(['payment.depositAddress', 'payment.riskCase'])->find($this->settlementId);

        if ($settlement === null || in_array($settlement->status, [SettlementStatus::Completed, SettlementStatus::Failed], true)) {
            return;
        }

        try {
            $response = in_array($settlement->status, [SettlementStatus::Queued, SettlementStatus::RetryableFailure], true)
                ? $signer->startSettlement($settlement->payment, $settlement->operation_id)
                : $signer->settlement($settlement->operation_id);

            $this->record($settlement, $response);
        } catch (Throwable $exception) {
            $settlement->forceFill([
                'status' => SettlementStatus::RetryableFailure,
                'attempts' => $settlement->attempts + 1,
                'failure_code' => 'signer_unavailable',
                'failure_message' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
            $this->release(60);
        }
    }

    /** @param array<string, mixed> $response */
    private function record(PaymentSettlement $settlement, array $response): void
    {
        $status = SettlementStatus::tryFrom((string) ($response['status'] ?? '')) ?? SettlementStatus::RetryableFailure;

        DB::transaction(function () use ($settlement, $response, $status): void {
            $locked = PaymentSettlement::query()->with(['payment.depositAddress', 'payment.riskCase'])
                ->whereKey($settlement->getKey())->lockForUpdate()->firstOrFail();

            $locked->forceFill([
                'status' => $status,
                'transaction_hashes' => $response['transactionHashes'] ?? $locked->transaction_hashes,
                'gas_used_wei' => $response['gasUsedWei'] ?? $locked->gas_used_wei,
                'quoted_eth' => $response['quotedEth'] ?? $locked->quoted_eth,
                'minimum_eth' => $response['minimumEth'] ?? $locked->minimum_eth,
                'received_eth' => $response['receivedEth'] ?? $locked->received_eth,
                'remaining_token_balance' => $response['remainingTokenBalance'] ?? $locked->remaining_token_balance,
                'remaining_eth_wei' => $response['remainingEthWei'] ?? $locked->remaining_eth_wei,
                'vault_receipt' => $response['vaultReceipt'] ?? $locked->vault_receipt,
                'failure_code' => $response['failureCode'] ?? null,
                'failure_message' => $response['failureReason'] ?? null,
                'attempts' => $locked->attempts + 1,
                'submitted_at' => $locked->submitted_at ?? now(),
                'completed_at' => $status === SettlementStatus::Completed ? now() : null,
            ])->save();

            if ($status === SettlementStatus::Completed) {
                $locked->payment->depositAddress?->forceFill(['status' => DepositAddressStatus::Swept, 'swept_at' => now()])->save();
                $locked->payment->riskCase?->forceFill(['status' => RiskCaseStatus::Settled])->save();
            } elseif ($status === SettlementStatus::NeedsReview) {
                $locked->payment->depositAddress?->forceFill(['status' => DepositAddressStatus::RiskReview])->save();
                $locked->payment->riskCase?->forceFill(['status' => RiskCaseStatus::SettlementFailed])->save();
            } else {
                $locked->payment->depositAddress?->forceFill(['status' => DepositAddressStatus::Settling])->save();
            }
        });

        if (in_array($status, [SettlementStatus::Queued, SettlementStatus::Submitted, SettlementStatus::Processing, SettlementStatus::RetryableFailure], true)) {
            $this->release(30);
        }

        if (in_array($status, [SettlementStatus::NeedsReview, SettlementStatus::RetryableFailure, SettlementStatus::Failed], true)) {
            $this->alertAdmin($settlement->fresh());
        }
    }

    private function alertAdmin(PaymentSettlement $settlement): void
    {
        if (! Cache::add("billing.settlement-alert.{$settlement->getKey()}", true, now()->addMinutes(15))) {
            return;
        }

        $adminEmail = trim((string) config('app.admin_email'));
        User::query()->where('email', $adminEmail)->first()?->notify(new SettlementNeedsAttentionNotification($settlement));
    }
}
