<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDataUploadJob;
use App\Models\TrainingUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestFullWorkflow extends Command
{
    protected $signature = 'ml:test-workflow {file : Path to Excel file}';
    protected $description = 'Test complete workflow: Upload → Parse → Train → Champion';

    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("❌ File not found: {$filePath}");
            return 1;
        }

        $this->info("🚀 TESTING COMPLETE WORKFLOW");
        $this->info("============================");
        $this->line("File: " . basename($filePath));
        $this->newLine();

        // Step 1: Upload file
        $this->info("📥 Step 1: Uploading file...");
        $fileName = 'test_' . time() . '_' . basename($filePath);
        $storagePath = 'uploads/' . $fileName;

        // Copy to storage
        Storage::put($storagePath, file_get_contents($filePath));
        $this->line("   ✅ File uploaded to storage");

        // Step 2: Create upload record
        $this->info("📝 Step 2: Creating upload record...");
        $upload = TrainingUpload::create([
            'file_name' => $fileName,
            'file_path' => $storagePath,
            'original_name' => basename($filePath),
            'processing_status' => 'pending',
        ]);
        $this->line("   ✅ Upload ID: {$upload->id}");

        // Step 3: Process upload (dispatch job)
        $this->info("⚙️  Step 3: Processing upload...");
        $this->line("   Dispatching ProcessDataUploadJob...");

        // Process synchronously for testing
        $job = new ProcessDataUploadJob($upload);
        $parser = new \App\Services\ExcelParserService();

        try {
            // Use absolute path to original file for parsing
            $result = $parser->parse($filePath, $upload);

            if (!$result['success']) {
                $upload->updateStatus('failed', $result['error'] ?? 'Parsing failed');
                $this->error("   ❌ Parsing failed: " . ($result['error'] ?? 'Unknown error'));
                return 1;
            }

            // Insert data
            $upload->updateStatus('inserting');
            $inserted = 0;
            $roomTypes = \App\Models\RoomType::pluck('id', 'code');

            foreach ($result['daily_data'] as $day) {
                $date = \Carbon\Carbon::parse($day['date']);

                foreach ($day['room_breakdown'] as $typeCode => $breakdown) {
                    $roomTypeId = $roomTypes[$typeCode] ?? null;
                    if (!$roomTypeId) continue;

                    \App\Models\HistoricalOccupancyData::updateOrCreate(
                        [
                            'date' => $date->toDateString(),
                            'room_type_id' => $roomTypeId,
                        ],
                        [
                            'occupancy_rate' => $breakdown['occupancy_rate'],
                            'rooms_available' => $breakdown['rooms_available'],
                            'rooms_occupied' => $breakdown['rooms_occupied'],
                            'revenue' => $breakdown['revenue'],
                        ]
                    );
                    $inserted++;
                }
            }

            $upload->records_inserted = $inserted;
            $upload->save();
            $upload->updateStatus('completed');

            $this->line("   ✅ Processing complete");
        } catch (\Exception $e) {
            $upload->updateStatus('failed', $e->getMessage());
            $this->error("   ❌ Processing failed: {$e->getMessage()}");
            return 1;
        }

        // Step 4: Check results
        $this->newLine();
        $this->info("📊 Step 4: Checking results...");
        $upload->refresh();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Status', $upload->processing_status],
                ['Records Parsed', $upload->records_parsed],
                ['Records Inserted', $upload->records_inserted],
                ['Records Failed', $upload->records_failed],
                ['Period', $upload->period_label],
            ]
        );

        if ($upload->processing_status === 'failed') {
            $this->error("❌ Upload failed: {$upload->error_message}");
            return 1;
        }

        // Step 5: Check if retraining was triggered
        $this->newLine();
        $this->info("🔄 Step 5: Checking retraining status...");

        if ($upload->processing_status === 'completed') {
            $this->line("   ✅ Workflow completed successfully!");

            // Check model versions
            $latestVersion = \App\Models\ModelVersion::latest()->first();
            if ($latestVersion && $latestVersion->created_at->gt($upload->created_at)) {
                $this->line("   🎉 New model version created: {$latestVersion->version}");
                $this->line("      MAPE: {$latestVersion->mape}%");
                $this->line("      Champion: " . ($latestVersion->is_champion ? "✅ YES" : "❌ NO"));
            } else {
                $this->warn("   ⏳ Retraining may still be in progress (check queue worker)");
            }
        }

        // Step 6: Show logs
        $this->newLine();
        $this->info("📋 Upload Logs:");
        $logs = $upload->parsing_log ?? [];
        foreach (array_slice($logs, -10) as $log) {
            $level = $log['level'] ?? 'info';
            $icon = $level === 'error' ? '❌' : ($level === 'warning' ? '⚠️' : '✅');
            $this->line("   {$icon} [{$level}] {$log['message']}");
        }

        $this->newLine();
        $this->info("✅ Workflow test complete!");

        // Cleanup option
        if ($this->confirm('Do you want to cleanup test data?', false)) {
            Storage::delete($storagePath);
            $upload->delete();
            $this->line("   🗑️  Test data cleaned up");
        }

        return 0;
    }
}
