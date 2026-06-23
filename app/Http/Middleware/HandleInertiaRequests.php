<?php

namespace App\Http\Middleware;

use App\Support\FrontendLocalization;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = FrontendLocalization::normalizeLocale($request->user()?->locale);
        $calendar = FrontendLocalization::normalizeCalendar($request->user()?->calendar);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'locale' => $locale,
            'dir' => FrontendLocalization::direction($locale),
            'calendar' => $calendar,
            'translations' => fn () => FrontendLocalization::messages($locale),
            'fallbackLocale' => FrontendLocalization::DEFAULT_LOCALE,
            'fallbackTranslations' => fn () => $locale === FrontendLocalization::DEFAULT_LOCALE
                ? null
                : FrontendLocalization::messages(FrontendLocalization::DEFAULT_LOCALE),
            'authFlow' => fn () => $request->session()->get('auth_flow'),
            'createdCategory' => fn () => $request->session()->get('createdCategory'),
            'transactionImportPreview' => fn () => $request->session()->get('transaction_import_preview'),
            'transactionImportResult' => fn () => $request->session()->get('transaction_import_result'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
