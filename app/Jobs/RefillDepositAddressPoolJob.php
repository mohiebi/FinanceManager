<?php

namespace App\Jobs;

use App\Enums\DepositAddressStatus;
use App\Models\DepositAddress;
use App\Models\WalletDerivationState;
use App\Services\Billing\WalletSignerClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RefillDepositAddressPoolJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;

    public int $uniqueFor = 300;

    public function __construct()
    {
        $this->onQueue('billing');
    }

    public function handle(WalletSignerClient $signer): void
    {
        $target = (int) config('billing.deposit_pool.target', 100);
        $available = DepositAddress::query()->available()->count();

        if ($available >= $target) {
            return;
        }

        DB::transaction(function () use ($signer, $target, $available): void {
            $keyVersion = (string) config('billing.signer.key_version', 'v1');
            $state = WalletDerivationState::query()->firstOrCreate(
                ['key_version' => $keyVersion],
                ['next_deposit_index' => 0],
            );
            $state = WalletDerivationState::query()->whereKey($state->getKey())->lockForUpdate()->firstOrFail();
            $startIndex = $this->startIndexFor($keyVersion, (int) $state->next_deposit_index);
            $count = min(250, max(0, $target - $available));

            if ($count === 0) {
                return;
            }

            $batch = $signer->deriveBatch($startIndex, $count);

            if (($batch['key_version'] ?? null) !== $keyVersion || count($batch['addresses'] ?? []) !== $count) {
                throw new RuntimeException('Signer returned an invalid derivation batch.');
            }

            foreach ($batch['addresses'] as $derived) {
                $index = (int) $derived['index'];

                if ($index < $startIndex || $index >= $startIndex + $count) {
                    throw new RuntimeException('Signer returned a derivation index outside the requested range.');
                }

                // Reaching this now means the signer's key material disagrees
                // with what this table records, because the range above starts
                // beyond every index already stored. That is worth stopping for.
                if (DepositAddress::query()->where('address', mb_strtolower($derived['address']))->exists()) {
                    throw new RuntimeException('Signer returned a deposit address that has already been used.');
                }

                DepositAddress::query()->create([
                    'key_version' => $keyVersion,
                    'network' => null,
                    'derivation_index' => $index,
                    'address' => mb_strtolower($derived['address']),
                    'status' => DepositAddressStatus::Available,
                ]);
            }

            $state->forceFill(['next_deposit_index' => $startIndex + $count])->save();
        }, 3);
    }

    /**
     * The first index this batch may derive.
     *
     * Taken from the addresses table as well as the counter, every run, rather
     * than seeding the counter once when its row is created. Seeding once meant
     * anything that added addresses afterwards left the counter pointing at
     * indices the table already held — `billing:import-deposit-addresses` most
     * of all, which writes rows and knows nothing about this counter. The job
     * then asked the signer to re-derive an address it had already handed out,
     * threw on the duplicate, and rolled back the transaction along with the
     * counter it never got to advance. Every subsequent run repeated that
     * exactly, so the pool drained to empty and checkout stopped with nothing
     * in the logs but the same error once a minute.
     *
     * Reading the floor from the rows themselves means no writer can put the
     * counter out of step, because there is nothing to keep in step with. The
     * counter is still consulted and still wins when it is ahead: indices that
     * were handed out and later deleted must never be derived a second time.
     */
    private function startIndexFor(string $keyVersion, int $storedNextIndex): int
    {
        $highestExistingIndex = DepositAddress::query()
            ->where('key_version', $keyVersion)
            ->max('derivation_index');

        return max(
            $storedNextIndex,
            $highestExistingIndex === null ? 0 : ((int) $highestExistingIndex) + 1,
        );
    }
}
