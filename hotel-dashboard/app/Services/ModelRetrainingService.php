<?php

namespace App\Services;

use App\Models\HistoricalOccupancyData;
use App\Models\ModelVersion;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ModelRetrainingService
{
    private string $flaskApiUrl;
    private string $modelsPath;

    public function __construct()
    {
        $this->flaskApiUrl = config('ml.flask_api_url', 'http://127.0.0.1:5000');
        $this->modelsPath = storage_path('app/models');
        
        // Ensure models directory exists
        if (!is_dir($this->modelsPath)) {
            mkdir($this->modelsPath, 0755, true);
        }
    }

    /**
     * Main retraining method
     */
    public function retrain(string $modelType = 'multi'): array
    {
        Log::info("Starting model retraining", ['type' => $modelType]);

        try {
            // Get current champion for comparison
            $currentChampion = ModelVersion::getChampion($modelType);
            
            // Create new version record
            $newVersion = ModelVersion::create([
                'version' => ModelVersion::getNextVersion($modelType),
                'model_type' => $modelType,
                'model_path' => '', // Will be updated after training
                'status' => 'training',
            ]);

            // Prepare training data
            $trainingData = $this->prepareTrainingData();
            
            if (empty($trainingData)) {
                $newVersion->updateStatus('failed', 'No training data available');
                return [
                    'success' => false,
                    'error' => 'No training data available',
                ];
            }

            $newVersion->trained_on_records = count($trainingData);
            $newVersion->save();

            // Call Flask API for retraining
            $startTime = microtime(true);
            $result = $this->callFlaskRetrain($trainingData, $modelType);
            $trainingDuration = microtime(true) - $startTime;

            if (!$result['success']) {
                $newVersion->status = 'failed';
                $newVersion->error_message = $result['error'] ?? 'Flask API error';
                $newVersion->save();
                
                return $result;
            }

            // Update version with results
            $newVersion->model_path = $result['model_path'] ?? '';
            $newVersion->mape = $result['metrics']['mape'] ?? null;
            $newVersion->r2_score = $result['metrics']['r2_score'] ?? null;
            $newVersion->rmse = $result['metrics']['rmse'] ?? null;
            $newVersion->training_duration_seconds = round($trainingDuration, 2);
            $newVersion->status = 'completed';
            $newVersion->metadata = [
                'training_samples' => count($trainingData),
                'flask_response' => $result['metadata'] ?? [],
            ];
            $newVersion->save();

            // Compare with champion and promote if better
            $promoted = false;
            $promotionReason = '';

            // Check if new model is valid
            if (!$newVersion->isValidModel()) {
                $promotionReason = "Invalid model: R²={$newVersion->r2_score} (must be 0-1), MAPE={$newVersion->mape}%";
                Log::warning("New model not promoted - Invalid metrics", [
                    'version' => $newVersion->version,
                    'r2_score' => $newVersion->r2_score,
                    'mape' => $newVersion->mape,
                ]);
            } elseif ($newVersion->isBetterThan($currentChampion)) {
                $newVersion->promoteToChampion();
                $promoted = true;

                if (!$currentChampion) {
                    $promotionReason = 'First valid model';
                } elseif (!$currentChampion->isValidModel()) {
                    $promotionReason = 'Replaced invalid champion';
                } else {
                    $promotionReason = "Better metrics: MAPE {$currentChampion->mape}% → {$newVersion->mape}%, R² {$currentChampion->r2_score} → {$newVersion->r2_score}";
                }

                // Mark retraining completed and schedule next retraining (6-month cycle)
                $retrainingScheduler = app(RetrainingScheduler::class);
                $retrainingScheduler->markRetrainingCompleted($newVersion);

                Log::info("New champion model promoted", [
                    'version' => $newVersion->version,
                    'mape' => $newVersion->mape,
                    'r2_score' => $newVersion->r2_score,
                    'reason' => $promotionReason,
                    'improvement' => $newVersion->getImprovementOver($currentChampion),
                    'next_retraining_due' => $newVersion->fresh()->next_retraining_due,
                ]);
            } else {
                if ($currentChampion) {
                    $promotionReason = "Not better than champion: MAPE {$newVersion->mape}% vs {$currentChampion->mape}%, R² {$newVersion->r2_score} vs {$currentChampion->r2_score}";
                } else {
                    $promotionReason = "No champion exists but model is invalid";
                }

                Log::info("New model not promoted", [
                    'version' => $newVersion->version,
                    'new_mape' => $newVersion->mape,
                    'new_r2' => $newVersion->r2_score,
                    'champion_mape' => $currentChampion?->mape,
                    'champion_r2' => $currentChampion?->r2_score,
                    'reason' => $promotionReason,
                ]);
            }

            return [
                'success' => true,
                'version' => $newVersion->version,
                'mape' => $newVersion->mape,
                'r2_score' => $newVersion->r2_score,
                'promoted' => $promoted,
                'improvement' => $newVersion->getImprovementOver($currentChampion),
                'training_duration' => round($trainingDuration, 2),
            ];

        } catch (\Exception $e) {
            Log::error("Model retraining failed", [
                'type' => $modelType,
                'error' => $e->getMessage(),
            ]);

            if (isset($newVersion)) {
                $newVersion->status = 'failed';
                $newVersion->error_message = $e->getMessage();
                $newVersion->save();
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Prepare training data from historical occupancy
     */
    private function prepareTrainingData(): array
    {
        // Get aggregated monthly data for the last 24 months
        $startDate = Carbon::now()->subMonths(24)->startOfMonth();
        $endDate = Carbon::now()->subMonth()->endOfMonth();

        $roomTypes = RoomType::where('is_active', true)->pluck('id', 'code');

        $monthlyData = HistoricalOccupancyData::selectRaw("
                strftime('%Y-%m', date) as month,
                room_type_id,
                AVG(occupancy_rate) as avg_occupancy,
                SUM(rooms_occupied) as total_occupied,
                SUM(revenue) as total_revenue,
                COUNT(*) as days_count
            ")
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('month', 'room_type_id')
            ->orderBy('month')
            ->get();

        // Transform to training format
        $trainingData = [];
        foreach ($monthlyData as $record) {
            $roomType = RoomType::find($record->room_type_id);
            
            $trainingData[] = [
                'month' => $record->month,
                'room_type_code' => $roomType?->code ?? 'UNK',
                'room_type_id' => $record->room_type_id,
                'avg_occupancy' => round($record->avg_occupancy, 2),
                'total_occupied' => $record->total_occupied,
                'total_revenue' => $record->total_revenue,
                'days_count' => $record->days_count,
            ];
        }

        return $trainingData;
    }

    /**
     * Call Flask API for model retraining
     */
    private function callFlaskRetrain(array $trainingData, string $modelType): array
    {
        try {
            $response = Http::timeout(300) // 5 minute timeout
                ->post("{$this->flaskApiUrl}/api/retrain", [
                    'training_data' => $trainingData,
                    'model_type' => $modelType,
                    'incremental' => true,
                ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => 'Flask API returned error: ' . $response->status(),
                    'response' => $response->body(),
                ];
            }

            $data = $response->json();
            
            return [
                'success' => $data['success'] ?? false,
                'model_path' => $data['model_path'] ?? null,
                'metrics' => $data['metrics'] ?? [],
                'metadata' => $data['metadata'] ?? [],
            ];

        } catch (\Exception $e) {
            Log::error("Flask API call failed", [
                'error' => $e->getMessage(),
                'url' => "{$this->flaskApiUrl}/api/retrain",
            ]);

            return [
                'success' => false,
                'error' => 'Failed to connect to Flask API: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Simulate retraining (for testing without Flask API)
     */
    public function simulateRetrain(string $modelType = 'multi'): array
    {
        Log::info("Simulating model retraining", ['type' => $modelType]);

        $currentChampion = ModelVersion::getChampion($modelType);
        
        // Create new version with simulated metrics
        $newVersion = ModelVersion::create([
            'version' => ModelVersion::getNextVersion($modelType),
            'model_type' => $modelType,
            'model_path' => storage_path('app/models/simulated_' . $modelType . '_' . time() . '.h5'),
            'mape' => rand(15, 35) + (rand(0, 99) / 100), // Random MAPE between 15-35%
            'r2_score' => rand(70, 95) / 100, // Random R² between 0.70-0.95
            'rmse' => rand(5, 15) / 100,
            'trained_on_records' => HistoricalOccupancyData::count(),
            'training_duration_seconds' => rand(30, 120),
            'status' => 'completed',
            'metadata' => [
                'simulated' => true,
                'timestamp' => now()->toISOString(),
            ],
        ]);

        // Check if better than champion
        $promoted = false;
        if ($newVersion->isBetterThan($currentChampion)) {
            $newVersion->promoteToChampion();
            $promoted = true;

            // Mark retraining completed and schedule next retraining (6-month cycle)
            $retrainingScheduler = app(RetrainingScheduler::class);
            $retrainingScheduler->markRetrainingCompleted($newVersion);
        }

        return [
            'success' => true,
            'version' => $newVersion->version,
            'mape' => $newVersion->mape,
            'r2_score' => $newVersion->r2_score,
            'promoted' => $promoted,
            'improvement' => $newVersion->getImprovementOver($currentChampion),
            'simulated' => true,
            'next_retraining_due' => $promoted ? $newVersion->fresh()->next_retraining_due : null,
        ];
    }

    /**
     * Get model version history
     */
    public function getVersionHistory(string $modelType = null, int $limit = 10): array
    {
        $query = ModelVersion::orderBy('created_at', 'desc');
        
        if ($modelType) {
            $query->where('model_type', $modelType);
        }
        
        return $query->limit($limit)->get()->toArray();
    }

    /**
     * Get champion models summary
     */
    public function getChampionsSummary(): array
    {
        $single = ModelVersion::getChampion('single');
        $multi = ModelVersion::getChampion('multi');

        return [
            'single' => $single ? [
                'version' => $single->version,
                'mape' => (float) $single->mape,
                'r2_score' => (float) $single->r2_score,
                'created_at' => $single->created_at->toDateTimeString(),
            ] : null,
            'multi' => $multi ? [
                'version' => $multi->version,
                'mape' => (float) $multi->mape,
                'r2_score' => (float) $multi->r2_score,
                'created_at' => $multi->created_at->toDateTimeString(),
            ] : null,
        ];
    }
}
