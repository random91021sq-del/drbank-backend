<?php

use App\Http\Controllers\AcademicAdvisoriesController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\PubSubController;
use App\Http\Controllers\SmartReviewController;
use Illuminate\Support\Facades\Route;

Route::prefix(env('APP_VERSION_ONE'))->group(function () {
    Route::prefix('auth')->group(base_path('routes/auth/auth.php'));
    Route::prefix('profile')->group(base_path('routes/profile/profile.php'));
    Route::prefix('external')->group(base_path('routes/external/external.php'));
    Route::prefix('quiz')->group(base_path('routes/quiz/quiz.php'));
    Route::prefix('student')->middleware('validateToken')->group(function () {
        Route::get('academic-advisores', [AcademicAdvisoriesController::class, 'listAcademicAdvisories']);
        Route::post('academic-advisores/hold', [AcademicAdvisoriesController::class, 'holdMeetingSlot']);
        Route::delete('academic-advisores/hold/{hold_token}', [AcademicAdvisoriesController::class, 'releaseMeetingSlot']);
        Route::post('academic-advisores', [AcademicAdvisoriesController::class, 'registerMeeting']);
    });
    Route::prefix('smart-review')->middleware('validateToken')->group(function () {
        Route::get('themes', [SmartReviewController::class, 'listThemesAdaptativeReview']);
        Route::get('blocks', [SmartReviewController::class, 'showSmartReview']);
        Route::get('blocks/{idStudyBlock}/pretests', [SmartReviewController::class, 'listBlockPretests'])
            ->whereNumber('idStudyBlock');
        Route::get('blocks/{idStudyBlock}/reviews', [SmartReviewController::class, 'listBlockReviews'])
            ->whereNumber('idStudyBlock');
        Route::get('blocks/{idStudyBlock}/posttests', [SmartReviewController::class, 'listBlockPosttests'])
            ->whereNumber('idStudyBlock');
        Route::delete('blocks/{idStudyBlock}', [SmartReviewController::class, 'deleteStudyBlock'])
            ->whereNumber('idStudyBlock');
        Route::get('due', [SmartReviewController::class, 'due']);
        Route::get('due/questions', [SmartReviewController::class, 'dueQuestions']);
        Route::post('review', [SmartReviewController::class, 'review']);
        Route::post('blocks', [SmartReviewController::class, 'createStoreBlock']);
        Route::post('blocks/{id}/pretest', [SmartReviewController::class, 'generatePretest']);
        Route::post('blocks/{idStudyBlock}/pretest/complete', [SmartReviewController::class, 'completePretest']);
        Route::get('blocks/{idStudyBlock}/posttest', [SmartReviewController::class, 'generatePosttest']);
        Route::post('blocks/{idStudyBlock}/posttest/complete', [SmartReviewController::class, 'completePosttest']);
    });
    Route::patch('student/academic-advisores/{id}', [AcademicAdvisoriesController::class, 'updateMeeting']);
    Route::get('doctors', [DoctorController::class, 'listDoctors']);
    Route::get('doctor/{doctor}/availability', [DoctorController::class, 'availability'])
        ->middleware('validateToken');
});

Route::post('pubsub-endpoint', [PubSubController::class, 'pubSubEndpoint']);
