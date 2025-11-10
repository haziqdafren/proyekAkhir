<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PredictionController;

/*
|--------------------------------------------------------------------------
| Web Routes - Hotel Occupancy Prediction Dashboard
|--------------------------------------------------------------------------
|
| Routes untuk dashboard prediksi okupansi Hotel Dharma Utama
| menggunakan LSTM model
|
*/

// Landing page - redirect ke dashboard
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Dashboard Routes
Route::prefix('dashboard')->group(function () {

    // Main dashboard page
    Route::get('/', [PredictionController::class, 'index'])
        ->name('dashboard');

    // API endpoints untuk dashboard
    Route::prefix('api')->group(function () {

        // Get historical occupancy data
        Route::get('/historical', [PredictionController::class, 'getHistoricalData'])
            ->name('api.historical');

        // Generate prediction
        Route::post('/predict', [PredictionController::class, 'predict'])
            ->name('api.predict');

        // Get model performance metrics
        Route::get('/metrics', [PredictionController::class, 'getMetrics'])
            ->name('api.metrics');

        // Health check - cek ML service status
        Route::get('/health', [PredictionController::class, 'healthCheck'])
            ->name('api.health');
    });
});
