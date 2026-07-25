<?php

namespace App\Observers;

use App\Models\User;
use App\Support\Encryption\UserKeyRing;

/**
 * Gives every new user a data key.
 *
 * An observer rather than a hook in the signup actions, because there are several
 * ways a user gets created — the OTP signup flow, the Google broker, factories and
 * seeders — and only this covers all of them.
 */
class UserEncryptionKeyObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $user->ensureEncryptionKey();
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        // The row is removed by the foreign key cascade; drop the memoized copy so
        // a recycled id in the same request cannot resurrect it.
        app(UserKeyRing::class)->forget($user->getKey());
    }
}
