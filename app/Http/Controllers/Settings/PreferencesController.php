<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Currency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PreferencesUpdateRequest;
use App\Support\FrontendLocalization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PreferencesController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/Preferences', [
            'locales' => collect(FrontendLocalization::locales())->map(fn (string $locale) => [
                'label' => __("settings.preferences.locales.{$locale}"),
                'value' => $locale,
            ]),
            'calendars' => collect(FrontendLocalization::calendars())->map(fn (string $calendar) => [
                'label' => __("settings.preferences.calendars.{$calendar}"),
                'value' => $calendar,
            ]),
            'currencies' => collect(Currency::cases())->map(fn (Currency $currency) => [
                'label' => __("finance.currencies.{$currency->value}"),
                'value' => $currency->value,
            ]),
            'timezoneGroups' => $this->timezoneGroups(),
            'defaultCurrency' => Auth::user()?->default_currency,
        ]);
    }

    /**
     * Every IANA zone, grouped by region so a ~420-entry flat list stays navigable.
     *
     * Zone identifiers are not translated — they are stable technical names, and a
     * user hunting for "Asia/Tehran" is better served by the string they already
     * know than by a localised approximation of it.
     *
     * @return array<int, array{label: string, options: array<int, array{label: string, value: string}>}>
     */
    private function timezoneGroups(): array
    {
        return collect(FrontendLocalization::timezones())
            ->groupBy(fn (string $timezone): string => str_contains($timezone, '/')
                ? Str::before($timezone, '/')
                : 'UTC')
            ->map(fn (Collection $zones, string $region): array => [
                'label' => str_replace('_', ' ', $region),
                'options' => $zones
                    ->map(fn (string $timezone): array => [
                        'label' => str_replace('_', ' ', Str::after($timezone, '/') ?: $timezone),
                        'value' => $timezone,
                    ])
                    ->values()
                    ->all(),
            ])
            // UTC first — it is the default, and it has no region to sort under.
            ->sortBy(fn (array $group, string $region): string => $region === 'UTC' ? '' : $region)
            ->values()
            ->all();
    }

    public function update(PreferencesUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back();
    }
}
