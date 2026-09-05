<?php

namespace App\Actions\Miles;

use App\Actions\Features\FeatureToggleResult;
use App\Actions\Features\UpdateUserFeature;
use App\Enums\Feature;
use App\Enums\MilesReason;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class ActivateUserFeature
{
    public function __construct(
        private AdjustMiles $adjustMiles,
        private UpdateUserFeature $updateUserFeature,
    ) {}

    /**
     * @return array{features: array<int, string>, cost: int}
     */
    public function quote(User $user, Feature $feature): array
    {
        $closure = $this->dependencyClosure($feature);
        $paid = array_flip(config('miles.paid_modules'));
        $unlocked = $user->featureUnlocks()->pluck('feature')
            ->map(fn (mixed $value): string => $value instanceof Feature ? $value->value : (string) $value)
            ->all();
        $features = array_values(array_filter(
            array_keys($closure),
            fn (string $value): bool => isset($paid[$value]) && ! in_array($value, $unlocked, true),
        ));

        return ['features' => $features, 'cost' => count($features) * (int) config('miles.unlock_price')];
    }

    public function __invoke(User $user, Feature $feature, bool $enable): FeatureToggleResult
    {
        if (! $enable) {
            return ($this->updateUserFeature)($user, $feature, false);
        }

        return DB::transaction(function () use ($user, $feature): FeatureToggleResult {
            $quote = $this->quote($user, $feature);
            $entry = null;

            if ($quote['cost'] > 0) {
                $entry = ($this->adjustMiles)(
                    $user,
                    -$quote['cost'],
                    MilesReason::ModuleUnlock,
                    'module-unlock:'.implode(',', $quote['features']),
                    metadata: ['features' => $quote['features']],
                    action: 'module_unlock',
                );

                foreach ($quote['features'] as $value) {
                    $user->featureUnlocks()->firstOrCreate(
                        ['feature' => $value],
                        ['mile_ledger_entry_id' => $entry->getKey(), 'unlocked_at' => now()],
                    );
                }
            }

            $result = ($this->updateUserFeature)($user, $feature, true);

            if ($result->wasRejected()) {
                throw new \LogicException('The feature activation was rejected.');
            }

            return $result;
        }, 3);
    }

    /** @return array<string, Feature> */
    private function dependencyClosure(Feature $root): array
    {
        $closure = [];
        $queue = [$root];

        while ($queue !== []) {
            $feature = array_shift($queue);
            if (isset($closure[$feature->value])) {
                continue;
            }
            $closure[$feature->value] = $feature;
            array_push($queue, ...$feature->requires());
        }

        return $closure;
    }
}
