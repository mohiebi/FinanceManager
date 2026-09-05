<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Features\UpdateUserFeature;
use App\Actions\Miles\ActivateUserFeature;
use App\Enums\Feature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DisplayUpdateRequest;
use App\Http\Requests\Settings\ModulesUpdateRequest;
use App\Models\User;
use App\Support\FeatureSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    public function __construct(
        private readonly UpdateUserFeature $updateUserFeature,
        private readonly ActivateUserFeature $activateUserFeature,
    ) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();
        $features = $user->featureSet();

        return Inertia::render('settings/Modules', [
            'modules' => collect(Feature::toggleable())
                ->map(fn (Feature $feature): array => $this->present($feature, $user, $features))
                ->values(),
            'coreModules' => collect(Feature::cases())
                ->filter(fn (Feature $feature): bool => $feature->isCore())
                ->map(fn (Feature $feature): array => [
                    'key' => $feature->value,
                    'label' => $feature->label(),
                    'description' => $feature->description(),
                    'icon' => $feature->icon(),
                ])
                ->values(),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(ModulesUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $feature = $request->feature();
        $validated = $request->validated();

        if (array_key_exists('show_promo', $validated)) {
            $this->updateUserFeature->setPromoVisibility($user, $feature, (bool) $validated['show_promo']);
        }

        if (! array_key_exists('enabled', $validated)) {
            return back()->with('status', __('modules.saved'));
        }

        $result = ($this->activateUserFeature)($user, $feature, (bool) $validated['enabled']);

        if ($result->wasRejected()) {
            return back()->withErrors([
                'feature' => __('modules.locked', ['module' => $feature->label()]),
            ]);
        }

        return back()->with('status', $this->statusFor($result->enabledByCascade(), $result->disabledByCascade()));
    }

    /**
     * The App tab's "Display" group — separate from update() on purpose: it is
     * a plain account preference, not a Feature toggle, so it carries none of
     * that method's cascade/entitlement logic.
     */
    public function updateDisplay(DisplayUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back();
    }

    /**
     * @param  array<int, Feature>  $enabled
     * @param  array<int, Feature>  $disabled
     */
    private function statusFor(array $enabled, array $disabled): string
    {
        $messages = [];

        if ($enabled !== []) {
            $messages[] = __('modules.cascade_enabled', ['features' => $this->labels($enabled)]);
        }

        if ($disabled !== []) {
            $messages[] = __('modules.cascade_disabled', ['features' => $this->labels($disabled)]);
        }

        return $messages === [] ? __('modules.saved') : implode(' ', $messages);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Feature $feature, User $user, FeatureSet $features): array
    {
        // Only dependents that are currently on would actually be switched off, so
        // the confirm dialog does not threaten to disable things already disabled.
        $wouldDisable = array_values(array_filter(
            $feature->requiredBy(),
            fn (Feature $dependent): bool => $features->enabled($dependent),
        ));

        $needs = array_values(array_filter(
            $feature->requires(),
            fn (Feature $dependency): bool => ! $dependency->isCore(),
        ));
        $quote = $this->activateUserFeature->quote($user, $feature);
        $balance = (int) ($user->mileWallet()->value('balance') ?? 0);

        return [
            'key' => $feature->value,
            'label' => $feature->label(),
            'description' => $feature->description(),
            'icon' => $feature->icon(),
            'tier' => $feature->tier()->value,
            // Live state, not the stored preference: a module the plan does not
            // cover reads as off here, which is both true and what keeps the
            // "hide from menu" control available on a locked card.
            'enabled' => $features->isLive($feature, $user->isPro()),
            'show_promo' => $features->advertises($feature, $user->isPro()),
            // Drives whether the "hide from menu" control is offered at all: a
            // module with no sidebar entry has no menu to be hidden from.
            'in_nav' => $feature->appearsInNav(),
            'may_use' => $user->mayUse($feature),
            'requires' => array_map(fn (Feature $dependency): string => $dependency->label(), $needs),
            'disables' => array_map(fn (Feature $dependent): string => $dependent->label(), $wouldDisable),
            // Non-null for modules the page advertises but does not switch — they
            // render as a link card instead of a toggle.
            'manage_url' => $feature->managedRoute() === null
                ? null
                : route($feature->managedRoute()),
            'unlocked' => $quote['cost'] === 0,
            // Whether Miles were ever spent here, which `unlocked` alone cannot
            // say: a free module also quotes zero. Drives the warning shown
            // before switching a paid module off.
            'paid' => in_array($feature->value, (array) config('miles.paid_modules'), true),
            'activation_cost' => $quote['cost'],
            'unlock_features' => array_map(
                fn (string $value): string => Feature::from($value)->label(),
                $quote['features'],
            ),
            'can_afford' => $balance >= $quote['cost'],
            'shortfall' => max(0, $quote['cost'] - $balance),
        ];
    }

    /**
     * @param  array<int, Feature>  $features
     */
    private function labels(array $features): string
    {
        return implode(', ', array_map(fn (Feature $feature): string => $feature->label(), $features));
    }
}
