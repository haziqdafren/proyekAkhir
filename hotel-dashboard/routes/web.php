<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Redirect root to login
Route::get('/', function () {
    return redirect('/login');
});

// Auth routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    // Login with rate limiting: max 5 attempts per minute
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('manager');

// Protected dashboard routes - Manager only
Route::middleware('manager')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/predictions', [\App\Http\Controllers\PredictionController::class, 'index'])->name('predictions');
    Route::get('/predictions/single', [\App\Http\Controllers\PredictionController::class, 'singleOutput'])->name('predictions.single');
    Route::get('/predictions/multi', [\App\Http\Controllers\PredictionController::class, 'multiOutput'])->name('predictions.multi');
    Route::post('/predictions/generate-single', [\App\Http\Controllers\PredictionController::class, 'generateSingle'])->name('predictions.generate-single');
    Route::post('/predictions/generate-multi', [\App\Http\Controllers\PredictionController::class, 'generateMulti'])->name('predictions.generate-multi');
    Route::delete('/predictions/month/{year}/{month}/{type}', [\App\Http\Controllers\PredictionController::class, 'destroyMonth'])->name('predictions.destroy-month');
    Route::get('/history', [\App\Http\Controllers\HistoryController::class, 'index'])->name('history');
    Route::get('/export', [\App\Http\Controllers\ExportController::class, 'index'])->name('export');
    Route::post('/export/generate', [\App\Http\Controllers\ExportController::class, 'generate'])->name('export.generate');
    
    // Data Upload & Model Training routes
    Route::get('/data-upload', [\App\Http\Controllers\DataUploadController::class, 'index'])->name('data-upload');
    Route::get('/data-upload/download-template', [\App\Http\Controllers\DataUploadController::class, 'downloadTemplate'])->name('data-upload.download-template');
    Route::post('/data-upload/validate', [\App\Http\Controllers\DataUploadController::class, 'validateFile'])->name('data-upload.validate');
    Route::post('/data-upload/upload', [\App\Http\Controllers\DataUploadController::class, 'upload'])->name('data-upload.upload');
    Route::get('/data-upload/status/{upload}', [\App\Http\Controllers\DataUploadController::class, 'status'])->name('data-upload.status');
    Route::post('/data-upload/retrain', [\App\Http\Controllers\DataUploadController::class, 'retrain'])->name('data-upload.retrain');
    Route::post('/data-upload/retry/{upload}', [\App\Http\Controllers\DataUploadController::class, 'retry'])->name('data-upload.retry');
    Route::delete('/data-upload/{upload}', [\App\Http\Controllers\DataUploadController::class, 'destroy'])->name('data-upload.destroy');
    Route::get('/model-versions', [\App\Http\Controllers\DataUploadController::class, 'modelVersions'])->name('model-versions');
});
