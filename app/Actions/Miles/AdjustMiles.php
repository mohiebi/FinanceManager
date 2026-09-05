<?php

namespace App\Actions\Miles;

use App\Enums\MilesReason;
use App\Exceptions\InsufficientMiles;
use App\Models\MileLedgerEntry;
use App\Models\MileWallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class AdjustMiles
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __invoke(
        User $user,
        int $amount,
        MilesReason $reason,
        string $idempotencyKey,
        ?Model $source = null,
        array $metadata = [],
        ?string $transferId = null,
        ?string $action = null,
    ): MileLedgerEntry {
        if ($amount === 0) {
            throw new InvalidArgumentException('A Miles adjustment cannot be zero.');
        }

        return DB::transaction(function () use ($user, $amount, $reason, $idempotencyKey, $source, $metadata, $transferId, $action): MileLedgerEntry {
            $existing = MileLedgerEntry::query()
                ->where('user_id', $user->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof MileLedgerEntry) {
                return $existing;
            }

            MileWallet::query()->firstOrCreate(['user_id' => $user->getKey()]);
            $wallet = MileWallet::query()->where('user_id', $user->getKey())->lockForUpdate()->firstOrFail();

            if ($amount < 0 && $wallet->balance < abs($amount)) {
                throw InsufficientMiles::forWallet($wallet, abs($amount), $action ?? $reason->value);
            }

            $wallet->balance += $amount;
            $wallet->lifetime_earned += max(0, $amount);
            $wallet->lifetime_spent += max(0, -$amount);
            $wallet->save();

            return MileLedgerEntry::query()->create([
                'user_id' => $user->getKey(),
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'reason' => $reason,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'idempotency_key' => $idempotencyKey,
                'transfer_id' => $transferId,
                'metadata' => $metadata === [] ? null : $metadata,
            ]);
        }, 3);
    }
}
