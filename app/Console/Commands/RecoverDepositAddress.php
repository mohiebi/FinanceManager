<?php

namespace App\Console\Commands;

use App\Enums\DepositAddressStatus;
use App\Enums\PaymentNetwork;
use App\Models\DepositAddress;
use App\Services\Billing\WalletSignerClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Move funds that arrived somewhere nothing will ever settle.
 *
 * Two cases produce them, and both are ordinary. A buyer pays after their
 * twenty-four hour window has closed, so the intent is expired and the address
 * retired. Or a buyer pays on Ethereum against an intent that named Arbitrum —
 * the same key controls the address on both chains, which is why the money is
 * recoverable at all, but no payment row will ever point at it.
 *
 * Everything lands in the risk vault rather than the main one. Nothing screened
 * these funds, and the point of a segregated vault is that "we did not check"
 * and "we checked and it was clean" do not end up in the same place.
 */
#[Signature('billing:recover-address {address : The deposit address holding the stranded funds} {--network= : Chain to recover on; defaults to the one the address was assigned for} {--reason= : Why this is being recovered, recorded on the signer} {--force : Skip the confirmation prompt}')]
#[Description('Sweep stranded funds from a deposit address into the risk vault')]
class RecoverDepositAddress extends Command
{
    /**
     * Statuses whose funds nothing else is coming for.
     *
     * Assigned, Settling and RiskReview are excluded because the settlement
     * pipeline owns those addresses and would race this. Quarantined is excluded
     * because quarantined funds must never move — that is the entire meaning of
     * the status.
     */
    private const RECOVERABLE = [
        DepositAddressStatus::Retired,
        DepositAddressStatus::Swept,
    ];

    private const TERMINAL = ['completed', 'failed', 'needs_review'];

    public function handle(WalletSignerClient $signer): int
    {
        $address = mb_strtolower(trim((string) $this->argument('address')));

        if (preg_match('/^0x[0-9a-f]{40}$/', $address) !== 1) {
            $this->error('Provide a valid EVM address.');

            return self::FAILURE;
        }

        $depositAddress = DepositAddress::query()->where('address', $address)->first();

        if ($depositAddress === null) {
            $this->error('That address is not in the deposit pool, so this signer cannot derive it.');

            return self::FAILURE;
        }

        if (! in_array($depositAddress->status, self::RECOVERABLE, true)) {
            $this->error("Refusing to recover an address in the {$depositAddress->status->value} state.");
            $this->line('Only retired and already-swept addresses can be recovered. Quarantined funds must never be moved.');

            return self::FAILURE;
        }

        $network = $this->network($depositAddress);

        if ($network === null) {
            return self::FAILURE;
        }

        $reason = trim((string) $this->option('reason')) ?: 'Stranded funds recovered by operator.';

        $this->line("Address:    {$depositAddress->address}");
        $this->line("Derivation: {$depositAddress->key_version} / {$depositAddress->derivation_index}");
        $this->line("Network:    {$network->label()}");
        $this->line('Destination is the risk vault configured on the signer.');

        if (! $this->option('force') && ! $this->confirm('Sweep everything at this address into the risk vault?')) {
            $this->warn('Nothing was recovered.');

            return self::FAILURE;
        }

        // Persisted before the call so a re-run resumes the signer's existing
        // operation instead of opening a second one against the same address.
        if (blank($depositAddress->recovery_operation_id)) {
            $depositAddress->forceFill(['recovery_operation_id' => (string) Str::uuid()])->save();
        }

        $operationId = (string) $depositAddress->recovery_operation_id;

        try {
            $operation = $signer->startRecovery($depositAddress, $network, $operationId, $reason);
        } catch (Throwable $exception) {
            $this->error('The signer refused or could not be reached: '.$exception->getMessage());

            return self::FAILURE;
        }

        $operation = $this->awaitCompletion($signer, $operationId, $operation);
        $status = (string) ($operation['status'] ?? 'unknown');

        foreach ((array) ($operation['transactionHashes'] ?? []) as $stage => $hash) {
            $this->line("  {$stage}: {$hash}");
        }

        if ($status === 'completed') {
            $depositAddress->forceFill([
                'status' => DepositAddressStatus::Recovered,
                'recovered_at' => now(),
            ])->save();

            $this->info('Recovered. The address is marked recovered and will never be assigned again.');

            return self::SUCCESS;
        }

        $this->warn("Operation {$operationId} is {$status}: ".(string) ($operation['failureReason'] ?? 'still running'));
        $this->line('Re-run this command to resume it — the operation id is stored on the address.');

        return self::FAILURE;
    }

    private function network(DepositAddress $depositAddress): ?PaymentNetwork
    {
        $requested = trim((string) $this->option('network'));

        if ($requested !== '') {
            $network = PaymentNetwork::tryFrom(mb_strtolower($requested));

            if ($network === null) {
                $this->error('--network must be ethereum or arbitrum.');

                return null;
            }

            return $network;
        }

        if ($depositAddress->network === null) {
            $this->error('This address was never assigned a network. Pass --network to say which chain the funds are on.');

            return null;
        }

        return $depositAddress->network;
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function awaitCompletion(WalletSignerClient $signer, string $operationId, array $operation): array
    {
        // Operations run one at a time on the signer, so this can legitimately
        // sit behind a settlement for a while before it starts.
        $deadline = now()->addMinutes(10);

        while (! in_array((string) ($operation['status'] ?? ''), self::TERMINAL, true) && now()->lessThan($deadline)) {
            $this->line('  '.(string) ($operation['stage'] ?? 'working').'...');
            sleep(5);

            try {
                $operation = $signer->recovery($operationId);
            } catch (Throwable $exception) {
                $this->warn('  lost contact with the signer: '.$exception->getMessage());

                break;
            }
        }

        return $operation;
    }
}
