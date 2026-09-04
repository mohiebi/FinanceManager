<?php

namespace App\Observers;

use App\Actions\Gamification\AwardMilestones;
use App\Actions\Miles\AwardDailyActivity;
use App\Models\Transaction;

/**
 * Watches for the moments a user's record crosses a milestone.
 *
 * An observer rather than a hook in the controllers, because there are four
 * separate paths that create a transaction — the web form, the CSV importer, the
 * Telegram wizard and the MCP proposal applier — and a milestone that only fires
 * on one of them is worse than none.
 */
class TransactionObserver
{
    public function __construct(
        private readonly AwardMilestones $awardMilestones,
        private readonly AwardDailyActivity $awardDailyActivity,
    ) {}

    /**
     * Deliberately `created`, not `creating`.
     *
     * Encryption runs on `saving`, so by `creating` the raw attributes already
     * hold ciphertext — and a `saving` that throws VaultLocked means `creating`
     * never runs at all. `created` fires only for rows that really landed.
     */
    public function created(Transaction $transaction): void
    {
        if ($transaction->user_id === null) {
            return;
        }

        // The id, not the relation: `$user->transactions()->create(...)` sets the
        // foreign key but never the inverse relation, so reading `$transaction->user`
        // would re-fetch a User the caller already holds — once per imported row.
        $this->awardMilestones->afterTransaction((int) $transaction->user_id);

        $this->awardDailyActivity->forUserId((int) $transaction->user_id, 'transaction');
    }
}
