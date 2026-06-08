<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PreferencesUpdateRequest;
use App\Support\FrontendLocalization;
use Illuminate\Http\RedirectResponse;
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
        ]);
    }

    public function update(PreferencesUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back();
    }
}
