<?php

namespace App\Jobs;

use App\Models\TrainingUpload;
use App\Services\ModelRetrainingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetrainModelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600; // 10 minutes for retraining

    private ?TrainingUpload $upload;
    private string $modelType;
    private bool $simulate;

    /**
     * Create a new job instance.
     */
    public function __construct(?TrainingUpload $upload = null, string $modelType = 'multi', bool $simulate = false)
    {
        $this->upload = $upload;
        $this->modelType = $modelType;
        $this->simulate = $simulate;
    }

    /**
     * Execute the job.
     */
    public function handle(ModelRetrainingService $retrainingService): void
    {
        Log::info("Starting model retraining", [
            'upload_id' => $this->upload?->id,
            'model_type' => $this->modelType,
            'simulate' => $this->simulate,
        ]);

        try {
            // Perform retraining
            if ($this->simulate) {
                $result = $retrainingService->simulateRetrain($this->modelType);
            } else {
                $result = $retrainingService->retrain($this->modelType);
            }

            // Log results
            if ($result['success']) {
                $this->upload?->addLog(
                    "Model retraining completed: {$result['version']}, " .
                    "MAPE: {$result['mape']}%, " .
                    "R²: " . ($result['r2_score'] ?? 'N/A') . ", " .
                    ($result['promoted'] ? "PROMOTED as new champion!" : "Not promoted (not better)")
                );

                // Also train single output model if this was multi
                if ($this->modelType === 'multi' && !$this->simulate) {
                    Log::info("Also retraining single output model");
                    $singleResult = $retrainingService->retrain('single');
                    
                    $this->upload?->addLog(
                        "Single model retraining: {$singleResult['version']}, " .
                        "MAPE: " . ($singleResult['mape'] ?? 'N/A') . "%"
                    );
                }
            } else {
                $this->upload?->addLog(
                    "Model retraining failed: " . ($result['error'] ?? 'Unknown error'),
                    'error'
                );
            }

            // Mark upload as completed
            $this->upload?->updateStatus('completed');

        } catch (\Exception $e) {
            Log::error("Model retraining job failed", [
                'upload_id' => $this->upload?->id,
                'error' => $e->getMessage(),
            ]);

            $this->upload?->addLog("Retraining error: {$e->getMessage()}", 'error');
            $this->upload?->updateStatus('failed', 'Retraining failed: ' . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("RetrainModelJob failed permanently", [
            'upload_id' => $this->upload?->id,
            'error' => $exception->getMessage(),
        ]);

        $this->upload?->updateStatus('failed', 'Retraining job failed: ' . $exception->getMessage());
    }
}
