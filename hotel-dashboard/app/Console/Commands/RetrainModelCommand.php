<?php

namespace App\Console\Commands;

use App\Models\ModelVersion;
use App\Services\ModelRetrainingService;
use Illuminate\Console\Command;

class RetrainModelCommand extends Command
{
    protected $signature = 'ml:retrain
                            {--type=multi : Model type (single or multi)}
                            {--real : Use REAL training (not simulated)}';

    protected $description = 'Retrain ML model with historical data';

    public function handle()
    {
        $modelType = $this->option('type');
        $useReal = $this->option('real');

        $this->info("🚀 Model Retraining");
        $this->info("==================");
        $this->line("Type: {$modelType}");
        $this->line("Mode: " . ($useReal ? "REAL Training" : "Simulated"));
        $this->newLine();

        // Show current champion
        $champion = ModelVersion::getChampion($modelType);
        $this->info("📊 Current Champion:");
        $this->line("   Version: {$champion->version}");
        $this->line("   MAPE: {$champion->mape}%");
        $this->line("   R²: {$champion->r2_score}");
        $this->newLine();

        // Confirm before real training
        if ($useReal) {
            if (!$this->confirm('⚠️  This will train a REAL model using Flask API. Continue?', true)) {
                $this->warn('Cancelled.');
                return 1;
            }
        }

        // Execute retraining
        $this->info($useReal ? '🔄 Starting REAL model training...' : '🧪 Starting simulated training...');

        $service = new ModelRetrainingService();

        if ($useReal) {
            // REAL training with Flask API
            $result = $service->retrain($modelType);
        } else {
            // Simulated training (for testing)
            $result = $service->simulateRetrain($modelType);
        }

        $this->newLine();

        if ($result['success']) {
            $this->info("✅ Training Completed!");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Version', $result['version']],
                    ['MAPE', $result['mape'] . '%'],
                    ['R² Score', $result['r2_score']],
                    ['Duration', ($result['training_duration'] ?? 0) . 's'],
                    ['Promoted', $result['promoted'] ? '✅ YES' : '❌ NO'],
                ]
            );

            if ($result['improvement']) {
                $this->line("   📈 Improvement: {$result['improvement']}%");
            }

            $this->newLine();

            // Show updated champion
            $newChampion = ModelVersion::getChampion($modelType);
            if ($newChampion->version !== $champion->version) {
                $this->line("<bg=green;fg=black> 🎉 NEW CHAMPION: {$newChampion->version} </>");
            } else {
                $this->line("<bg=yellow;fg=black> 📌 Champion unchanged: {$champion->version} </>");
            }

            // Show all versions
            $this->newLine();
            $this->info("📋 Model Version History:");
            $versions = ModelVersion::where('model_type', $modelType)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            $data = [];
            foreach ($versions as $v) {
                $data[] = [
                    $v->is_champion ? '⭐' : '',
                    $v->version,
                    $v->mape . '%',
                    $v->r2_score,
                    $v->status,
                ];
            }
            $this->table(['', 'Version', 'MAPE', 'R²', 'Status'], $data);

        } else {
            $this->error("❌ Training failed: {$result['error']}");
            return 1;
        }

        return 0;
    }
}
