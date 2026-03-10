<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDataUploadJob;
use App\Jobs\RetrainModelJob;
use App\Models\ModelVersion;
use App\Models\TrainingUpload;
use App\Services\ModelRetrainingService;
use App\Services\RetrainingScheduler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DataUploadController extends Controller
{
    private ModelRetrainingService $retrainingService;

    public function __construct(ModelRetrainingService $retrainingService)
    {
        $this->retrainingService = $retrainingService;
    }

    /**
     * Display data upload page
     */
    public function index()
    {
        // Get recent uploads
        $uploads = TrainingUpload::orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($upload) {
                return [
                    'id' => $upload->id,
                    'file_name' => $upload->original_name,
                    'period_label' => $upload->period_label,
                    'status' => $upload->processing_status,
                    'status_label' => $upload->status_label,
                    'status_color' => $upload->status_color,
                    'records_parsed' => $upload->records_parsed,
                    'records_inserted' => $upload->records_inserted,
                    'error_message' => $upload->error_message,
                    'created_at' => $upload->created_at->format('d M Y H:i'),
                    'processed_at' => $upload->processed_at?->format('d M Y H:i'),
                ];
            });

        // Get model versions
        $modelVersions = ModelVersion::orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($version) {
                return [
                    'id' => $version->id,
                    'version' => $version->version,
                    'model_type' => $version->model_type,
                    'is_champion' => (bool) $version->is_champion,
                    'mape' => $version->mape ? (float) $version->mape : null,
                    'r2_score' => $version->r2_score ? (float) $version->r2_score : null,
                    'status' => $version->status,
                    'trained_on_records' => (int) $version->trained_on_records,
                    'training_duration' => $version->training_duration_seconds ? (float) $version->training_duration_seconds : null,
                    'created_at' => $version->created_at->format('d M Y H:i'),
                ];
            });

        // Get current champions
        $champions = $this->retrainingService->getChampionsSummary();

        // Get retraining status (6-month cycle tracking)
        $retrainingScheduler = app(RetrainingScheduler::class);
        $retrainingStatus = $retrainingScheduler->getAllModelsStatus();

        return Inertia::render('DataUpload/Index', [
            'uploads' => $uploads,
            'modelVersions' => $modelVersions,
            'champions' => $champions,
            'retrainingStatus' => $retrainingStatus,
        ]);
    }

    /**
     * Validate and preview file before uploading
     */
    public function validateFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xls,xlsx|max:10240',
        ]);

        try {
            $file = $request->file('file');

            // Quick parse to get file info (don't save yet)
            $parserService = new \App\Services\ExcelParserService();
            $tempPath = $file->getRealPath();

            // Create temporary upload record for parsing (save to DB temporarily)
            $tempUpload = TrainingUpload::create([
                'file_name' => 'temp_validation_' . time(),
                'file_path' => 'temp',
                'original_name' => $file->getClientOriginalName(),
                'processing_status' => 'pending',
            ]);

            try {
                $result = $parserService->parse($tempPath, $tempUpload);

                if (!$result['success']) {
                    $tempUpload->delete();
                    return response()->json([
                        'success' => false,
                        'message' => 'File tidak valid: ' . ($result['error'] ?? 'Format file salah'),
                    ], 400);
                }

                // Calculate summary
                // Collect all unique room types from all days (not just first day)
                $allRoomTypes = [];
                foreach ($result['daily_data'] as $dayData) {
                    if (isset($dayData['room_breakdown'])) {
                        foreach (array_keys($dayData['room_breakdown']) as $roomType) {
                            if (!in_array($roomType, $allRoomTypes)) {
                                $allRoomTypes[] = $roomType;
                            }
                        }
                    }
                }

                $summary = [
                    'total_days' => count($result['daily_data']),
                    'date_range' => [
                        'start' => $result['daily_data'][0]['date'] ?? null,
                        'end' => $result['daily_data'][count($result['daily_data']) - 1]['date'] ?? null,
                    ],
                    'total_records' => count($result['daily_data']) * count($allRoomTypes),
                    'room_types' => $allRoomTypes,
                ];

                // Delete temp upload record
                $tempUpload->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'File valid dan siap diproses',
                    'summary' => $summary,
                    'file_name' => $file->getClientOriginalName(),
                ]);
            } catch (\Exception $e) {
                $tempUpload->delete();
                throw $e;
            }

        } catch (\Exception $e) {
            \Log::error('Validation error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memvalidasi file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle file upload (after validation)
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xls,xlsx|max:10240', // Max 10MB
        ]);

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $fileName = time() . '_' . $originalName;

            // Store file
            $path = $file->storeAs('uploads/training', $fileName);

            // Create upload record
            $upload = TrainingUpload::create([
                'file_name' => $fileName,
                'file_path' => $path,
                'original_name' => $originalName,
                'processing_status' => 'pending',
            ]);

            // Dispatch processing job
            ProcessDataUploadJob::dispatch($upload);

            return response()->json([
                'success' => true,
                'message' => 'File berhasil diupload dan sedang diproses',
                'upload_id' => $upload->id,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupload file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get upload status
     */
    public function status(TrainingUpload $upload)
    {
        return response()->json([
            'id' => $upload->id,
            'status' => $upload->processing_status,
            'status_label' => $upload->status_label,
            'status_color' => $upload->status_color,
            'records_parsed' => $upload->records_parsed,
            'records_inserted' => $upload->records_inserted,
            'error_message' => $upload->error_message,
            'parsing_log' => $upload->parsing_log,
            'processed_at' => $upload->processed_at?->format('d M Y H:i'),
        ]);
    }

    /**
     * Manual trigger for model retraining
     */
    public function retrain(Request $request)
    {
        $request->validate([
            'model_type' => 'required|in:single,multi,both',
            'simulate' => 'boolean',
        ]);

        $modelType = $request->input('model_type');
        $simulate = $request->boolean('simulate', false);

        try {
            if ($modelType === 'both') {
                RetrainModelJob::dispatch(null, 'multi', $simulate);
                RetrainModelJob::dispatch(null, 'single', $simulate);
                $message = 'Proses retraining untuk kedua model telah dimulai';
            } else {
                RetrainModelJob::dispatch(null, $modelType, $simulate);
                $message = "Proses retraining model {$modelType} telah dimulai";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memulai retraining: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get model version history
     */
    public function modelVersions(Request $request)
    {
        $modelType = $request->input('model_type');
        
        $query = ModelVersion::orderBy('created_at', 'desc');
        
        if ($modelType) {
            $query->where('model_type', $modelType);
        }
        
        $versions = $query->paginate(20);

        return response()->json($versions);
    }

    /**
     * Retry failed upload
     */
    public function retry(TrainingUpload $upload)
    {
        if (!$upload->canRetry()) {
            return response()->json([
                'success' => false,
                'message' => 'Upload ini tidak dapat di-retry',
            ], 400);
        }

        // Reset status
        $upload->processing_status = 'pending';
        $upload->error_message = null;
        $upload->error_details = null;
        $upload->records_parsed = 0;
        $upload->records_inserted = 0;
        $upload->processed_at = null;
        $upload->save();

        // Dispatch job again
        ProcessDataUploadJob::dispatch($upload);

        return response()->json([
            'success' => true,
            'message' => 'Upload akan diproses ulang',
        ]);
    }

    /**
     * Delete upload record
     */
    public function destroy(TrainingUpload $upload)
    {
        try {
            // Delete file if exists
            if (Storage::exists($upload->file_path)) {
                Storage::delete($upload->file_path);
            }

            $upload->delete();

            return response()->json([
                'success' => true,
                'message' => 'Upload berhasil dihapus',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download Excel template
     */
    public function downloadTemplate()
    {
        $templatePath = storage_path('app/public/templates/Template_Upload_Data_Hotel.xls');

        if (!file_exists($templatePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Template file not found',
            ], 404);
        }

        return response()->download(
            $templatePath,
            'Template_Upload_Data_Hotel.xls',
            [
                'Content-Type' => 'application/vnd.ms-excel',
                'Content-Disposition' => 'attachment; filename="Template_Upload_Data_Hotel.xls"',
            ]
        );
    }
}
