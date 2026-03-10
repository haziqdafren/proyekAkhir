<?php

namespace App\Services;

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
     * Total number of rooms in the hotel
     */
    private const TOTAL_ROOMS = 56;

    /**
     * Normalization range used in training
     */
    private const NORM_MIN = 0;
    private const NORM_MAX = 1;

    public function __construct()
    {
        $this->flaskApiUrl = config('ml.flask_api_url', 'http://127.0.0.1:5000');
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
            $response = Http::timeout(30)->post("{$this->flaskApiUrl}/api/predict", [
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
            $actualRooms = $this->denormalize($normalizedOccupancy, 0, self::TOTAL_ROOMS);
            $occupancyRate = $normalizedOccupancy * 100;

            return [
                'success' => true,
                'model_type' => 'single',
                'model_path' => $result['model_path'] ?? 'champion',
                'prediction' => [
                    'rooms_sold' => round($actualRooms, 1),
                    'occupancy_rate' => round($occupancyRate, 2),
                    'normalized_value' => $normalizedOccupancy,
                ],
                'confidence' => $result['prediction']['confidence'] ?? ['note' => 'Champion model'],
                'metadata' => [
                    'total_rooms' => self::TOTAL_ROOMS,
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
     * @param array $roomCapacities ['STD' => 21, 'SPR' => 18, 'FMY' => 10, 'JS' => 9]
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
            $response = Http::timeout(30)->post("{$this->flaskApiUrl}/api/predict", [
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
                ];
            }

            return [
                'success' => true,
                'model_type' => 'multi',
                'model_path' => $result['model_path'] ?? 'champion',
                'predictions' => $roomPredictions,
                'metadata' => [
                    'room_types' => array_keys($roomPredictions),
                    'flask_api_url' => $this->flaskApiUrl,
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
            'STD' => $roomTypes['STD'] ?? 21,
            'SPR' => $roomTypes['SPR'] ?? 18,
            'FMY' => $roomTypes['FMY'] ?? 10,
            'JS' => $roomTypes['JS'] ?? 9,
        ];
    }
}
