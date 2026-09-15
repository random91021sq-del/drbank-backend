<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('validateToken')->group(function () {
    Route::post('notifications', [AuthController::class, 'profileNotification'])->middleware('throttle:notifications');
    Route::post('update', [AuthController::class, 'profileUpdate'])->middleware(['throttle:update']);
    Route::post('change-password', [AuthController::class, 'changePassword'])->middleware('throttle:change-password');
});
