<?php

namespace App\Actions\Features;

use App\Enums\Feature;
use App\Support\FeatureGraph;
use LogicException;

/**
 * Works out the full feature state produced by flipping one feature.
 *
 * Pure: no database, no user. Enabling walks the transitive dependency closure and
 * evicts anything the newly enabled set conflicts with; disabling walks the
 * transitive reverse-dependency closure, so turning off Investments also turns off
 * Portfolio rather than refusing and making the user unpick the graph themselves.
 */
final readonly class ResolveFeatureToggle
{
    private FeatureGraph $graph;

    public function __construct(?FeatureGraph $graph = null)
    {
        $this->graph = $graph ?? FeatureGraph::fromEnum();
    }

    /**
     * @param  array<string, bool>  $current  feature value => enabled
     */
    public function __invoke(array $current, Feature $target, bool $enable): FeatureToggleResult
    {
        if ($target->isCore()) {
            return new FeatureToggleResult($current, [], FeatureToggleResult::REJECTED_CORE);
        }

        if ($this->isEnabled($current, $target) === $enable) {
            return new FeatureToggleResult($current);
        }

        $next = $current;

        /** @var array<int, FeatureChange> $changes */
        $changes = [];

        if ($enable) {
            $enabled = $this->enableClosure($next, $target, $changes);
            $this->evictConflicts($next, $enabled, $changes);
        } else {
            $this->disableClosure($next, $target, $changes);
        }

        return new FeatureToggleResult($next, $changes);
    }

    /**
     * Turn on the target and everything it transitively requires.
     *
     * @param  array<string, bool>  $state
     * @param  array<int, FeatureChange>  $changes
     * @return array<string, Feature> everything now enabled by this call, keyed by value
     */
    private function enableClosure(array &$state, Feature $root, array &$changes): array
    {
        $seen = [];
        $queue = [$root];

        while ($queue !== []) {
            $feature = array_shift($queue);

            if (isset($seen[$feature->value])) {
                continue;
            }

            $seen[$feature->value] = $feature;

            $wasEnabled = $this->isEnabled($state, $feature);
            $state[$feature->value] = true;

            if (! $wasEnabled && $feature !== $root) {
                $changes[] = new FeatureChange($feature, true);
            }

            foreach ($this->graph->requires($feature) as $dependency) {
                if (! isset($seen[$dependency->value])) {
                    $queue[] = $dependency;
                }
            }
        }

        return $seen;
    }

    /**
     * Turn off everything the newly enabled set conflicts with, plus their dependents.
     *
     * @param  array<string, bool>  $state
     * @param  array<string, Feature>  $enabled
     * @param  array<int, FeatureChange>  $changes
     */
    private function evictConflicts(array &$state, array $enabled, array &$changes): void
    {
        foreach ($enabled as $feature) {
            foreach ($this->graph->conflictsWith($feature) as $conflict) {
                if (isset($enabled[$conflict->value])) {
                    throw new LogicException(sprintf(
                        'Feature graph is self-contradictory: [%s] conflicts with [%s], which the same enable resolved as a dependency.',
                        $feature->value,
                        $conflict->value,
                    ));
                }

                if ($this->isEnabled($state, $conflict)) {
                    $this->disableClosure($state, $conflict, $changes, recordRoot: true);
                }
            }
        }
    }

    /**
     * Turn off the root and everything that transitively depends on it.
     *
     * @param  array<string, bool>  $state
     * @param  array<int, FeatureChange>  $changes
     */
    private function disableClosure(array &$state, Feature $root, array &$changes, bool $recordRoot = false): void
    {
        $seen = [];
        $queue = [$root];

        while ($queue !== []) {
            $feature = array_shift($queue);

            // Core features are never dependents in practice, but guard anyway so a
            // future edge can't switch off the base app.
            if (isset($seen[$feature->value]) || $feature->isCore()) {
                continue;
            }

            $seen[$feature->value] = true;

            $wasEnabled = $this->isEnabled($state, $feature);
            $state[$feature->value] = false;

            if ($wasEnabled && ($recordRoot || $feature !== $root)) {
                $changes[] = new FeatureChange($feature, false);
            }

            foreach ($this->graph->requiredBy($feature) as $dependent) {
                if (! isset($seen[$dependent->value])) {
                    $queue[] = $dependent;
                }
            }
        }
    }

    /**
     * @param  array<string, bool>  $state
     */
    private function isEnabled(array $state, Feature $feature): bool
    {
        return $state[$feature->value] ?? $feature->enabledByDefault();
    }
}
