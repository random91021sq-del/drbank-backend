<?php

use App\Http\Controllers\FeedbackController;
use Illuminate\Support\Facades\Route;

Route::middleware('validateToken')->group(function(){
    Route::post('support', [FeedbackController::class, 'feedbackClient'])->middleware(['throttle:support']);
});