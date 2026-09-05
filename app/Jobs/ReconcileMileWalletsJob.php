<?php

namespace App\Jobs;

use App\Models\MileWallet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Proves every wallet still equals the ledger that produced it.
 *
 * The ledger is the record; `mile_wallets` is a cache kept beside it so a page
 * render does not have to sum a user's whole history. A cache can drift - a
 * partial write, a manual fix applied to one and not the other - and drift in
 * money-adjacent state is exactly the kind that goes unnoticed until someone
 * cannot explain their balance.
 *
 * Correction goes one way only. The totals are recomputed from the entries,
 * never the reverse: writing a ledger row to justify a wallet would be making
 * the record agree with the copy. Every correction is logged at error level,
 * because a wallet that needed one means something upstream is wrong and
 * silently repairing it would hide the bug rather than surface it.
 */
class ReconcileMileWalletsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    /** Wallets held in memory at once while walking the table. */
    private const CHUNK = 200;

    public function handle(): void
    {
        $drifted = 0;

        MileWallet::query()->orderBy('id')->chunkById(self::CHUNK, function ($wallets) use (&$drifted): void {
            $totals = $this->totalsFor($wallets->pluck('user_id')->all());

            foreach ($wallets as $wallet) {
                $totals[$wallet->user_id] ??= ['balance' => 0, 'earned' => 0, 'spent' => 0];
                $drifted += $this->reconcile($wallet, $totals[$wallet->user_id]) ? 1 : 0;
            }
        });

        if ($drifted > 0) {
            Log::error('Miles wallets drifted from their ledger.', ['wallets' => $drifted]);
        }
    }

    /**
     * Ledger totals for a page of wallets, as one grouped query rather than one
     * sum per user.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, array{balance: int, earned: int, spent: int}>
     */
    private function totalsFor(array $userIds): array
    {
        return DB::table('mile_ledger_entries')
            ->selectRaw('user_id')
            ->selectRaw('COALESCE(SUM(amount), 0) as balance')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as earned')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END), 0) as spent')
            ->whereIn('user_id', $userIds)
            ->groupBy('user_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->user_id => [
                'balance' => (int) $row->balance,
                'earned' => (int) $row->earned,
                'spent' => (int) $row->spent,
            ]])
            ->all();
    }

    /**
     * @param  array{balance: int, earned: int, spent: int}  $totals
     * @return bool whether the wallet had drifted
     */
    private function reconcile(MileWallet $wallet, array $totals): bool
    {
        $corrections = array_filter([
            'balance' => $wallet->balance === $totals['balance'] ? null : $totals['balance'],
            'lifetime_earned' => $wallet->lifetime_earned === $totals['earned'] ? null : $totals['earned'],
            'lifetime_spent' => $wallet->lifetime_spent === $totals['spent'] ? null : $totals['spent'],
        ], fn (?int $value): bool => $value !== null);

        if ($corrections === []) {
            return false;
        }

        Log::error('A Miles wallet did not match its ledger.', [
            'user_id' => $wallet->user_id,
            'balance_was' => $wallet->balance,
            'balance_now' => $totals['balance'],
            'corrections' => array_keys($corrections),
        ]);

        $wallet->forceFill($corrections)->save();

        return true;
    }
}
