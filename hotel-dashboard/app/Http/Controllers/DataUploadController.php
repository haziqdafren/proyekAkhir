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
    public function index(Request $request)
    {
        // Paginated uploads — 10 per page. Clamp to ≥ 1 (#8).
        $uploadsPage = max(1, (int) $request->get('uploads_page', 1));
        $uploadsPerPage = 10;
        $uploadsQuery = TrainingUpload::orderBy('created_at', 'desc');
        $uploadsPaginated = $uploadsQuery->paginate($uploadsPerPage, ['*'], 'uploads_page', $uploadsPage);

        $uploads = [
            'data' => $uploadsPaginated->getCollection()->map(function ($upload) {
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
            })->values(),
            'current_page' => $uploadsPaginated->currentPage(),
            'last_page' => $uploadsPaginated->lastPage(),
            'total' => $uploadsPaginated->total(),
            'per_page' => $uploadsPerPage,
        ];

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

            // Use parseForValidation() — no DB record is created or deleted.
            // Pass original name so the parser can extract authoritative month/year from it
            // (the real path is a temp path like /tmp/phpXXXXX which has no month info).
            $parserService = new \App\Services\ExcelParserService();
            $result = $parserService->parseForValidation($file->getRealPath(), $file->getClientOriginalName());

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak valid: ' . ($result['error'] ?? 'Format file salah'),
                ], 400);
            }

            // Collect all unique room types across all parsed days
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

            $dates = array_column($result['daily_data'], 'date');
            sort($dates);
            $uploadStart = $dates[0] ?? null;
            $uploadEnd   = $dates[count($dates) - 1] ?? null;

            // Determine expected next period from DB
            $dbMaxDate        = \App\Models\HistoricalOccupancyData::max('date');
            $expectedStart    = $dbMaxDate
                ? \Carbon\Carbon::parse($dbMaxDate)->addDay()->startOfMonth()->toDateString()
                : null;
            $expectedEnd      = $expectedStart
                ? \Carbon\Carbon::parse($expectedStart)->endOfMonth()->toDateString()
                : null;
            $expectedLabel    = $expectedStart
                ? \Carbon\Carbon::parse($expectedStart)->translatedFormat('F Y')
                : null;

            // Check if uploaded period matches expectation
            $uploadMonth  = $uploadStart ? substr($uploadStart, 0, 7) : null; // YYYY-MM
            $expectMonth  = $expectedStart ? substr($expectedStart, 0, 7) : null;
            $periodMatch  = $uploadMonth && $expectMonth && $uploadMonth === $expectMonth;
            $isFuture     = $uploadStart && $expectedEnd && $uploadStart > $expectedEnd;
            $isPast       = $uploadStart && $expectedStart && $uploadStart < $expectedStart
                            && $uploadMonth !== $expectMonth;

            $summary = [
                'total_days'      => count($result['daily_data']),
                'date_range'      => [
                    'start' => $uploadStart,
                    'end'   => $uploadEnd,
                ],
                'total_records'   => count($result['daily_data']) * count($allRoomTypes),
                'room_types'      => $allRoomTypes,
                'expected_period' => $expectedLabel,
                'period_match'    => $periodMatch,
                'is_future'       => $isFuture,
                'is_past'         => $isPast,
            ];

            return response()->json([
                'success'   => true,
                'message'   => 'File valid dan siap diproses',
                'summary'   => $summary,
                'file_name' => $file->getClientOriginalName(),
            ]);

        } catch (\Exception $e) {
            \Log::error('Validation error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
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
     * Manual trigger for model retraining.
     *
     * model_type: 'single' | 'multi' | 'both'
     * simulate:   boolean — dev/staging only; defaults to false (real training)
     */
    public function retrain(Request $request)
    {
        $request->validate([
            'model_type' => 'required|in:single,multi,both',
            'simulate'   => 'boolean',
        ]);

        $modelType = $request->input('model_type');
        $simulate  = $request->boolean('simulate', false)
                     && !app()->isProduction();

        try {
            $types = $modelType === 'both' ? ['single', 'multi'] : [$modelType];

            foreach ($types as $type) {
                RetrainModelJob::dispatch(null, $type, $simulate);
            }

            $label   = $modelType === 'both' ? 'kedua model' : "model {$modelType}";
            $simNote = $simulate ? ' (simulasi)' : '';

            return response()->json([
                'success'    => true,
                'message'    => "Retraining {$label}{$simNote} telah dimulai.",
                'model_type' => $modelType,
                'simulate'   => $simulate,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memulai retraining: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Real-time retraining status for frontend polling.
     * Returns per-model-type training state, champions, and the latest version record.
     */
    public function retrainingStatus(Request $request)
    {
        $modelType = $request->input('model_type');

        $formatVersion = fn ($v) => [
            'id'                => $v->id,
            'version'           => $v->version,
            'model_type'        => $v->model_type,
            'status'            => $v->status,
            'is_champion'       => (bool) $v->is_champion,
            'mape'              => $v->mape !== null ? (float) $v->mape : null,
            'r2_score'          => $v->r2_score !== null ? (float) $v->r2_score : null,
            'rmse'              => $v->rmse !== null ? (float) $v->rmse : null,
            'error_message'     => $v->error_message,
            'training_duration' => $v->training_duration_seconds !== null ? (float) $v->training_duration_seconds : null,
            'created_at'        => $v->created_at->format('d M Y H:i:s'),
        ];

        // Per-type: latest version + is_training flag
        $perType = [];
        foreach (['single', 'multi'] as $type) {
            if ($modelType && $modelType !== $type) {
                continue;
            }
            $latest = \App\Models\ModelVersion::where('model_type', $type)
                ->orderBy('created_at', 'desc')
                ->first();

            $perType[$type] = [
                'is_training' => $latest && $latest->status === 'training',
                'latest'      => $latest ? $formatVersion($latest) : null,
            ];
        }

        $isTrainingAny = collect($perType)->contains(fn ($t) => $t['is_training']);

        return response()->json([
            'is_training' => $isTrainingAny,
            'per_type'    => $perType,
            'champions'   => $this->retrainingService->getChampionsSummary(),
        ]);
    }

    /**
     * Dedicated retrain history page — rendered via Inertia
     */
    public function retrainHistory()
    {
        $versions = ModelVersion::orderBy('created_at', 'desc')
            ->get()
            ->map(function ($v) {
                $metadata = is_array($v->metadata) ? $v->metadata : [];
                return [
                    'id'                  => $v->id,
                    'version'             => $v->version,
                    'model_type'          => $v->model_type,
                    'is_champion'         => (bool) $v->is_champion,
                    'status'              => $v->status,
                    'mape'                => $v->mape !== null ? (float) $v->mape : null,
                    'r2_score'            => $v->r2_score !== null ? (float) $v->r2_score : null,
                    'rmse'                => $v->rmse !== null ? (float) $v->rmse : null,
                    'trained_on_records'  => (int) $v->trained_on_records,
                    'training_duration'   => $v->training_duration_seconds !== null ? (float) $v->training_duration_seconds : null,
                    'error_message'       => $v->error_message,
                    'retraining_notes'    => $v->retraining_notes,
                    'accuracy'            => $v->mape !== null ? round(100 - (float) $v->mape, 2) : null,
                    'per_room'            => $metadata['per_room'] ?? null,
                    'created_at'          => $v->created_at->format('d M Y H:i'),
                    'created_at_iso'      => $v->created_at->toISOString(),
                    'next_retraining_due' => $v->next_retraining_due
                        ? \Carbon\Carbon::parse($v->next_retraining_due)->format('d M Y')
                        : null,
                ];
            });

        $champions = $this->retrainingService->getChampionsSummary();

        // Summary stats
        $totalVersions  = $versions->count();
        $singleVersions = $versions->where('model_type', 'single')->count();
        $multiVersions  = $versions->where('model_type', 'multi')->count();
        $singleChampion = $versions->firstWhere('model_type', 'single');
        $multiChampion  = $versions->firstWhere('model_type', 'multi');

        return Inertia::render('RetrainHistory/Index', [
            'versions'       => $versions->values(),
            'champions'      => $champions,
            'stats'          => [
                'total'          => $totalVersions,
                'single_count'   => $singleVersions,
                'multi_count'    => $multiVersions,
                'best_single_mape' => $versions->where('model_type','single')->whereNotNull('mape')->min('mape'),
                'best_multi_mape'  => $versions->where('model_type','multi')->whereNotNull('mape')->min('mape'),
            ],
        ]);
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
     * Delete upload record AND the historical data for that month.
     * This allows re-uploading a corrected file for the same month.
     */
    public function destroy(TrainingUpload $upload)
    {
        try {
            $deletedRows = 0;

            // Delete historical data for this upload's month/year if known.
            // Use whereYear/whereMonth (portable across SQLite & MySQL) instead of substr (#2).
            if ($upload->month_period && $upload->year_period) {
                $deletedRows = \App\Models\HistoricalOccupancyData::whereYear('date', $upload->year_period)
                    ->whereMonth('date', $upload->month_period)
                    ->delete();
            }

            // Delete uploaded file from storage
            if (Storage::exists($upload->file_path)) {
                Storage::delete($upload->file_path);
            }

            $upload->delete();

            $message = $deletedRows > 0
                ? "Upload dihapus. {$deletedRows} data historis bulan tersebut juga dihapus dari database."
                : 'Upload berhasil dihapus.';

            return response()->json([
                'success'       => true,
                'message'       => $message,
                'deleted_rows'  => $deletedRows,
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
