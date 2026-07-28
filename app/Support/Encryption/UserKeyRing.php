<?php

namespace App\Support\Encryption;

use App\Models\UserEncryptionKey;
use Illuminate\Support\Facades\Crypt;
use LogicException;

/**
 * Resolves and memoizes per-user data keys for the current request or job.
 *
 * Bound as `scoped`, never `singleton`: a singleton survives across jobs in a
 * long-running queue worker, so job #2 for user B could read the key memoized by
 * job #1 for user A. That distinction is a security bug, not a performance one.
 */
final class UserKeyRing
{
    /**
     * Raw DEKs by user id. `false` means "looked up, vault is armed, no key".
     *
     * @var array<int, string|false>
     */
    private array $keys = [];

    /**
     * The raw DEK for a user, or null when their vault is armed.
     *
     * A null return is a normal state, not an error — the caller decides whether
     * that means "render ciphertext" (reads) or "refuse" (writes).
     */
    public function for(int $userId): ?string
    {
        if (! array_key_exists($userId, $this->keys)) {
            $this->keys[$userId] = $this->load($userId);
        }

        return $this->keys[$userId] === false ? null : $this->keys[$userId];
    }

    /**
     * Warm the ring for a batch of users in one query.
     *
     * Without this a job touching N users' rows costs N key lookups.
     *
     * @param  array<int, int>  $userIds
     */
    public function preload(array $userIds): void
    {
        $missing = array_values(array_diff($userIds, array_keys($this->keys)));

        if ($missing === []) {
            return;
        }

        $rows = UserEncryptionKey::query()
            ->whereIn('user_id', $missing)
            ->get(['user_id', 'wrapped_dek_server']);

        foreach ($rows as $row) {
            $this->keys[(int) $row->user_id] = $row->wrapped_dek_server === null
                ? false
                : base64_decode(Crypt::decryptString($row->wrapped_dek_server), true);
        }
    }

    public function forget(int $userId): void
    {
        unset($this->keys[$userId]);
    }

    public function flush(): void
    {
        $this->keys = [];
    }

    private function load(int $userId): string|false
    {
        $record = UserEncryptionKey::query()
            ->where('user_id', $userId)
            ->first(['wrapped_dek_server']);

        if ($record === null || $record->wrapped_dek_server === null) {
            return false;
        }

        $dek = base64_decode(Crypt::decryptString($record->wrapped_dek_server), true);

        return $dek === false ? false : $dek;
    }

    /**
     * Keep raw keys out of dumps, logs and stack traces.
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['keys' => '[redacted]'];
    }

    /**
     * @return array<int, string>
     */
    public function __sleep(): array
    {
        throw new LogicException('UserKeyRing must never be serialized.');
    }
}
