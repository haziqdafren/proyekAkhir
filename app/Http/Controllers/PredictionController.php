<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Exception;

class PredictionController extends Controller
{
    /**
     * URL Flask API untuk prediction
     *
     * @var string
     */
    private $flaskApiUrl = 'http://localhost:5000/api';

    /**
     * Display dashboard prediksi okupansi
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('prediction.dashboard');
    }

    /**
     * Get historical data untuk chart
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getHistoricalData()
    {
        try {
            // Call Flask API untuk get historical data
            $response = Http::timeout(30)->get("{$this->flaskApiUrl}/historical");

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch historical data'
            ], 500);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error connecting to ML service: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate prediction untuk N months ke depan
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function predict(Request $request)
    {
        $request->validate([
            'months_ahead' => 'required|integer|min:1|max:12',
            'room_types' => 'array'
        ]);

        try {
            $response = Http::timeout(60)->post("{$this->flaskApiUrl}/predict", [
                'months_ahead' => $request->months_ahead,
                'room_types' => $request->room_types ?? ['STD', 'SPR', 'FMY', 'JS']
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return response()->json([
                    'success' => true,
                    'predictions' => $data['predictions'],
                    'metrics' => $data['metrics'] ?? null,
                    'timestamp' => now()->toDateTimeString()
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Prediction failed: ' . $response->body()
            ], 500);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during prediction: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get model performance metrics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMetrics()
    {
        try {
            $response = Http::timeout(30)->get("{$this->flaskApiUrl}/metrics");

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'metrics' => $response->json()
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch metrics'
            ], 500);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching metrics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Health check - cek apakah Flask API running
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function healthCheck()
    {
        try {
            $response = Http::timeout(5)->get("{$this->flaskApiUrl}/health");

            return response()->json([
                'success' => $response->successful(),
                'status' => $response->successful() ? 'ML service is running' : 'ML service is down',
                'timestamp' => now()->toDateTimeString()
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 'ML service is not reachable',
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString()
            ], 503);
        }
    }
}
