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

    public int $tries = 1;       // #11: retries on parse failure waste resources; retry is manual via UI
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

            // Mark as completed — user will manually trigger retraining from the UI
            $this->upload->updateStatus('completed');
            $this->upload->addLog("Data berhasil disimpan. Tekan 'Latih Ulang Manual' untuk memperbarui model prediksi.");

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
     * Insert parsed data into historical_occupancy_data table.
     * Uses upsert() to avoid N+1 SELECT-before-write pattern (#1).
     */
    private function insertHistoricalData(array $dailyData): int
    {
        $roomTypes = RoomType::pluck('id', 'code');
        $rows = [];

        foreach ($dailyData as $day) {
            $date = Carbon::parse($day['date'])->toDateString();

            foreach ($day['room_breakdown'] as $typeCode => $breakdown) {
                $roomTypeId = $roomTypes[$typeCode] ?? null;
                if (!$roomTypeId) {
                    $this->upload->addLog("Tipe kamar tidak dikenal: {$typeCode}", 'warning');
                    continue;
                }

                $rows[] = [
                    'date'             => $date,
                    'room_type_id'     => $roomTypeId,
                    'occupancy_rate'   => $breakdown['occupancy_rate'],
                    'rooms_available'  => $breakdown['rooms_available'],
                    'rooms_occupied'   => $breakdown['rooms_occupied'],
                    'revenue'          => $breakdown['revenue'],
                ];
            }
        }

        if (empty($rows)) {
            return 0;
        }

        // Single query: insert new rows, update existing on (date, room_type_id) conflict
        HistoricalOccupancyData::upsert(
            $rows,
            ['date', 'room_type_id'],
            ['occupancy_rate', 'rooms_available', 'rooms_occupied', 'revenue']
        );

        return count($rows);
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
