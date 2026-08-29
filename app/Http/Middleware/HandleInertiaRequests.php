<?php

namespace App\Http\Middleware;

use App\Support\FrontendLocalization;
use App\Support\SeoMetadata;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * Never let a browser or proxy reuse an Inertia response across a deploy.
     *
     * The root template carries three things that are only valid for the build
     * that produced it: the server-rendered markup, the hashed asset URLs, and
     * the Inertia asset version — which is a hash of the Vite manifest, so it
     * changes every single deploy.
     *
     * Laravel's default `no-cache, private` still permits *storing* the
     * response; it only asks for revalidation, and the back/forward cache
     * ignores even that. A document served from that store after a deploy
     * hydrates the new JS bundle against the previous build's markup, which is
     * how a page ends up half-updated: the shell hydrates, the page subtree
     * does not, and Inertia's later visits swap the header while the stale
     * body stays on screen.
     *
     * `no-store` is the only directive that actually forbids keeping it.
     * Hashed assets under /build are immutable and cache normally — this is
     * about the one document that must always be fetched fresh.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);

        if ($this->shouldPreventCaching($response)) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        $this->logDocumentRender($request, $response);

        return $response;
    }

    /**
     * Record every full-document render, and whether SSR markup reached the HTML.
     *
     * This is the request the Google callback redirects into, and the only kind
     * of request that server-renders an authenticated page at all -- an Inertia
     * XHR visit returns JSON and never runs SSR. Logging it makes the two entry
     * paths directly comparable: sign in with Google, then press F5 on the same
     * page, and compare the two lines.
     *
     * Error level on purpose; the container runs at LOG_LEVEL=error.
     */
    private function logDocumentRender(Request $request, Response $response): void
    {
        if (! config('diagnostics.log_document_renders')) {
            return;
        }

        // An Inertia visit is XHR and carries this header; its absence is what
        // makes this a real browser navigation.
        if ($request->headers->has('X-Inertia') || $response->isRedirection()) {
            return;
        }

        if (! str_contains((string) $response->headers->get('Content-Type', ''), 'text/html')) {
            return;
        }

        $content = (string) $response->getContent();
        $ssr = $this->ssrMarkupPresent($content);

        Log::error(sprintf(
            '[document] component=%s ssr=%s authed=%s path=/%s referer=%s',
            $this->componentFrom($content) ?? '-',
            $ssr === null ? '?' : ($ssr ? 'yes' : 'NO'),
            $request->user() ? 'yes' : 'no',
            ltrim($request->path(), '/'),
            $request->headers->get('referer') ?? '-',
        ), [
            'user_id' => $request->user()?->id,
            'inertia_version' => $this->version($request),
        ]);
    }

    /**
     * Whether Inertia's root element actually carries server-rendered markup.
     *
     * When SSR fails, Inertia still returns a valid document -- just with an
     * empty root for the client to render into. That fallback is invisible in
     * an access log: same URL, same 200, only a smaller body. This is the
     * difference that matters, so it is read straight off the markup rather
     * than inferred from response size.
     */
    private function ssrMarkupPresent(string $content): ?bool
    {
        $anchor = strpos($content, 'id="app"');

        if ($anchor === false) {
            return null;
        }

        $tagEnd = strpos($content, '>', $anchor);

        if ($tagEnd === false) {
            return null;
        }

        return ! str_starts_with(ltrim(substr($content, $tagEnd + 1, 32)), '</div>');
    }

    /** The page component, read out of the embedded Inertia payload. */
    private function componentFrom(string $content): ?string
    {
        // Inertia v3 embeds the payload as raw JSON in a <script> tag, so this
        // is unescaped -- confirmed against the rendered document, not assumed.
        if (! preg_match('/"component"\s*:\s*"([^"]{1,120})"/', $content, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Only the HTML shell and Inertia's own JSON — never a streamed download or
     * a redirect, which carry no markup and no asset version.
     */
    private function shouldPreventCaching(Response $response): bool
    {
        if ($response->isRedirection()) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'text/html')
            || str_contains($contentType, 'application/json');
    }

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
