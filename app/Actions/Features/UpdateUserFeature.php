<?php

namespace App\Actions\Features;

use App\Enums\Feature;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Applies a feature toggle to a user, persisting only the rows that actually
 * changed so `user_features` stays sparse.
 */
final readonly class UpdateUserFeature
{
    public function __construct(private ResolveFeatureToggle $resolveFeatureToggle) {}

    public function __invoke(User $user, Feature $target, bool $enable): FeatureToggleResult
    {
        // Defence in depth: the form request rejects these too, but a self-managed
        // feature reaching this action would mean a caller bypassed the crypto
        // handshake that owns it.
        if ($target->isSelfManaged()) {
            return new FeatureToggleResult(
                $user->featureSet()->toEnabledMap(),
                [],
                FeatureToggleResult::REJECTED_SELF_MANAGED,
            );
        }

        return $this->apply($user, $target, $enable);
    }

    /**
     * Toggle a self-managed feature.
     *
     * Only for the controller that owns that feature's crypto — VaultController
     * calls this after the browser has wrapped the data key, which is the step the
     * generic endpoint cannot perform.
     */
    public function force(User $user, Feature $target, bool $enable): FeatureToggleResult
    {
        return $this->apply($user, $target, $enable);
    }

    private function apply(User $user, Feature $target, bool $enable): FeatureToggleResult
    {
        $current = $user->featureSet()->toEnabledMap();

        if ($enable && ! $user->mayUse($target)) {
            return new FeatureToggleResult($current, [], FeatureToggleResult::REJECTED_ENTITLEMENT);
        }

        $result = ($this->resolveFeatureToggle)($current, $target, $enable);

        if ($result->wasRejected()) {
            return $result;
        }

        $this->persist($user, $current, $result->state);

        return $result;
    }

    /**
     * Set whether a disabled module still advertises itself in the nav.
     */
    public function setPromoVisibility(User $user, Feature $target, bool $showPromo): void
    {
        if ($target->isCore()) {
            return;
        }

        $user->features()->updateOrCreate(
            ['feature' => $target->value],
            [
                'show_promo' => $showPromo,
                // Carry the resolved value through, otherwise creating a row here would
                // fall back to the column default and silently disable a module that
                // was only ever on by default.
                'enabled' => $user->featureSet()->enabled($target),
            ],
        );

        $user->forgetFeatureSet();
    }

    /**
     * @param  array<string, bool>  $before
     * @param  array<string, bool>  $after
     */
    private function persist(User $user, array $before, array $after): void
    {
        DB::transaction(function () use ($user, $before, $after): void {
            foreach ($after as $value => $enabled) {
                $feature = Feature::tryFrom((string) $value);

                if ($feature === null || $feature->isCore()) {
                    continue;
                }

                if (($before[$value] ?? $feature->enabledByDefault()) === $enabled) {
                    continue;
                }

                // Only `enabled` is written, so an existing show_promo choice survives.
                $user->features()->updateOrCreate(
                    ['feature' => $feature->value],
                    ['enabled' => $enabled],
                );
            }
        });

        $user->forgetFeatureSet();
    }
}
