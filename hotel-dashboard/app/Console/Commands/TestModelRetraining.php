<?php

namespace App\Console\Commands;

use App\Models\ModelVersion;
use App\Services\ModelRetrainingService;
use Illuminate\Console\Command;

class TestModelRetraining extends Command
{
    protected $signature = 'ml:test-retrain {--type=multi : Model type (single or multi)}';
    protected $description = 'Test model retraining workflow with champion promotion';

    public function handle()
    {
        $modelType = $this->option('type');

        $this->info("🎯 Testing Model Retraining Workflow");
        $this->info("=====================================");
        $this->newLine();

        // Get current champion
        $champion = ModelVersion::getChampion($modelType);
        $this->info("📊 Current Champion ({$modelType}):");
        $this->line("   Version: {$champion->version}");
        $this->line("   MAPE: {$champion->mape}%");
        $this->line("   R²: {$champion->r2_score}");
        $this->newLine();

        // Run retraining simulation
        $this->info("🔄 Starting Simulated Retraining...");
        $service = new ModelRetrainingService();
        $result = $service->simulateRetrain($modelType);

        if ($result['success']) {
            $this->info("✅ Retraining Completed!");
            $this->line("   New Version: {$result['version']}");
            $this->line("   New MAPE: {$result['mape']}%");
            $this->line("   New R²: {$result['r2_score']}");

            if ($result['promoted']) {
                $this->line("   <bg=green;fg=black> PROMOTED TO CHAMPION </>");
                if ($result['improvement']) {
                    $this->line("   Improvement: {$result['improvement']}%");
                }
            } else {
                $this->line("   <bg=yellow;fg=black> NOT PROMOTED (not better than champion) </>");
            }

            $this->newLine();

            // Show updated champion
            $newChampion = ModelVersion::getChampion($modelType);
            $this->info("🏆 Champion AFTER:");
            $this->line("   Version: {$newChampion->version}");
            $this->line("   MAPE: {$newChampion->mape}%");

            if ($newChampion->version !== $champion->version) {
                $this->line("   <fg=green>🎉 CHAMPION WAS UPDATED!</>");
            } else {
                $this->line("   <fg=yellow>📌 Champion remained the same</>");
            }
        } else {
            $this->error("❌ Retraining failed: {$result['error']}");
            return 1;
        }

        $this->newLine();
        $this->info("📋 All Model Versions ({$modelType}):");
        $versions = ModelVersion::where('model_type', $modelType)
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($versions as $v) {
            $champion = $v->is_champion ? '⭐' : '  ';
            $this->line("   {$champion} {$v->version}: MAPE={$v->mape}% | R²={$v->r2_score} | {$v->status}");
        }

        return 0;
    }
}
