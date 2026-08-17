<?php

namespace App\Actions\Billing;

use App\Enums\DepositAddressStatus;
use App\Enums\SettlementAsset;
use App\Exceptions\SweepRecordRejected;
use App\Models\DepositAddress;
use App\Models\User;
use App\Services\Billing\SweepTransactionVerifier;
use Illuminate\Support\Facades\DB;

final readonly class RecordDepositSweep
{
    public function __construct(private SweepTransactionVerifier $verifier) {}

    public function __invoke(
        DepositAddress $depositAddress,
        User $admin,
        string $note,
        string $sweepTransactionHash,
        ?string $conversionTransactionHash = null,
    ): void {
        $network = $depositAddress->network;
        $sweepTransactionHash = $network->normalizeTxHash($sweepTransactionHash);
        $conversionTransactionHash = blank($conversionTransactionHash)
            ? null
            : $network->normalizeTxHash((string) $conversionTransactionHash);

        if (preg_match($network->txHashPattern(), $sweepTransactionHash) !== 1) {
            throw new SweepRecordRejected('invalid_sweep_hash');
        }

        if (
            $conversionTransactionHash !== null
            && preg_match($network->txHashPattern(), $conversionTransactionHash) !== 1
        ) {
            throw new SweepRecordRejected('invalid_conversion_hash');
        }

        if ($conversionTransactionHash === $sweepTransactionHash) {
            throw new SweepRecordRejected('invalid_conversion_hash');
        }

        if (mb_strlen(trim($note)) < 3 || mb_strlen($note) > 2000) {
            throw new SweepRecordRejected('invalid_note');
        }

        $depositAddress->loadMissing('payment');

        if (
            $depositAddress->status !== DepositAddressStatus::SweepAuthorized
            || $depositAddress->sweep_authorization_expires_at === null
            || $depositAddress->sweep_authorization_expires_at->isPast()
        ) {
            throw new SweepRecordRejected('authorization_expired');
        }

        $payment = $depositAddress->payment;

        if ($payment === null || ($payment->asset !== SettlementAsset::Eth && $conversionTransactionHash === null)) {
            throw new SweepRecordRejected('conversion_required');
        }

        $rejectionReason = $this->verifier->rejectionReason(
            $depositAddress,
            $sweepTransactionHash,
            $conversionTransactionHash,
        );

        if ($rejectionReason !== null) {
            throw new SweepRecordRejected($rejectionReason);
        }

        DB::transaction(function () use (
            $depositAddress,
            $admin,
            $note,
            $sweepTransactionHash,
            $conversionTransactionHash,
        ): void {
            $locked = DepositAddress::query()->whereKey($depositAddress->getKey())->lockForUpdate()->firstOrFail();

            if (
                $locked->status !== DepositAddressStatus::SweepAuthorized
                || $locked->sweep_authorization_expires_at === null
                || $locked->sweep_authorization_expires_at->isPast()
            ) {
                throw new SweepRecordRejected('authorization_expired');
            }

            $locked->forceFill([
                'status' => DepositAddressStatus::Swept,
                'conversion_tx_hash' => $conversionTransactionHash,
                'sweep_tx_hash' => $sweepTransactionHash,
                'swept_at' => now(),
                'swept_by_admin_id' => $admin->getKey(),
                'sweep_note' => trim($note),
            ])->save();
        });
    }
}
