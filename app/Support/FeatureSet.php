<?php

namespace App\Support;

use App\Enums\Feature;
use App\Models\UserFeature;

/**
 * A user's resolved feature state: sparse `user_features` rows merged over the
 * enum defaults.
 *
 * @phpstan-type FeatureState array{enabled: bool, show_promo: bool}
 */
final readonly class FeatureSet
{
    /**
     * @param  array<string, FeatureState>  $state
     */
    private function __construct(private array $state) {}

    /**
     * @param  iterable<int, UserFeature>  $overrides
     */
    public static function fromOverrides(iterable $overrides): self
    {
        $state = [];

        foreach (Feature::cases() as $feature) {
            $state[$feature->value] = [
                'enabled' => $feature->enabledByDefault(),
                'show_promo' => $feature->promoByDefault(),
            ];
        }

        foreach ($overrides as $override) {
            // Read the raw column rather than the cast attribute so a row left behind
            // by a removed enum case is ignored instead of throwing.
            $feature = Feature::tryFrom((string) $override->getRawOriginal('feature'));

            // Core features ignore overrides outright — a stale row must never be able
            // to switch off transactions.
            if ($feature === null || $feature->isCore()) {
                continue;
            }

            $state[$feature->value] = [
                'enabled' => (bool) $override->enabled,
                'show_promo' => (bool) $override->show_promo,
            ];
        }

        return new self($state);
    }

    public static function defaults(): self
    {
        return self::fromOverrides([]);
    }

    public function enabled(Feature $feature): bool
    {
        return $this->state[$feature->value]['enabled'] ?? $feature->enabledByDefault();
    }

    /** Only ever true for a disabled feature — enabled modules are always visible. */
    public function showsPromo(Feature $feature): bool
    {
        if ($this->enabled($feature)) {
            return false;
        }

        return $this->state[$feature->value]['show_promo'] ?? $feature->promoByDefault();
    }

    /**
     * The shape the resolver works on.
     *
     * @return array<string, bool>
     */
    public function toEnabledMap(): array
    {
        return array_map(
            fn (array $entry): bool => $entry['enabled'],
            $this->state,
        );
    }

    /**
     * The shape shared with Inertia.
     *
     * `$isPro` decides `may_use` for paid modules — a plan entitlement, not
     * whether the user has switched the module on. Free modules and core
     * modules are always usable regardless of it.
     *
     * @return array<string, array{enabled: bool, show_promo: bool, core: bool, tier: string, may_use: bool}>
     */
    public function toArray(bool $isPro = false): array
    {
        $features = [];

        foreach (Feature::cases() as $feature) {
            $features[$feature->value] = [
                'enabled' => $this->enabled($feature),
                'show_promo' => $this->showsPromo($feature),
                'core' => $feature->isCore(),
                'tier' => $feature->tier()->value,
                'may_use' => $feature->mayUseWithPro($isPro),
            ];
        }

        return $features;
    }
}
