<?php

namespace App\Console\Commands;

use App\Models\TrainingUpload;
use App\Services\ExcelParserService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestExcelParsing extends Command
{
    protected $signature = 'ml:test-parse {file : Path to Excel file}';
    protected $description = 'Test Excel parsing with a real hotel file';

    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("❌ File not found: {$filePath}");
            return 1;
        }

        $this->info("🧪 Testing Excel Parser");
        $this->info("=====================");
        $this->line("File: " . basename($filePath));
        $this->line("Size: " . round(filesize($filePath) / 1024, 2) . " KB");
        $this->newLine();

        // Create temp upload record
        $upload = TrainingUpload::create([
            'file_name' => 'test_' . time() . '.xls',
            'file_path' => 'test/test.xls',
            'original_name' => basename($filePath),
            'processing_status' => 'pending',
        ]);

        $parser = new ExcelParserService();

        try {
            $this->info("📥 Parsing Excel file...");
            $result = $parser->parse($filePath, $upload);

            if ($result['success']) {
                $this->info("✅ Parsing SUCCESS!");
                $this->newLine();

                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Days parsed', count($result['daily_data'])],
                        ['Records parsed', $result['total_parsed'] ?? 0],
                        ['Records failed', $result['total_failed'] ?? 0],
                    ]
                );

                // Show first day sample
                if (!empty($result['daily_data'])) {
                    $firstDay = $result['daily_data'][0];
                    $this->newLine();
                    $this->info("📅 Sample Day: {$firstDay['date']}");
                    $this->line("   Total Occupied: {$firstDay['total_occupied']} rooms");
                    $this->line("   Total Revenue: Rp " . number_format($firstDay['total_revenue'], 0, ',', '.'));
                    $this->newLine();

                    $this->info("🏨 Room Breakdown:");
                    $data = [];
                    foreach ($firstDay['room_breakdown'] as $type => $breakdown) {
                        $data[] = [
                            $type,
                            $breakdown['rooms_occupied'] . '/' . $breakdown['rooms_available'],
                            round($breakdown['occupancy_rate'], 1) . '%',
                            'Rp ' . number_format($breakdown['revenue'], 0, ',', '.'),
                        ];
                    }
                    $this->table(['Type', 'Occupied/Available', 'Occupancy', 'Revenue'], $data);
                }

                // Show parsing logs
                $logs = $upload->fresh()->parsing_log ?? [];
                if (!empty($logs)) {
                    $this->newLine();
                    $this->info("📋 Parsing Logs:");
                    foreach (array_slice($logs, 0, 10) as $log) {
                        $level = $log['level'] ?? 'info';
                        $icon = $level === 'error' ? '❌' : ($level === 'warning' ? '⚠️' : '✅');
                        $this->line("   {$icon} [{$level}] {$log['message']}");
                    }
                    if (count($logs) > 10) {
                        $this->line("   ... and " . (count($logs) - 10) . " more");
                    }
                }

            } else {
                $this->error("❌ Parsing FAILED!");
                $this->line("Error: " . ($result['error'] ?? 'Unknown error'));

                $errors = $result['errors'] ?? [];
                if (!empty($errors)) {
                    $this->newLine();
                    $this->warn("Errors found:");
                    foreach (array_slice($errors, 0, 5) as $error) {
                        $this->line("   • {$error}");
                    }
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ EXCEPTION: {$e->getMessage()}");
            $this->newLine();
            $this->line($e->getTraceAsString());
            return 1;
        } finally {
            // Cleanup test record
            $upload->delete();
        }

        return 0;
    }
}
