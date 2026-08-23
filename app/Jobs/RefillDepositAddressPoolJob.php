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
        $available = DepositAddress::query()
            ->available()
            ->whereNotIn('address', DepositAddress::query()
                ->where('status', '!=', DepositAddressStatus::Available->value)
                ->select('address'))
            ->distinct()
            ->count('address');

        if ($available >= $target) {
            return;
        }

        DB::transaction(function () use ($signer, $target, $available): void {
            $keyVersion = (string) config('billing.signer.key_version', 'v1');
            $highestExistingIndex = DepositAddress::query()->max('derivation_index');
            $state = WalletDerivationState::query()->firstOrCreate(
                ['key_version' => $keyVersion],
                ['next_deposit_index' => $highestExistingIndex === null ? 0 : ((int) $highestExistingIndex) + 1],
            );
            $state = WalletDerivationState::query()->whereKey($state->getKey())->lockForUpdate()->firstOrFail();
            $count = min(250, max(0, $target - $available));

            if ($count === 0) {
                return;
            }

            $batch = $signer->deriveBatch((int) $state->next_deposit_index, $count);

            if (($batch['key_version'] ?? null) !== $keyVersion || count($batch['addresses'] ?? []) !== $count) {
                throw new RuntimeException('Signer returned an invalid derivation batch.');
            }

            foreach ($batch['addresses'] as $derived) {
                if (DepositAddress::query()->where('address', mb_strtolower($derived['address']))->exists()) {
                    throw new RuntimeException('Signer returned a deposit address that has already been used.');
                }

                DepositAddress::query()->create([
                    'key_version' => $keyVersion,
                    'network' => null,
                    'derivation_index' => $derived['index'],
                    'address' => mb_strtolower($derived['address']),
                    'status' => DepositAddressStatus::Available,
                ]);
            }

            $state->forceFill(['next_deposit_index' => (int) $state->next_deposit_index + $count])->save();
        }, 3);
    }
}
