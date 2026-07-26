<?php

namespace App\Actions\Features;

use App\Enums\Feature;

final readonly class FeatureToggleResult
{
    public const REJECTED_CORE = 'core';

    public const REJECTED_ENTITLEMENT = 'entitlement';

    /**
     * Blocked because it would have evicted a self-managed feature — one the
     * server cannot switch off on its own.
     */
    public const REJECTED_CONFLICT_LOCKED = 'conflict_locked';

    /** Blocked because the feature is owned by a dedicated controller. */
    public const REJECTED_SELF_MANAGED = 'self_managed';

    /**
     * @param  array<string, bool>  $state  the resolved enabled map
     * @param  array<int, FeatureChange>  $cascaded  everything flipped as a side effect
     * @param  string|null  $rejected  one of the REJECTED_* constants
     */
    public function __construct(
        public array $state,
        public array $cascaded = [],
        public ?string $rejected = null,
    ) {}

    public function wasRejected(): bool
    {
        return $this->rejected !== null;
    }

    /**
     * @return array<int, Feature>
     */
    public function disabledByCascade(): array
    {
        return $this->cascadedWhere(false);
    }

    /**
     * @return array<int, Feature>
     */
    public function enabledByCascade(): array
    {
        return $this->cascadedWhere(true);
    }

    /**
     * @return array<int, Feature>
     */
    private function cascadedWhere(bool $enabled): array
    {
        return array_values(array_map(
            fn (FeatureChange $change): Feature => $change->feature,
            array_filter(
                $this->cascaded,
                fn (FeatureChange $change): bool => $change->enabled === $enabled,
            ),
        ));
    }
}
