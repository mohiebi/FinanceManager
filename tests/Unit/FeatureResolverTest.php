<?php

use App\Actions\Features\FeatureToggleResult;
use App\Actions\Features\ResolveFeatureToggle;
use App\Enums\Feature;
use App\Support\FeatureGraph;
use App\Support\FeatureSet;

test('enabling portfolio also enables the investments it depends on', function () {
    $result = (new ResolveFeatureToggle)(
        FeatureSet::defaults()->toEnabledMap(),
        Feature::Portfolio,
        true,
    );

    expect($result->wasRejected())->toBeFalse()
        ->and($result->state[Feature::Portfolio->value])->toBeTrue()
        ->and($result->state[Feature::Investments->value])->toBeTrue()
        ->and($result->enabledByCascade())->toBe([Feature::Investments]);
});

test('disabling investments cascades to portfolio', function () {
    $state = FeatureSet::defaults()->toEnabledMap();
    $state[Feature::Investments->value] = true;
    $state[Feature::Portfolio->value] = true;

    $result = (new ResolveFeatureToggle)($state, Feature::Investments, false);

    expect($result->state[Feature::Investments->value])->toBeFalse()
        ->and($result->state[Feature::Portfolio->value])->toBeFalse()
        ->and($result->disabledByCascade())->toBe([Feature::Portfolio]);
});

test('core features cannot be disabled', function () {
    $state = FeatureSet::defaults()->toEnabledMap();

    $result = (new ResolveFeatureToggle)($state, Feature::Transactions, false);

    expect($result->rejected)->toBe(FeatureToggleResult::REJECTED_CORE)
        ->and($result->state)->toBe($state);
});

test('toggling a feature to the state it is already in changes nothing', function () {
    $state = FeatureSet::defaults()->toEnabledMap();

    $result = (new ResolveFeatureToggle)($state, Feature::Bills, false);

    expect($result->wasRejected())->toBeFalse()
        ->and($result->cascaded)->toBe([])
        ->and($result->state)->toBe($state);
});

test('transitive dependency chains resolve in one pass', function () {
    $graph = FeatureGraph::make(requires: [
        Feature::Portfolio->value => [Feature::Bills],
        Feature::Bills->value => [Feature::Investments],
    ]);

    $result = (new ResolveFeatureToggle($graph))(
        FeatureSet::defaults()->toEnabledMap(),
        Feature::Portfolio,
        true,
    );

    expect($result->state[Feature::Portfolio->value])->toBeTrue()
        ->and($result->state[Feature::Bills->value])->toBeTrue()
        ->and($result->state[Feature::Investments->value])->toBeTrue()
        ->and($result->enabledByCascade())->toEqualCanonicalizing([Feature::Bills, Feature::Investments]);
});

test('enabling a feature evicts what it conflicts with, and their dependents', function () {
    $graph = FeatureGraph::make(
        requires: [Feature::Portfolio->value => [Feature::Investments]],
        conflicts: [Feature::Bills->value => [Feature::Investments]],
    );

    $state = FeatureSet::defaults()->toEnabledMap();
    $state[Feature::Investments->value] = true;
    $state[Feature::Portfolio->value] = true;

    $result = (new ResolveFeatureToggle($graph))($state, Feature::Bills, true);

    expect($result->state[Feature::Bills->value])->toBeTrue()
        ->and($result->state[Feature::Investments->value])->toBeFalse()
        ->and($result->state[Feature::Portfolio->value])->toBeFalse()
        ->and($result->disabledByCascade())
        ->toEqualCanonicalizing([Feature::Investments, Feature::Portfolio]);
});

test('conflicts are symmetric even when declared in only one direction', function () {
    $graph = FeatureGraph::make(conflicts: [
        Feature::Investments->value => [Feature::Bills],
    ]);

    $state = FeatureSet::defaults()->toEnabledMap();
    $state[Feature::Investments->value] = true;

    $result = (new ResolveFeatureToggle($graph))($state, Feature::Bills, true);

    expect($result->state[Feature::Bills->value])->toBeTrue()
        ->and($result->state[Feature::Investments->value])->toBeFalse();
});

test('no feature both requires and conflicts with the same feature', function () {
    foreach (Feature::cases() as $feature) {
        expect(array_intersect($feature->requires(), $feature->conflictsWith()))->toBe([]);
    }
});

test('the dependency graph is acyclic', function () {
    foreach (Feature::cases() as $feature) {
        $seen = [];
        $queue = $feature->requires();

        while ($queue !== []) {
            $dependency = array_shift($queue);

            expect($dependency)->not->toBe($feature);

            if (isset($seen[$dependency->value])) {
                continue;
            }

            $seen[$dependency->value] = true;
            $queue = [...$queue, ...$dependency->requires()];
        }
    }
});
