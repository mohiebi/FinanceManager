<?php

namespace App\Http\Middleware;

use App\Support\FrontendLocalization;
use App\Support\SeoMetadata;
use Illuminate\Http\Request;
use Inertia\Inertia;
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
        $locale = FrontendLocalization::resolve($request);
        $calendar = FrontendLocalization::normalizeCalendar($request->user()?->calendar);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'isAdmin' => $request->user()?->isAdmin() ?? false,
            ],
            'locale' => $locale,
            'locales' => FrontendLocalization::locales(),
            'dir' => FrontendLocalization::direction($locale),
            'calendar' => $calendar,
            'timezone' => FrontendLocalization::normalizeTimezone($request->user()?->timezone),
            'flightTerminologyEnabled' => $request->user()?->flight_terminology_enabled ?? false,
            // Eager, not deferred: useAmountMask.ts reads this synchronously at
            // module load (before any component has mounted) so a fresh device
            // starts masked or unmasked per the account's own default rather than
            // always-unmasked for the one render before a deferred prop arrives.
            'amountMaskDefault' => $request->user()?->amount_mask_default ?? false,
            // Same reasoning as amountMaskDefault: CipheredMoney.vue reads this on
            // every render, app-wide, so it has to be a normal shared prop rather
            // than something only the Settings > App page happens to receive.
            'compactFiguresEnabled' => $request->user()?->compact_figures_enabled ?? false,
            'translations' => FrontendLocalization::messages($locale),
            // Eager, not deferred: the nav is built from this, and deferring would
            // make menu items pop in after first paint. Costs one memoized query
            // that the feature middleware has usually already paid for.
            'features' => fn () => $request->user()?->featureSet()->toArray($request->user()->isPro()),
            // Eager like the feature map: the unlock dialog is a layout-level gate,
            // and deferring it would flash unlocked-looking UI on every page load.
            'vault' => fn () => $request->user()?->vaultDescriptor(),
            // Also eager, and also free: pro_until is a column on the already-loaded
            // auth user, so isPro() costs no query. The settings nav is built from
            // billing_enabled, which is the same reason `features` cannot be deferred.
            'subscription' => fn (): ?array => $request->user() === null ? null : [
                'is_pro' => $request->user()->isPro(),
                'pro_until' => $request->user()->pro_until?->toIso8601String(),
                'billing_enabled' => (bool) config('billing.enabled'),
            ],
            'fallbackLocale' => FrontendLocalization::DEFAULT_LOCALE,
            'fallbackTranslations' => $locale === FrontendLocalization::DEFAULT_LOCALE
                ? null
                : FrontendLocalization::messages(FrontendLocalization::DEFAULT_LOCALE),
            'seo' => fn () => SeoMetadata::forRequest($request, $locale),
            'authFlow' => fn () => $request->session()->get('auth_flow'),
            'createdCategory' => fn () => $request->session()->get('createdCategory'),
            'createdInvestmentAsset' => fn () => $request->session()->get('createdInvestmentAsset'),
            'transactionImportPreview' => fn () => $request->session()->get('transaction_import_preview'),
            'transactionImportResult' => fn () => $request->session()->get('transaction_import_result'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Deferred (not eager) — these 2 queries would otherwise run on every
            // single authenticated page load app-wide. Inertia fetches it via a
            // background request right after initial render, same pattern as
            // the Portfolio/Investments pages already use for their stats.
            'notifications' => $request->user() ? Inertia::defer(fn () => [
                'unread_count' => $request->user()->unreadNotifications()->count(),
                'recent' => $request->user()->notifications()->latest()->limit(8)->get()->map(fn ($notification) => [
                    'id' => $notification->id,
                    'data' => $notification->data,
                    'read_at' => $notification->read_at?->toIso8601String(),
                    'created_at' => $notification->created_at->toIso8601String(),
                ]),
            ]) : null,
        ];
    }
}
