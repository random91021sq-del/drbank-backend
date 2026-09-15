<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register'])->middleware(['throttle:register']);
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('refresh', [AuthController::class, 'refresh'])->middleware(['throttle:refresh','validateToken']);
Route::post('activation', [AuthController::class, 'activationClient'])->middleware('throttle:activation');
Route::post('recovery', [AuthController::class, 'recoverPassword'])->middleware('throttle:recovery');
Route::post('resend-activation', [AuthController::class, 'resendActivation'])->middleware('throttle:resend-activation');
Route::post('recovery-validation', [AuthController::class, 'recoveryValidation'])->middleware('throttle:recovery-validation');
Route::post('recovery-password', [AuthController::class, 'generateNewPassword'])->middleware(['throttle:recovery-password']);
Route::post('social',[LoginController::class,'socialLogin'])->middleware('throttle:social');
Route::middleware('validateToken')->group(function () {
    Route::get('me', [AuthController::class, 'clientProfile'])->middleware('throttle:me');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('throttle:logout');
    Route::delete('delete', [AuthController::class, 'profileDelete'])->middleware('throttle:delete');
});
