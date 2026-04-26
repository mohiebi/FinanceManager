<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('api.auth.')->group(function () {
    Route::post('email/start', [AuthController::class, 'start'])->middleware('throttle:10,1')->name('email.start');
    Route::post('login/password', [AuthController::class, 'passwordLogin'])->middleware('throttle:login')->name('login.password');
    Route::post('recovery/send', [AuthController::class, 'sendRecovery'])->middleware('throttle:5,1')->name('recovery.send');
    Route::post('recovery/verify', [AuthController::class, 'verifyRecovery'])->middleware('throttle:10,1')->name('recovery.verify');
    Route::post('signup/verify', [AuthController::class, 'verifySignup'])->middleware('throttle:10,1')->name('signup.verify');
    Route::post('signup/complete', [AuthController::class, 'completeSignup'])->middleware('throttle:10,1')->name('signup.complete');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    });
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->name('api.')->group(function () {
    Route::apiResource('transactions', TransactionController::class);
    Route::apiResource('categories', CategoryController::class)->only(['index']);
});
