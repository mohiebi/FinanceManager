<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\InvestmentAssetController;
use App\Http\Controllers\Settings\AiConnectionsController;
use App\Http\Controllers\Settings\BillingController;
use App\Http\Controllers\Settings\ModuleController;
use App\Http\Controllers\Settings\NotificationController;
use App\Http\Controllers\Settings\PreferencesController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\TelegramController;
use App\Http\Controllers\Settings\VaultController;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureProfileIsComplete;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified', EnsureProfileIsComplete::class])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/notifications', [NotificationController::class, 'edit'])->name('notifications.edit');
    Route::patch('settings/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::patch('settings/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('settings/notifications/preferences', [NotificationController::class, 'updatePreferences'])->name('notifications.preferences');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::put('settings/security/confirm-password', [SecurityController::class, 'confirmPassword'])
        ->middleware('throttle:6,1')
        ->name('security.confirm-password');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    Route::get('settings/preferences', [PreferencesController::class, 'edit'])->name('preferences.edit');
    Route::patch('settings/preferences', [PreferencesController::class, 'update'])->name('preferences.update');

    Route::get('settings/modules', [ModuleController::class, 'edit'])->name('modules.edit');
    Route::patch('settings/modules', [ModuleController::class, 'update'])->name('modules.update');

    // Billing is not a Feature module, so it carries no EnsureFeatureEnabled —
    // and no RejectWhenVaultArmed either: these rows are plaintext by design,
    // because the worker that settles a payment has no data key.
    //
    // Registered unconditionally and gated inside the controller. Registering on
    // config('billing.enabled') would break route caching and leave Wayfinder
    // with nothing to generate.
    Route::get('settings/billing', [BillingController::class, 'edit'])->name('billing.edit');

    // Read-only: it prices a code without claiming a use, so it can be called
    // as often as somebody retypes one.
    Route::post('settings/billing/coupon', [BillingController::class, 'previewCoupon'])
        ->middleware('throttle:20,1')
        ->name('billing.coupon.preview');

    Route::post('settings/billing/payments', [BillingController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('billing.payments.store');

    // Tighter, because each submission fans out into outbound calls to a node.
    Route::post('settings/billing/payments/{payment}/proof', [BillingController::class, 'submitProof'])
        ->middleware('throttle:6,1')
        ->name('billing.payments.proof');

    Route::delete('settings/billing/payments/{payment}', [BillingController::class, 'destroy'])
        ->middleware('throttle:10,1')
        ->name('billing.payments.cancel');

    // The vault's own endpoints. Enrolling hands over the raw data key, so it sits
    // behind a fresh password confirmation; the other two carry client-produced
    // material and verify it against the fingerprint on record.
    Route::post('settings/vault/enroll', [VaultController::class, 'enroll'])
        ->middleware([RequirePassword::class, 'throttle:6,1'])
        ->name('vault.enroll');

    Route::post('settings/vault/enable', [VaultController::class, 'enable'])
        ->middleware('throttle:6,1')
        ->name('vault.enable');

    Route::post('settings/vault/disable', [VaultController::class, 'disable'])
        ->middleware('throttle:6,1')
        ->name('vault.disable');

    Route::get('settings/categories', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::patch('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::middleware(EnsureFeatureEnabled::class.':investments')->group(function () {
        Route::get('settings/assets', [InvestmentAssetController::class, 'edit'])->name('investment-assets.edit');
        Route::patch('investment-assets/{investment_asset}', [InvestmentAssetController::class, 'update'])->name('investment-assets.update');
        Route::delete('investment-assets/{investment_asset}', [InvestmentAssetController::class, 'destroy'])->name('investment-assets.destroy');
    });

    Route::middleware(EnsureFeatureEnabled::class.':ai_assistant')->group(function () {
        Route::get('settings/ai-connections', [AiConnectionsController::class, 'edit'])->name('ai-connections.edit');
        Route::delete('settings/ai-connections', [AiConnectionsController::class, 'revokeAll'])->name('ai-connections.revoke-all');
        Route::delete('settings/ai-connections/{token}', [AiConnectionsController::class, 'destroy'])->name('ai-connections.destroy');
    });

    Route::middleware(EnsureFeatureEnabled::class.':telegram_bot')->group(function () {
        Route::get('settings/telegram', [TelegramController::class, 'edit'])->name('telegram.edit');
        Route::post('settings/telegram/connect', [TelegramController::class, 'connect'])->name('telegram.connect');
        Route::delete('settings/telegram', [TelegramController::class, 'disconnect'])->name('telegram.disconnect');
    });
});
