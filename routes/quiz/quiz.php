<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\ExamTypeController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\SpecialtyController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\YearController;
use Illuminate\Support\Facades\Route;

Route::middleware('validateToken')->group(function () {
    Route::post('questions', [QuestionController::class, 'questionGroup'])->middleware('throttle:questions');
    Route::get('exam-type', [ExamTypeController::class, 'ExamType'])->middleware('throttle:exam-type');
    Route::get('specialty', [SpecialtyController::class, 'specialty'])->middleware('throttle:specialty');
    Route::get('year', [YearController::class, 'year'])->middleware('throttle:year');
    Route::post('by-year', [QuestionController::class, 'examTypeYear'])->middleware('throttle:by-year');
    Route::get('theme', [ThemeController::class, 'listThemes'])->middleware('throttle:theme');
    Route::post('question/theme', [QuestionController::class, 'questionsByTheme'])->middleware('throttle:question-theme');
    Route::post('report', [QuestionController::class, 'report'])->middleware('throttle:report');
    Route::post('history', [QuestionController::class, 'history'])->middleware("throttle:history");
    Route::get('history', [QuestionController::class, 'userHistory'])->middleware('throttle:history-user');
    Route::post('ranking', [QuestionController::class, 'ranking'])->middleware("throttle:register-ranking");
    Route::get('ranking', [QuestionController::class, 'userRanking'])->middleware('throttle:list-ranking');
    Route::get('area', [AreaController::class, 'areas'])->middleware('throttle:area');
    Route::post('exam',[QuestionController::class, 'saveExamRegister'])->middleware('throttle:exam');
    Route::patch('exam/status',[QuestionController::class, 'updateExamStatus'])->middleware('throttle:exam-status');
    Route::get('exam',[QuestionController::class, 'getUserExams'])->middleware('throttle:exam-user');
    Route::post('exam/download-summary',[QuestionController::class, 'downloadExamSummary'])->middleware('throttle:exam-download-summary');
});
