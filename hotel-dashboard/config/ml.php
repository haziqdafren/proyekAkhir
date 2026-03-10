<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Machine Learning Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for ML model integration (LSTM predictions)
    |
    */

    // Flask API URL for model serving and retraining
    'flask_api_url' => env('ML_FLASK_API_URL', 'http://127.0.0.1:5000'),

    // Python executable - use virtual environment
    'python_path' => env('ML_PYTHON_PATH', base_path('venv/bin/python3')),

    // Model paths
    'models' => [
        'single_output' => env('ML_SINGLE_MODEL_PATH', base_path('../proyekAkhir/single_output/lstm_single.keras')),
        'multi_output' => env('ML_MULTI_MODEL_PATH', base_path('../proyekAkhir/multi_output/lstm_multi_output.keras')),
    ],

    // Model metadata
    'single_output' => [
        'name' => 'Single-Output LSTM',
        'version' => '1.0',
        'mape' => 21.28,
        'r2' => 0.1553,
        'recommended' => true,
    ],

    'multi_output' => [
        'name' => 'Multi-Output LSTM',
        'version' => '1.0',
        'mape' => 28.41,
        'r2' => 0.0088,
        'recommended' => false,
    ],

    // Hotel configuration - Hotel Dharma Utama
    'total_rooms' => env('HOTEL_TOTAL_ROOMS', 56),
    
    'room_capacities' => [
        'STD' => 27,  // Standard Room
        'SPR' => 23,  // Superior Room
        'JS' => 2,    // Junior Suite
        'FMY' => 4,   // Family Room
    ],

    // Feature engineering
    'sequence_length' => 6, // Number of months used as input
    'num_features' => 15, // Number of features per month
    
    'peak_months' => [6, 7, 12], // June, July, December

    // Prediction settings
    'confidence_interval' => 0.05, // ±5% for display
    'cache_ttl' => 3600, // Cache predictions for 1 hour
];
