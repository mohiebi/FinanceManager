<?php

use App\Http\Controllers\Auth\EmailAuthPageController;
use App\Http\Controllers\Auth\WebEmailAuthController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome', [
    'canRegister' => true,
])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('register', EmailAuthPageController::class)->name('register');
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
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
