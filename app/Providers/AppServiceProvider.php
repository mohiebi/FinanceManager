<?php

namespace App\Providers;

use App\Actions\Gamification\AwardMilestones;
use App\Contracts\AdvisorKnowledgeProvider;
use App\Contracts\Billing\AddressScreener;
use App\Services\Advisor\ModelOnlyAdvisorKnowledgeProvider;
use App\Services\Billing\AddressScreenerFactory;
use App\Support\Encryption\UserKeyRing;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped, never singleton: a singleton survives across jobs in a
        // long-running queue worker, so one user's data key could leak into
        // another user's job.
        $this->app->scoped(UserKeyRing::class);

        // Scoped for the same reason, plus one of its own: the transaction
        // observer resolves this per model event, so without a shared instance
        // its per-user memo is rebuilt for every row the CSV importer writes.
        $this->app->scoped(AwardMilestones::class);
        $this->app->bind(AdvisorKnowledgeProvider::class, ModelOnlyAdvisorKnowledgeProvider::class);
        $this->app->bind(AddressScreener::class, fn (): AddressScreener => app(AddressScreenerFactory::class)->configured());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAdvisorRateLimiting();
        $this->configurePassport();
        $this->configureSsr();
    }

    /**
     * Server-render only what benefits from it.
     *
     * A signed-in page is never crawled and never shared, so SSR buys it
     * nothing -- while still requiring the server's markup to match, node for
     * node, what the browser builds from the same props. Dashboard did not
     * match, and Vue cannot always patch cleanly past a mismatch, which is how
     * a page renders but stops responding.
     *
     * Google sign-in was only ever the messenger: it is the one route that
     * reaches an authenticated page through a full browser navigation. Email
     * login arrives over an Inertia XHR and renders client-side, so it never
     * hydrated and never showed the fault.
     *
     * Landing and the auth screens keep SSR, which is where the SEO and
     * first-paint value actually is.
     */
    protected function configureSsr(): void
    {
        Inertia::disableSsr(fn (): bool => Auth::check());
    }

    protected function configureAdvisorRateLimiting(): void
    {
        RateLimiter::for('advisor-recommendations', function (Request $request) {
            if (app()->isLocal()) {
                return Limit::none();
            }

            return Limit::perDay(5)
                ->by('advisor-recommendations:'.($request->user()?->getAuthIdentifier() ?? $request->ip()))
                ->after(fn (Response $response): bool => $response->getStatusCode() < 500)
                ->response(fn (Request $_request, array $headers): JsonResponse => response()->json([
                    'message' => __('advisor.validation.recommendation_rate_limited'),
                ], 429, $headers));
        });

        RateLimiter::for('advisor-clarifications', function (Request $request) {
            if (app()->isLocal()) {
                return Limit::none();
            }

            return Limit::perDay(5)
                ->by('advisor-clarifications:'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        RateLimiter::for('advisor-consultations', function (Request $request) {
            if (app()->isLocal()) {
                return Limit::none();
            }

            return Limit::perDay(30)
                ->by('advisor-consultations:'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });
    }

    /**
     * Configure Passport for the MCP OAuth 2.1 flow used by AI clients.
     */
    protected function configurePassport(): void
    {
        Passport::loadKeysFrom(storage_path('passport'));
        Passport::authorizationView('mcp.authorize');
        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));

        RateLimiter::for('mcp', function (Request $request): Limit {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(60)->by('mcp:'.$key);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Throws on lazy loading, silent attribute discard, and missing attribute
        // access during development/testing; in production we log instead of crash.
        Model::shouldBeStrict(! app()->isProduction());

        if (app()->isProduction()) {
            Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
                logger()->warning('Lazy loading violation', [
                    'model' => $model::class,
                    'relation' => $relation,
                ]);
            });
        }

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
