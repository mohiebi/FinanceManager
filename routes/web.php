<?php

use App\Http\Controllers\Auth\EmailAuthPageController;
use App\Http\Controllers\Auth\WebEmailAuthController;
use App\Http\Controllers\Auth\WebGoogleAuthController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionImportController;
use App\Http\Middleware\EnsureProfileIsComplete;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Landing')->name('home');

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
    Route::get('dashboard', [TransactionController::class, 'dashboard'])->name('dashboard');
    Route::get('reports', ReportController::class)->name('report');
    Route::get('transactions/import-template', [TransactionImportController::class, 'template'])->name('transactions.import-template');
    Route::post('transactions/imports/preview', [TransactionImportController::class, 'preview'])->name('transactions.imports.preview');
    Route::post('transactions/imports', [TransactionImportController::class, 'store'])->name('transactions.imports.store');
    Route::resource('transactions', TransactionController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('investments', InvestmentController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('portfolio', PortfolioController::class)->name('portfolio');
});

require __DIR__.'/settings.php';
