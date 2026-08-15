<?php

namespace App\Services\Advisor;

use App\Models\AdvisorRecommendation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Holds a generated recommendation for a Vault-armed browser to collect.
 *
 * Generation runs in a queued job, but a Vault-armed user's recommendation may
 * never be written to the database in the clear — only their browser holds the
 * key that seals it. That leaves the job with a result and nowhere to put it,
 * so it parks here until the browser comes back for it.
 *
 * The narrowest thing that works: encrypted with the application key, held in
 * the cache rather than the database, and dropped the moment the sealed
 * ciphertext lands. Compared with the synchronous flow this replaces, the same
 * plaintext was already passing through the server in the response body; what
 * changes is that it now rests there for as long as it takes the user to come
 * back, which is why the window is deliberately short.
 */
class AdvisorPendingPayloadStore
{
    /** @param array<string, mixed> $payload */
    public function put(AdvisorRecommendation $recommendation, array $payload): void
    {
        Cache::put(
            $this->key($recommendation),
            Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
            now()->addMinutes($this->lifetimeMinutes()),
        );
    }

    /**
     * Read the parked payload without consuming it.
     *
     * Deliberately not single-use: a browser that collects the payload and then
     * fails to seal it — a refresh at the wrong moment, a closed laptop — would
     * otherwise leave a recommendation that can never be completed and cannot be
     * regenerated without spending another provider call. forget() on a
     * successful seal is what ends the exposure.
     *
     * @return array<string, mixed>|null
     */
    public function peek(AdvisorRecommendation $recommendation): ?array
    {
        $stored = Cache::get($this->key($recommendation));

        if (! is_string($stored)) {
            return null;
        }

        try {
            $decoded = json_decode(Crypt::decryptString($stored), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    public function forget(AdvisorRecommendation $recommendation): void
    {
        Cache::forget($this->key($recommendation));
    }

    private function key(AdvisorRecommendation $recommendation): string
    {
        return "advisor:pending-payload:{$recommendation->id}";
    }

    private function lifetimeMinutes(): int
    {
        return max(5, (int) config('advisor.pending_payload_lifetime', 60));
    }
}
