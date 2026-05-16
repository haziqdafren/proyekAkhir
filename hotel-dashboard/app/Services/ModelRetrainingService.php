<?php

namespace App\Services;

use App\Models\HistoricalOccupancyData;
use App\Models\ModelVersion;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ModelRetrainingService
{
    private string $flaskApiUrl;
    private string $flaskApiKey;
    private string $modelsPath;

    public function __construct()
    {
        $this->flaskApiUrl = config('ml.flask_api_url', 'http://127.0.0.1:5000');
        $this->flaskApiKey = config('ml.flask_api_key', '');
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

            // Compare with champion and promote if better.
            // Wrapped in a transaction with row lock to prevent race conditions
            // when single and multi retraining jobs run concurrently (#3).
            $promoted = false;
            $promotionReason = '';

            if (!$newVersion->isTrainable()) {
                $promotionReason = "Model not trainable: R²={$newVersion->r2_score}, MAPE={$newVersion->mape}%";
                Log::warning("New model not promoted - unusable metrics", [
                    'version' => $newVersion->version,
                    'r2_score' => $newVersion->r2_score,
                    'mape' => $newVersion->mape,
                ]);
            } else {
                $promotionResult = DB::transaction(function () use ($newVersion, $modelType) {
                    // Lock the current champion row so two concurrent jobs can't both promote
                    $lockedChampion = ModelVersion::where('model_type', $modelType)
                        ->where('is_champion', true)
                        ->lockForUpdate()
                        ->first();

                    if (!$newVersion->isBetterThan($lockedChampion)) {
                        return ['promoted' => false, 'champion' => $lockedChampion];
                    }

                    $newVersion->promoteToChampion();
                    return ['promoted' => true, 'champion' => $lockedChampion];
                });

                $promoted = $promotionResult['promoted'];
                $lockedChampion = $promotionResult['champion'];

                if ($promoted) {
                    if (!$lockedChampion) {
                        $promotionReason = 'First valid model';
                    } elseif (!$lockedChampion->isValidModel()) {
                        $promotionReason = 'Replaced invalid champion';
                    } else {
                        $promotionReason = "Better metrics: MAPE {$lockedChampion->mape}% → {$newVersion->mape}%, R² {$lockedChampion->r2_score} → {$newVersion->r2_score}";
                    }

                    $retrainingScheduler = app(RetrainingScheduler::class);
                    $retrainingScheduler->markRetrainingCompleted($newVersion);

                    Log::info("New champion model promoted", [
                        'version'             => $newVersion->version,
                        'mape'                => $newVersion->mape,
                        'r2_score'            => $newVersion->r2_score,
                        'reason'              => $promotionReason,
                        'improvement'         => $newVersion->getImprovementOver($lockedChampion),
                        'next_retraining_due' => $newVersion->fresh()->next_retraining_due,
                    ]);
                } else {
                    $promotionReason = $lockedChampion
                        ? "Not better than champion: MAPE {$newVersion->mape}% vs {$lockedChampion->mape}%, R² {$newVersion->r2_score} vs {$lockedChampion->r2_score}"
                        : "No champion exists but new model metrics are unusable";

                    Log::info("New model not promoted", [
                        'version'       => $newVersion->version,
                        'new_mape'      => $newVersion->mape,
                        'new_r2'        => $newVersion->r2_score,
                        'champion_mape' => $lockedChampion?->mape,
                        'champion_r2'   => $lockedChampion?->r2_score,
                        'reason'        => $promotionReason,
                    ]);
                }
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
        // Get earliest available historical data
        $oldestDataDate = HistoricalOccupancyData::min('date');

        if (!$oldestDataDate) {
            return []; // No data available
        }

        // Use ALL available historical data (full dataset from first upload onward).
        // More data = better long-term pattern learning for LSTM.
        // Retraining is done once a year, so the full dataset grows incrementally each cycle.
        $startDate = Carbon::parse($oldestDataDate)->startOfMonth();
        $endDate   = HistoricalOccupancyData::max('date')
            ? Carbon::parse(HistoricalOccupancyData::max('date'))->endOfMonth()
            : Carbon::now()->subMonth()->endOfMonth();

        // Pre-load room type code map to avoid N+1 queries in the loop below
        $roomTypeMap = RoomType::where('is_active', true)->pluck('code', 'id');

        // Fetch raw daily rows and aggregate in PHP (portable across SQLite / MySQL / Postgres)
        $rawData = HistoricalOccupancyData::selectRaw('
                date,
                room_type_id,
                occupancy_rate,
                rooms_occupied,
                revenue
            ')
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        // Group by (year-month, room_type_id) in PHP
        $grouped = [];
        foreach ($rawData as $row) {
            $month  = substr($row->date, 0, 7); // "YYYY-MM"
            $key    = $month . '|' . $row->room_type_id;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'month'        => $month,
                    'room_type_id' => $row->room_type_id,
                    'occ_sum'      => 0,
                    'rooms_sum'    => 0,
                    'revenue_sum'  => 0,
                    'days_count'   => 0,
                ];
            }

            $grouped[$key]['occ_sum']   += $row->occupancy_rate;
            $grouped[$key]['rooms_sum'] += $row->rooms_occupied;
            $grouped[$key]['revenue_sum'] += $row->revenue;
            $grouped[$key]['days_count']++;
        }

        // Transform to training format — no N+1, using pre-loaded map
        $trainingData = [];
        foreach ($grouped as $record) {
            $daysCount = max($record['days_count'], 1);
            $trainingData[] = [
                'month'          => $record['month'],
                'room_type_code' => $roomTypeMap[$record['room_type_id']] ?? 'UNK',
                'room_type_id'   => $record['room_type_id'],
                'avg_occupancy'  => round($record['occ_sum'] / $daysCount, 2),
                'total_occupied' => $record['rooms_sum'],
                'total_revenue'  => $record['revenue_sum'],
                'days_count'     => $record['days_count'],
            ];
        }

        // Sort by month ascending (important for LSTM sequence building)
        usort($trainingData, fn($a, $b) => strcmp($a['month'], $b['month']));

        return $trainingData;
    }

    /**
     * Common headers for Flask API calls — includes API key if configured.
     */
    private function flaskHeaders(): array
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($this->flaskApiKey) {
            $headers['X-API-Key'] = $this->flaskApiKey;
        }
        return $headers;
    }

    /**
     * Call Flask API for model retraining
     */
    private function callFlaskRetrain(array $trainingData, string $modelType): array
    {
        try {
            $response = Http::timeout(300) // 5 minute timeout
                ->withHeaders($this->flaskHeaders())
                ->post("{$this->flaskApiUrl}/api/retrain", [
                    'training_data' => $trainingData,
                    'model_type' => $modelType,
                    'incremental' => true,
                    // Set RETRAIN_TEST_MODE=true in .env to simulate training without modifying real weights
                    'test_mode' => (bool) env('RETRAIN_TEST_MODE', false),
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
            'model_path' => '', // Simulated — no real file on disk
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

            $retrainingScheduler = app(RetrainingScheduler::class);
            $retrainingScheduler->markRetrainingCompleted($newVersion);
        }

        return [
            'success'             => true,
            'version'             => $newVersion->version,
            'mape'                => $newVersion->mape,
            'r2_score'            => $newVersion->r2_score,
            'promoted'            => $promoted,
            'improvement'         => $newVersion->getImprovementOver($currentChampion),
            'simulated'           => true,
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
                'version'    => $single->version,
                'mape'       => round((float) $single->mape, 2),
                'accuracy'   => round(100 - (float) $single->mape, 2),
                'r2_score'   => round((float) $single->r2_score, 4),
                'created_at' => $single->created_at->toDateTimeString(),
            ] : null,
            'multi' => $multi ? [
                'version'    => $multi->version,
                'mape'       => round((float) $multi->mape, 2),
                'accuracy'   => round(100 - (float) $multi->mape, 2),
                'r2_score'   => round((float) $multi->r2_score, 4),
                'created_at' => $multi->created_at->toDateTimeString(),
            ] : null,
        ];
    }
}
