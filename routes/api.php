<?php

use App\Http\Controllers\AcademicAdvisoriesController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\PubSubController;
use App\Http\Controllers\StudentProgressController;
use Illuminate\Support\Facades\Route;

Route::prefix(env('APP_VERSION_ONE'))->group(function () {
    Route::prefix('auth')->group(base_path('routes/auth/auth.php'));
    Route::prefix('profile')->group(base_path('routes/profile/profile.php'));
    Route::prefix('external')->group(base_path('routes/external/external.php'));
    Route::prefix('quiz')->group(base_path('routes/quiz/quiz.php'));
    Route::prefix('student')->middleware('validateToken')->group(function () {
        Route::get('progress', [StudentProgressController::class, 'getProgress']);
        Route::post('mark-topic-studied', [StudentProgressController::class, 'markAsStudied']);
        Route::get('academic-advisores', [AcademicAdvisoriesController::class, 'listAcademicAdvisories']);
        Route::post('academic-advisores/hold', [AcademicAdvisoriesController::class, 'holdMeetingSlot']);
        Route::delete('academic-advisores/hold/{hold_token}', [AcademicAdvisoriesController::class, 'releaseMeetingSlot']);
        Route::post('academic-advisores', [AcademicAdvisoriesController::class, 'registerMeeting']);
    });
    Route::patch('student/academic-advisores/{id}', [AcademicAdvisoriesController::class, 'updateMeeting']);
    Route::get('doctors', [DoctorController::class, 'listDoctors']);
    Route::get('doctor/{doctor}/availability', [DoctorController::class, 'availability'])
        ->middleware('validateToken');
});

Route::post('pubsub-endpoint', [PubSubController::class, 'pubSubEndpoint']);
