<?php

use App\Http\Controllers\AdminBillingController;
use App\Http\Controllers\AdminCouponController;
use App\Http\Controllers\AdminCustomerExportController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdvisorAssessmentController;
use App\Http\Controllers\AdvisorConsultationController;
use App\Http\Controllers\AdvisorController;
use App\Http\Controllers\AdvisorProfileController;
use App\Http\Controllers\AdvisorRecommendationController;
use App\Http\Controllers\AssetPriceSyncController;
use App\Http\Controllers\Auth\EmailAuthPageController;
use App\Http\Controllers\Auth\WebEmailAuthController;
use App\Http\Controllers\Auth\WebGoogleAuthController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\InvestmentAssetController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\InvestmentExportController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NoSpendDayController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\PortfolioExportController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavingsGoalController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionExportController;
use App\Http\Controllers\TransactionImportController;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureProfileIsComplete;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\RejectWhenVaultArmed;
use App\Support\FrontendLocalization;
use App\Support\SeoMetadata;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    $request->session()->forget('locale');

    return Inertia::render('Landing');
})->name('home');

Route::get('sitemap.xml', fn () => response(SeoMetadata::sitemapXml(), 200, [
    'Content-Type' => 'application/xml; charset=UTF-8',
]))->name('sitemap');

Route::get('{locale}', function (Request $request, string $locale) {
    $locale = FrontendLocalization::normalizeLocale($locale);

    $request->session()->put('locale', $locale);
    app()->setLocale($locale);

    return Inertia::render('Landing');
})->where('locale', implode('|', FrontendLocalization::locales()))
    ->name('home.localized');

// Works for guests (session) and authenticated users (profile) alike.
Route::post('locale', LocaleController::class)
    ->middleware('throttle:20,1')
    ->name('locale.update');

Route::middleware('guest')->group(function () {
    Route::get('forgot-password', EmailAuthPageController::class)->name('password.request');

    Route::post('auth/email/start', [WebEmailAuthController::class, 'start'])
        ->middleware('throttle:10,1')
        ->name('auth.email.start');

    Route::post('auth/email/reset', [WebEmailAuthController::class, 'reset'])
        ->name('auth.email.reset');

    Route::post('auth/login/password', [WebEmailAuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login.password');

    Route::post('auth/recovery/send', [WebEmailAuthController::class, 'sendRecovery'])
        ->middleware('throttle:5,1')
        ->name('auth.recovery.send');

    Route::post('auth/recovery/verify', [WebEmailAuthController::class, 'verifyRecovery'])
        ->middleware('throttle:10,1')
        ->name('auth.recovery.verify');

    Route::post('auth/signup/verify', [WebEmailAuthController::class, 'verifySignup'])
        ->middleware('throttle:10,1')
        ->name('auth.signup.verify');

    Route::post('auth/signup/complete', [WebEmailAuthController::class, 'completeSignup'])
        ->middleware('throttle:10,1')
        ->name('auth.signup.complete');

    Route::get('auth/google/redirect', [WebGoogleAuthController::class, 'redirect'])
        ->name('auth.google.redirect');

    Route::get('auth/google/callback', [WebGoogleAuthController::class, 'callback'])
        ->name('auth.google.callback');
});

Route::middleware(['auth', 'verified', EnsureProfileIsComplete::class])->group(function () {
    Route::get('admin', AdminDashboardController::class)
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.dashboard');

    Route::get('admin/customers/export', AdminCustomerExportController::class)
        ->middleware(EnsureUserIsAdmin::class)
        ->name('admin.customers.export');

    Route::middleware(EnsureUserIsAdmin::class)->group(function () {
        Route::get('admin/billing', [AdminBillingController::class, 'index'])->name('admin.billing');

        // A second factor on top of the admin check, which is one string compare
        // against a configured email. These routes hand out paid entitlements,
        // so they get the same treatment as arming the vault.
        Route::middleware([RequirePassword::class, 'throttle:30,1'])->group(function () {
            Route::post('admin/billing/payments/{payment}/approve', [AdminBillingController::class, 'approve'])->name('admin.billing.approve');
            Route::post('admin/billing/payments/{payment}/reject', [AdminBillingController::class, 'reject'])->name('admin.billing.reject');
            Route::post('admin/billing/payments/{payment}/recheck', [AdminBillingController::class, 'recheck'])->name('admin.billing.recheck');
            Route::post('admin/billing/deposits/{depositAddress}/authorize-sweep', [AdminBillingController::class, 'authorizeSweep'])->name('admin.billing.sweeps.authorize');
            Route::post('admin/billing/deposits/{depositAddress}/record-sweep', [AdminBillingController::class, 'recordSweep'])->name('admin.billing.sweeps.record');
            Route::post('admin/billing/users/{user}/grant', [AdminBillingController::class, 'grant'])->name('admin.billing.grant');
            Route::post('admin/billing/users/{user}/revoke', [AdminBillingController::class, 'revoke'])->name('admin.billing.revoke');

            // Coupons give away paid access, so they sit behind the same second
            // factor as granting it directly.
            Route::post('admin/billing/coupons', [AdminCouponController::class, 'store'])->name('admin.coupons.store');
            Route::post('admin/billing/coupons/{coupon}/disable', [AdminCouponController::class, 'disable'])->name('admin.coupons.disable');
            Route::post('admin/billing/coupons/{coupon}/enable', [AdminCouponController::class, 'enable'])->name('admin.coupons.enable');
            Route::delete('admin/billing/coupons/{coupon}', [AdminCouponController::class, 'destroy'])->name('admin.coupons.destroy');
        });
    });

    Route::get('dashboard', [TransactionController::class, 'dashboard'])->name('dashboard');

    /*
     * Deliberately outside the feature gate below: a user without the Pro
     * entitlement has nothing to switch on, so bouncing them to the modules
     * page shows them a locked row and no way forward. The controller answers
     * for itself and renders the paywall instead.
     */
    Route::get('advisor', [AdvisorController::class, 'index'])->name('advisor.index');

    Route::middleware(EnsureFeatureEnabled::class.':advisor')->prefix('advisor')->name('advisor.')->group(function () {
        Route::post('assessments', [AdvisorController::class, 'store'])->name('assessments.store');
        Route::get('assessment/{assessment}', [AdvisorAssessmentController::class, 'show'])->name('assessments.show');
        Route::patch('assessment/{assessment}/sections/{section}', [AdvisorAssessmentController::class, 'updateSection'])->whereNumber('section')->name('assessments.sections.update');
        Route::post('assessment/{assessment}/complete', [AdvisorAssessmentController::class, 'complete'])->name('assessments.complete');
        Route::get('profile', AdvisorProfileController::class)->name('profile');

        Route::post('recommendations', [AdvisorRecommendationController::class, 'store'])->middleware('throttle:advisor-recommendations')->name('recommendations.store');
        Route::post('recommendations/{recommendation}/clarifications', [AdvisorRecommendationController::class, 'clarify'])->middleware('throttle:advisor-clarifications')->name('recommendations.clarify');
        Route::post('recommendations/{recommendation}/claim', [AdvisorRecommendationController::class, 'claim'])->name('recommendations.claim');
        Route::patch('recommendations/{recommendation}/seal', [AdvisorRecommendationController::class, 'seal'])->name('recommendations.seal');
        Route::get('recommendations/{recommendation}', [AdvisorRecommendationController::class, 'show'])->name('recommendations.show');
        Route::post('recommendations/{recommendation}/consult', [AdvisorConsultationController::class, 'store'])->middleware('throttle:advisor-consultations')->name('recommendations.consult');
        Route::post('recommendations/{recommendation}/messages/seal', [AdvisorConsultationController::class, 'seal'])->name('recommendations.messages.seal');
    });
    Route::get('transactions/import-template', [TransactionImportController::class, 'template'])->name('transactions.import-template');

    // Spreadsheets are built and parsed server-side, so neither survives a server
    // that cannot read the data.
    Route::middleware(RejectWhenVaultArmed::class)->group(function () {
        Route::post('transactions/imports/preview', [TransactionImportController::class, 'preview'])->name('transactions.imports.preview');
        Route::post('transactions/imports', [TransactionImportController::class, 'store'])->name('transactions.imports.store');
        Route::get('transactions/export', TransactionExportController::class)->name('transactions.export');
    });
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::delete('transactions/bulk', [TransactionController::class, 'destroyBulk'])->name('transactions.destroy-bulk');
    Route::patch('transactions/bulk/category', [TransactionController::class, 'updateBulkCategory'])->name('transactions.update-bulk-category');
    Route::resource('transactions', TransactionController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('reports', ReportController::class)->name('report');

    Route::middleware(EnsureFeatureEnabled::class.':gamification')->group(function () {
        Route::post('no-spend-days', [NoSpendDayController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('no-spend-days.store');
    });

    Route::middleware(EnsureFeatureEnabled::class.':investments')->group(function () {
        Route::post('investment-assets', [InvestmentAssetController::class, 'store'])->name('investment-assets.store');
        Route::get('investments/export', InvestmentExportController::class)
            ->middleware(RejectWhenVaultArmed::class)
            ->name('investments.export');
        Route::post('investments/sell', [InvestmentController::class, 'sell'])->name('investments.sell');
        Route::resource('investments', InvestmentController::class)->only(['index', 'store', 'update', 'destroy']);
        // Forces a real outbound scrape against tgju.org when cache is cold, so this is
        // throttled stricter than the codebase's usual 10,1 / 5,1 write endpoints.
        Route::post('asset-prices/sync', AssetPriceSyncController::class)
            ->middleware('throttle:3,1')
            ->name('asset-prices.sync');
    });

    Route::middleware(EnsureFeatureEnabled::class.':portfolio')->group(function () {
        Route::get('portfolio/export', PortfolioExportController::class)
            ->middleware(RejectWhenVaultArmed::class)
            ->name('portfolio.export');
        Route::get('portfolio', PortfolioController::class)->name('portfolio');
    });

    // Its own module rather than part of the portfolio's: progress is a ratio of
    // holdings, so goals need Investments — not net worth and P&L.
    //
    // Deliberately not behind RejectWhenVaultArmed: goals are sealed in the
    // browser and their progress is computed there, so working with the vault
    // armed is the point rather than an edge case.
    Route::middleware(EnsureFeatureEnabled::class.':goals')->group(function () {
        Route::get('goals', GoalController::class)->name('goals');

        Route::resource('savings-goals', SavingsGoalController::class)
            ->only(['store', 'update', 'destroy']);

        // Only the browser knows a sealed goal is finished, so it reports the
        // date. Write-once server-side, and throttled because it fires from a
        // watcher rather than a click.
        Route::post('savings-goals/{savingsGoal}/achieved', [SavingsGoalController::class, 'markAchieved'])
            ->middleware('throttle:20,1')
            ->name('savings-goals.achieved');
    });

    // Deliberately not behind RejectWhenVaultArmed: percentages are plaintext and
    // the allowances are resolved in the browser, so working with the vault armed
    // is the point rather than an edge case.
    Route::middleware(EnsureFeatureEnabled::class.':budgets')->group(function () {
        Route::resource('budgets', BudgetController::class)
            ->only(['index', 'store', 'update', 'destroy']);
    });

    Route::middleware(EnsureFeatureEnabled::class.':bills')->group(function () {
        Route::resource('bills', BillController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('bills/{bill}/occurrences/{occurrence}/pay', [BillController::class, 'markPaid'])
            ->name('bills.occurrences.pay');
    });
});

require __DIR__.'/settings.php';
