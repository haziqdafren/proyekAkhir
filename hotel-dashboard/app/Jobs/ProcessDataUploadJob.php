<?php

namespace App\Jobs;

use App\Models\HistoricalOccupancyData;
use App\Models\RoomType;
use App\Models\TrainingUpload;
use App\Services\ExcelParserService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDataUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes

    private TrainingUpload $upload;

    /**
     * Create a new job instance.
     */
    public function __construct(TrainingUpload $upload)
    {
        $this->upload = $upload;
    }

    /**
     * Execute the job.
     */
    public function handle(ExcelParserService $parserService): void
    {
        Log::info("Processing data upload", ['id' => $this->upload->id]);

        try {
            // Update status to parsing
            $this->upload->updateStatus('parsing');

            // Get actual file path using Storage facade
            $filePath = \Illuminate\Support\Facades\Storage::path($this->upload->file_path);

            if (!file_exists($filePath)) {
                $this->upload->updateStatus('failed', "File not found: {$filePath}");
                Log::error("File not found", ['path' => $filePath]);
                return;
            }

            // Parse Excel file
            $result = $parserService->parse($filePath, $this->upload);

            if (!$result['success']) {
                $this->upload->updateStatus('failed', $result['error'] ?? 'Parsing failed');
                return;
            }

            // Validate parsed data
            $validation = $parserService->validateParsedData($result['daily_data']);
            if (!$validation['valid']) {
                $this->upload->addLog("Validation errors: " . implode('; ', $validation['errors']), 'error');
            }
            if (!empty($validation['warnings'])) {
                foreach ($validation['warnings'] as $warning) {
                    $this->upload->addLog("Warning: {$warning}", 'warning');
                }
            }

            // Update status to inserting
            $this->upload->updateStatus('inserting');

            // Insert data into historical_occupancy_data
            $inserted = $this->insertHistoricalData($result['daily_data']);
            
            $this->upload->records_inserted = $inserted;
            $this->upload->save();
            $this->upload->addLog("Inserted {$inserted} records to database");

            // Dispatch retraining job
            $this->upload->updateStatus('retraining');
            $this->upload->addLog("Dispatching model retraining job");
            
            RetrainModelJob::dispatch($this->upload);

        } catch (\Exception $e) {
            Log::error("Data upload processing failed", [
                'id' => $this->upload->id,
                'error' => $e->getMessage(),
            ]);

            $this->upload->updateStatus('failed', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Insert parsed data into historical_occupancy_data table
     */
    private function insertHistoricalData(array $dailyData): int
    {
        $roomTypes = RoomType::pluck('id', 'code');
        $inserted = 0;

        foreach ($dailyData as $day) {
            $date = Carbon::parse($day['date']);

            foreach ($day['room_breakdown'] as $typeCode => $breakdown) {
                $roomTypeId = $roomTypes[$typeCode] ?? null;
                if (!$roomTypeId) {
                    $this->upload->addLog("Unknown room type: {$typeCode}", 'warning');
                    continue;
                }

                // Check if record already exists
                $existing = HistoricalOccupancyData::where('date', $date->toDateString())
                    ->where('room_type_id', $roomTypeId)
                    ->first();

                if ($existing) {
                    // Update existing record
                    $existing->update([
                        'occupancy_rate' => $breakdown['occupancy_rate'],
                        'rooms_available' => $breakdown['rooms_available'],
                        'rooms_occupied' => $breakdown['rooms_occupied'],
                        'revenue' => $breakdown['revenue'],
                    ]);
                } else {
                    // Create new record
                    HistoricalOccupancyData::create([
                        'date' => $date->toDateString(),
                        'room_type_id' => $roomTypeId,
                        'occupancy_rate' => $breakdown['occupancy_rate'],
                        'rooms_available' => $breakdown['rooms_available'],
                        'rooms_occupied' => $breakdown['rooms_occupied'],
                        'revenue' => $breakdown['revenue'],
                    ]);
                    $inserted++;
                }
            }
        }

        return $inserted;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessDataUploadJob failed permanently", [
            'id' => $this->upload->id,
            'error' => $exception->getMessage(),
        ]);

        $this->upload->updateStatus('failed', 'Job failed after all retries: ' . $exception->getMessage());
    }
}
