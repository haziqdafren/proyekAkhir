<?php

namespace App\Services;

use App\Models\RoomType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MLPredictionService
{
    /**
     * Flask API URL
     */
    private string $flaskApiUrl;

    /**
     * Total rooms — resolved from DB at runtime so room config changes are reflected
     */
    private function getTotalRooms(): int
    {
        static $cached = null;
        if ($cached === null) {
            $cached = (int) RoomType::where('is_active', true)->sum('total_rooms') ?: 56;
        }
        return $cached;
    }

    /**
     * Normalization range used in training
     */
    private const NORM_MIN = 0;
    private const NORM_MAX = 1;

    private string $flaskApiKey;

    public function __construct()
    {
        $this->flaskApiUrl = config('ml.flask_api_url', 'http://127.0.0.1:5000');
        $this->flaskApiKey = config('ml.flask_api_key', '');
    }

    private function flaskHeaders(): array
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($this->flaskApiKey) {
            $headers['X-API-Key'] = $this->flaskApiKey;
        }
        return $headers;
    }

    /**
     * Make prediction using single-output model (total occupancy)
     *
     * @param array $features Array of shape [6, 15] - 6 months of 15 features
     * @return array Prediction result with denormalized values
     * @throws RuntimeException
     */
    public function predictSingleOutput(array $features): array
    {
        // Validate features shape
        if (!$this->validateFeatures($features)) {
            throw new RuntimeException('Invalid features shape. Expected [6, 15]');
        }

        try {
            // Call Flask API
            $response = Http::timeout(30)->withHeaders($this->flaskHeaders())->post("{$this->flaskApiUrl}/api/predict", [
                'model_type' => 'single',
                'features' => $features,
            ]);

            if (!$response->successful()) {
                throw new RuntimeException("Flask API error: {$response->status()} - {$response->body()}");
            }

            $result = $response->json();

            if (!$result['success']) {
                throw new RuntimeException($result['error'] ?? 'Prediction failed');
            }

            // Denormalize the prediction
            $normalizedOccupancy = $result['prediction']['normalized_occupancy'];
            $actualRooms = $this->denormalize($normalizedOccupancy, 0, $this->getTotalRooms());
            $occupancyRate = $normalizedOccupancy * 100;

            // Get champion model metrics from database
            $champion = \App\Models\ModelVersion::where('model_type', 'single')
                ->where('is_champion', true)
                ->first();

            return [
                'success' => true,
                'model_type' => 'single',
                'model_path' => $result['model_path'] ?? 'champion',
                'model_version' => $champion ? $champion->version : 'v1.0.0',
                'prediction' => [
                    'rooms_sold' => round($actualRooms, 1),
                    'occupancy_rate' => round($occupancyRate, 2),
                    'normalized_value' => $normalizedOccupancy,
                ],
                'confidence' => [
                    'mape' => $champion ? $champion->mape : 0,
                    'r2' => $champion ? $champion->r2_score : 0,
                    'note' => 'Champion model metrics'
                ],
                'metadata' => [
                    'total_rooms' => $this->getTotalRooms(),
                    'flask_api_url' => $this->flaskApiUrl,
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Single-output prediction failed', [
                'error' => $e->getMessage(),
                'input_shape' => count($features) . 'x' . (count($features[0] ?? []))
            ]);
            throw new RuntimeException('Prediction failed: ' . $e->getMessage());
        }
    }

    /**
     * Make prediction using multi-output model (per room type)
     *
     * @param array $features Array of shape [6, 15] - 6 months of 15 features
     * @param array $roomCapacities ['STD' => 32, 'SPR' => 19, 'JS' => 2, 'FMY' => 3]
     * @return array Prediction results for all room types
     * @throws RuntimeException
     */
    public function predictMultiOutput(array $features, array $roomCapacities): array
    {
        // Validate features shape
        if (!$this->validateFeatures($features)) {
            throw new RuntimeException('Invalid features shape. Expected [6, 15]');
        }

        try {
            // Call Flask API
            $response = Http::timeout(30)->withHeaders($this->flaskHeaders())->post("{$this->flaskApiUrl}/api/predict", [
                'model_type' => 'multi',
                'features' => $features,
            ]);

            if (!$response->successful()) {
                throw new RuntimeException("Flask API error: {$response->status()} - {$response->body()}");
            }

            $result = $response->json();

            if (!$result['success']) {
                throw new RuntimeException($result['error'] ?? 'Prediction failed');
            }

            // Get champion model metrics from database first
            $champion = \App\Models\ModelVersion::where('model_type', 'multi')
                ->where('is_champion', true)
                ->first();

            $championMape = $champion ? $champion->mape : 0;
            $championR2 = $champion ? $champion->r2_score : 0;

            // Denormalize predictions for each room type
            $roomPredictions = [];
            foreach ($result['predictions'] as $roomType => $prediction) {
                $capacity = $roomCapacities[$roomType] ?? 0;
                $normalizedValue = $prediction['normalized_occupancy'];
                $actualRooms = $this->denormalize($normalizedValue, 0, $capacity);

                $roomPredictions[$roomType] = [
                    'rooms_sold' => round($actualRooms, 1),
                    'occupancy_rate' => round($normalizedValue * 100, 2),
                    'capacity' => $capacity,
                    'normalized_value' => $normalizedValue,
                    'mape' => $championMape, // Add MAPE for each prediction
                    'r2' => $championR2, // Add R² for each prediction
                ];
            }

            return [
                'success' => true,
                'model_type' => 'multi',
                'model_path' => $result['model_path'] ?? 'champion',
                'model_version' => $champion ? $champion->version : 'v1.0.0',
                'overall_mape' => $championMape, // Overall MAPE for the model
                'predictions' => $roomPredictions,
                'metadata' => [
                    'room_types' => array_keys($roomPredictions),
                    'flask_api_url' => $this->flaskApiUrl,
                    'mape' => $championMape,
                    'r2_score' => $championR2,
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Multi-output prediction failed', [
                'error' => $e->getMessage(),
                'input_shape' => count($features) . 'x' . (count($features[0] ?? []))
            ]);
            throw new RuntimeException('Prediction failed: ' . $e->getMessage());
        }
    }


    /**
     * Denormalize a value from [0, 1] range to actual range
     *
     * @param float $normalizedValue Value in [0, 1]
     * @param float $min Minimum value of actual range
     * @param float $max Maximum value of actual range
     * @return float Denormalized value
     */
    private function denormalize(float $normalizedValue, float $min, float $max): float
    {
        return $normalizedValue * ($max - $min) + $min;
    }

    /**
     * Validate feature array shape
     *
     * @param array $features
     * @return bool
     */
    public function validateFeatures(array $features): bool
    {
        // Should be 6 months
        if (count($features) !== 6) {
            return false;
        }

        // Each month should have 15 features
        foreach ($features as $month) {
            if (!is_array($month) || count($month) !== 15) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get room capacities from database
     *
     * @return array
     */
    public function getRoomCapacities(): array
    {
        $roomTypes = \App\Models\RoomType::where('is_active', true)
            ->pluck('total_rooms', 'code')
            ->toArray();

        return [
            'STD' => $roomTypes['STD'] ?? 32,
            'SPR' => $roomTypes['SPR'] ?? 19,
            'JS'  => $roomTypes['JS']  ?? 2,
            'FMY' => $roomTypes['FMY'] ?? 3,
        ];
    }
}
