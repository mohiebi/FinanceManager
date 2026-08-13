<?php

namespace App\Providers;

use App\Actions\Gamification\AwardMilestones;
use App\Contracts\AdvisorKnowledgeProvider;
use App\Services\Advisor\ModelOnlyAdvisorKnowledgeProvider;
use App\Support\Encryption\UserKeyRing;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePassport();
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
