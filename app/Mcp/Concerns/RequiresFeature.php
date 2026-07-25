<?php

namespace App\Mcp\Concerns;

use App\Enums\Feature;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Hides a tool from users who have its module switched off.
 *
 * `Primitive::eligibleForRegistration()` calls `shouldRegister` through the
 * container, and `ServerContext::resolvePrimitives()` filters on it for both
 * `tools/list` and `tools/call` — so a gated tool is neither advertised nor
 * reachable.
 */
trait RequiresFeature
{
    public function shouldRegister(): bool
    {
        // Read the guard rather than a container-resolved Request: the MCP transport
        // authenticates via `auth:api` and the test helper calls `Auth::setUser()`,
        // so neither reliably populates an Illuminate HTTP request.
        $user = Auth::user();

        if (! $user instanceof User) {
            // No authenticated user (e.g. the artisan/local transport) — nothing
            // to check a module against, so stay hidden.
            return false;
        }

        foreach (static::requiredFeatures() as $feature) {
            if (! $user->hasFeature($feature)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, Feature>
     */
    abstract protected static function requiredFeatures(): array;
}
