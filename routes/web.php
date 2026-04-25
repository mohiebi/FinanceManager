<?php

use App\Http\Controllers\Auth\EmailAuthPageController;
use App\Http\Controllers\Auth\WebEmailAuthController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('forgot-password', EmailAuthPageController::class)->name('password.request');

    Route::post('auth/email/start', [WebEmailAuthController::class, 'start'])
        ->middleware('throttle:10,1')
        ->name('auth.email.start');

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
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [TransactionController::class, 'index'])->name('dashboard');
    Route::resource('transactions', TransactionController::class)->only(['store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
