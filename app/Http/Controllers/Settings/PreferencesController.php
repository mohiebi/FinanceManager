<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Currency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PreferencesUpdateRequest;
use App\Support\FrontendLocalization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
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
            'defaultCurrency' => Auth::user()?->default_currency,
        ]);
    }

    public function update(PreferencesUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back();
    }
}
