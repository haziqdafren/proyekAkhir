<?php

namespace Database\Seeders;

use App\Models\ModelVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class CopyChampionModels extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Initializing model storage and records...');

        // Ensure directories exist
        $storagePath = storage_path('app/models');
        if (!File::exists($storagePath)) {
            File::makeDirectory($storagePath, 0755, true);
        }
        if (!File::exists($storagePath . '/single')) {
            File::makeDirectory($storagePath . '/single', 0755, true);
        }
        if (!File::exists($storagePath . '/multi')) {
            File::makeDirectory($storagePath . '/multi', 0755, true);
        }

        // Source paths (original models)
        $singleSource = base_path('../proyekAkhir/single_output/lstm_single_final.keras');
        $multiSource = base_path('../proyekAkhir/multi_output/lstm_multi_final.keras');

        // 1. Setup Single Output Model
        if (File::exists($singleSource)) {
            $version = 'v1.0.0';
            $destPath = $storagePath . "/single/{$version}.keras";
            $championPath = $storagePath . "/single/champion.keras";

            // Copy files
            File::copy($singleSource, $destPath);
            File::copy($singleSource, $championPath);

            // Create record
            ModelVersion::create([
                'version' => $version,
                'model_type' => 'single',
                'model_path' => $destPath,
                'is_champion' => true,
                'is_active' => true,
                'mape' => 17.18, // Based on config.json
                'r2_score' => 0.4208,
                'status' => 'completed',
                'metadata' => [
                    'description' => 'Baseline Academic Research Model',
                    'original_source' => $singleSource
                ]
            ]);
            
            $this->command->info("Single Output Model initialized: {$version}");
        } else {
            $this->command->error("Single output source not found: {$singleSource}");
        }

        // 2. Setup Multi Output Model
        if (File::exists($multiSource)) {
            $version = 'v1.0.0';
            $destPath = $storagePath . "/multi/{$version}.keras";
            $championPath = $storagePath . "/multi/champion.keras";

            // Copy files
            File::copy($multiSource, $destPath);
            File::copy($multiSource, $championPath);

            // Create record
            ModelVersion::create([
                'version' => $version,
                'model_type' => 'multi',
                'model_path' => $destPath,
                'is_champion' => true,
                'is_active' => true,
                'mape' => 32.39, // Based on config.json
                'r2_score' => 0.2140,
                'status' => 'completed',
                'metadata' => [
                    'description' => 'Baseline Academic Research Model',
                    'original_source' => $multiSource
                ]
            ]);

            $this->command->info("Multi Output Model initialized: {$version}");
        } else {
            $this->command->error("Multi output source not found: {$multiSource}");
        }
    }
}
