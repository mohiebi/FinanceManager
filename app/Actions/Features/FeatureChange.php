<?php

namespace App\Actions\Features;

use App\Enums\Feature;

/**
 * A single feature flipped as a side effect of toggling another one.
 */
final readonly class FeatureChange
{
    public function __construct(
        public Feature $feature,
        public bool $enabled,
    ) {}
}
