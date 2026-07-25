<?php

namespace App\Support;

use App\Enums\Feature;

/**
 * The dependency and conflict edges between features.
 *
 * Normally built straight from the enum. It exists as a separate object so the
 * resolver's conflict handling can be tested with an injected graph before any
 * real conflicting pair ships.
 */
final readonly class FeatureGraph
{
    /**
     * @param  array<string, array<int, Feature>>  $requires
     * @param  array<string, array<int, Feature>>  $conflicts
     */
    private function __construct(
        private array $requires,
        private array $conflicts,
    ) {}

    public static function fromEnum(): self
    {
        $requires = [];
        $conflicts = [];

        foreach (Feature::cases() as $feature) {
            $requires[$feature->value] = $feature->requires();
            $conflicts[$feature->value] = $feature->conflictsWith();
        }

        return new self($requires, $conflicts);
    }

    /**
     * @param  array<string, array<int, Feature>>  $requires
     * @param  array<string, array<int, Feature>>  $conflicts
     */
    public static function make(array $requires = [], array $conflicts = []): self
    {
        return new self($requires, $conflicts);
    }

    /**
     * @return array<int, Feature>
     */
    public function requires(Feature $feature): array
    {
        return $this->requires[$feature->value] ?? [];
    }

    /**
     * @return array<int, Feature>
     */
    public function requiredBy(Feature $feature): array
    {
        $dependents = [];

        foreach (Feature::cases() as $candidate) {
            if (in_array($feature, $this->requires($candidate), true)) {
                $dependents[] = $candidate;
            }
        }

        return $dependents;
    }

    /**
     * Conflicts are symmetric: declaring one direction is enough, so both edge
     * directions are unioned here rather than duplicated in the enum.
     *
     * @return array<int, Feature>
     */
    public function conflictsWith(Feature $feature): array
    {
        $conflicts = $this->conflicts[$feature->value] ?? [];

        foreach (Feature::cases() as $candidate) {
            if ($candidate === $feature) {
                continue;
            }

            if (in_array($feature, $this->conflicts[$candidate->value] ?? [], true)
                && ! in_array($candidate, $conflicts, true)) {
                $conflicts[] = $candidate;
            }
        }

        return $conflicts;
    }
}
