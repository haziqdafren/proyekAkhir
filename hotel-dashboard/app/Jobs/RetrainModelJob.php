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

    public int $tries   = 2;
    public int $timeout = 600; // 10 minutes

    private ?TrainingUpload $upload;
    private string $modelType;
    private bool $simulate;

    public function __construct(
        ?TrainingUpload $upload    = null,
        string          $modelType = 'multi',
        bool            $simulate  = false
    ) {
        $this->upload    = $upload;
        $this->modelType = $modelType;
        $this->simulate  = $simulate;
    }

    public function handle(ModelRetrainingService $retrainingService): void
    {
        Log::info('Starting model retraining job', [
            'upload_id'  => $this->upload?->id,
            'model_type' => $this->modelType,
            'simulate'   => $this->simulate,
        ]);

        try {
            $result = $this->simulate
                ? $retrainingService->simulateRetrain($this->modelType)
                : $retrainingService->retrain($this->modelType);

            if ($result['success']) {
                $promotedLabel = $result['promoted'] ? 'PROMOTED' : 'Not promoted (not better)';
                $this->upload?->addLog(
                    "Model retraining completed: {$result['version']}, " .
                    "MAPE: {$result['mape']}%, " .
                    "R²: " . ($result['r2_score'] ?? 'N/A') . ", " .
                    $promotedLabel
                );
            } else {
                $this->upload?->addLog(
                    'Model retraining failed: ' . ($result['error'] ?? 'Unknown error'),
                    'error'
                );
            }

            // Upload status is managed separately — retrain is now always standalone

        } catch (\Exception $e) {
            Log::error('RetrainModelJob failed', [
                'upload_id'  => $this->upload?->id,
                'model_type' => $this->modelType,
                'error'      => $e->getMessage(),
            ]);

            $this->upload?->addLog('Retraining error: ' . $e->getMessage(), 'error');
            $this->upload?->updateStatus('failed', 'Retraining failed: ' . $e->getMessage());

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RetrainModelJob failed permanently', [
            'upload_id'  => $this->upload?->id,
            'model_type' => $this->modelType,
            'error'      => $exception->getMessage(),
        ]);

        $this->upload?->updateStatus('failed', 'Retraining job failed: ' . $exception->getMessage());
    }
}
