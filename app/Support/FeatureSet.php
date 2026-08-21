<?php

namespace App\Support;

use App\Enums\Feature;
use App\Models\User;
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
     * Whether the module is actually live for a user with this entitlement.
     *
     * {@see enabled()} is the stored preference and nothing else — the toggle
     * resolver and the promo-visibility writer both depend on it staying that
     * way, so a free user who never touched a paid module has to keep reading as
     * "on" there, or buying the plan would find it switched off.
     *
     * What a *screen* needs is both questions answered together, which is what
     * {@see User::hasFeature()} means. Both presenters go through here rather
     * than each remembering to `&&` the entitlement for itself.
     */
    public function isLive(Feature $feature, bool $isPro): bool
    {
        return $feature->mayUseWithPro($isPro) && $this->enabled($feature);
    }

    /**
     * Whether a module that is not live still advertises itself in the nav.
     *
     * Asked of the live state rather than the stored one, so a locked module
     * keeps the "hide from menu" control an off module has. Without it,
     * defaulting a paid module to on would quietly take that choice away from
     * every free user.
     */
    public function advertises(Feature $feature, bool $isPro): bool
    {
        if ($this->isLive($feature, $isPro)) {
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
     * `may_use` is the plan entitlement on its own; `enabled` is whether the
     * module is live, which needs the entitlement *and* the switch. A client
     * reading `enabled` alone is what decides whether a nav item is a page or a
     * sales pitch, so it has to mean the same thing the server means by
     * {@see User::hasFeature()}.
     *
     * @return array<string, array{enabled: bool, show_promo: bool, core: bool, tier: string, may_use: bool}>
     */
    public function toArray(bool $isPro = false): array
    {
        $features = [];

        foreach (Feature::cases() as $feature) {
            $features[$feature->value] = [
                'enabled' => $this->isLive($feature, $isPro),
                'show_promo' => $this->advertises($feature, $isPro),
                'core' => $feature->isCore(),
                'tier' => $feature->tier()->value,
                'may_use' => $feature->mayUseWithPro($isPro),
            ];
        }

        return $features;
    }
}
