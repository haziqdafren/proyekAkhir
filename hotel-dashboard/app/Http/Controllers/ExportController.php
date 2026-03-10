<?php

namespace App\Http\Controllers;

use App\Models\HistoricalOccupancyData;
use App\Models\Prediction;
use App\Models\RoomType;
use App\Exports\PredictionsExport;
use App\Exports\HistoricalDataExport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ExportController extends Controller
{
    public function index()
    {
        $roomTypes = RoomType::where('is_active', true)->get();

        // Get available predictions grouped by month and model type
        $availablePredictions = Prediction::selectRaw('
                strftime("%Y-%m", predicted_for_date) as month,
                model_type,
                MIN(predicted_for_date) as start_date,
                MAX(predicted_for_date) as end_date,
                COUNT(*) as prediction_count
            ')
            ->groupBy('month', 'model_type')
            ->orderBy('month', 'desc')
            ->get()
            ->map(function ($item) {
                $date = Carbon::createFromFormat('Y-m', $item->month);
                return [
                    'month' => $item->month,
                    'label' => $date->format('F Y'),
                    'model_type' => $item->model_type,
                    'model_label' => $item->model_type === 'single' ? 'Single Output' : 'Multi Output',
                    'start_date' => $item->start_date,
                    'end_date' => $item->end_date,
                    'count' => $item->prediction_count,
                ];
            });

        // Get export templates/presets (predictions only)
        $exportTemplates = [
            [
                'id' => 'prediction_report',
                'name' => 'Laporan Prediksi Lengkap',
                'description' => 'Export semua data prediksi okupansi yang telah dihasilkan',
                'icon' => 'chart',
                'type' => 'prediction',
                'format' => ['excel', 'csv', 'pdf'],
            ],
            [
                'id' => 'prediction_single',
                'name' => 'Prediksi Single Output',
                'description' => 'Export hanya hasil prediksi Single Output',
                'icon' => 'document',
                'type' => 'prediction',
                'format' => ['excel', 'csv', 'pdf'],
            ],
            [
                'id' => 'prediction_multi',
                'name' => 'Prediksi Multi Output',
                'description' => 'Export hanya hasil prediksi Multi Output',
                'icon' => 'documents',
                'type' => 'prediction',
                'format' => ['excel', 'csv', 'pdf'],
            ],
            [
                'id' => 'custom_export',
                'name' => 'Export Custom',
                'description' => 'Export data prediksi dengan filter tanggal dan tipe kamar',
                'icon' => 'database',
                'type' => 'prediction',
                'format' => ['excel', 'csv', 'pdf'],
            ],
        ];

        // Get recent exports - currently empty as we don't have export history table
        // This can be enhanced later with a database table to track exports
        $recentExports = [];

        return Inertia::render('Export/Index', [
            'roomTypes' => $roomTypes,
            'availablePredictions' => $availablePredictions,
            'exportTemplates' => $exportTemplates,
        ]);
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'template_id' => 'required|string',
            'format' => 'required|in:pdf,excel,csv,json',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date',
            'model_type' => 'nullable|string|in:single,multi',
            'room_types' => 'nullable|array',
            'include_charts' => 'nullable|boolean',
            'include_summary' => 'nullable|boolean',
            'include_raw_data' => 'nullable|boolean',
            // Multi-select predictions for prediction_report template
            'predictions' => 'nullable|array',
            'predictions.*.month' => 'required_with:predictions|string',
            'predictions.*.model_type' => 'required_with:predictions|string|in:single,multi',
            'predictions.*.start_date' => 'required_with:predictions|date',
            'predictions.*.end_date' => 'required_with:predictions|date',
        ]);

        $templateId = $validated['template_id'];
        $format = $validated['format'];

        // Generate appropriate filename
        $timestamp = Carbon::now()->format('Ymd_His');
        $filename = $this->getFilename($templateId, $format, $timestamp);

        try {
            // Check if using multi-select predictions array (new approach - all templates support it)
            if (!empty($validated['predictions']) && is_array($validated['predictions'])) {
                // Multi-select mode: Use predictions array
                return $this->exportMultiplePredictions($validated, $filename);
            }

            // Route to appropriate export method based on template (legacy single-select)
            switch ($templateId) {
                case 'prediction_report':
                case 'custom_export':
                    return $this->exportPredictions($validated, $filename);

                case 'prediction_single':
                    $validated['model_type'] = 'single';
                    return $this->exportPredictions($validated, $filename);

                case 'prediction_multi':
                    $validated['model_type'] = 'multi';
                    return $this->exportPredictions($validated, $filename);

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Template tidak ditemukan',
                    ], 404);
            }
        } catch (\Exception $e) {
            \Log::error('Export failed', [
                'template' => $templateId,
                'format' => $format,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat export: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function exportPredictions($filters, $filename)
    {
        $format = $filters['format'];

        if ($format === 'pdf') {
            // Build query for PDF
            $query = Prediction::with('roomType')->orderBy('predicted_for_date');

            // Apply filters
            if (!empty($filters['date_start'])) {
                $query->where('predicted_for_date', '>=', $filters['date_start']);
            }
            if (!empty($filters['date_end'])) {
                $query->where('predicted_for_date', '<=', $filters['date_end']);
            }
            if (!empty($filters['model_type'])) {
                $query->where('model_type', $filters['model_type']);
            }
            if (!empty($filters['room_types'])) {
                $query->whereIn('room_type_id', $filters['room_types']);
            }

            $predictions = $query->get();
            $pdf = Pdf::loadView('exports.predictions-pdf', compact('predictions'));
            return $pdf->download($filename);
        } elseif ($format === 'excel') {
            return Excel::download(new PredictionsExport($filters), $filename);
        } elseif ($format === 'csv') {
            return Excel::download(new PredictionsExport($filters), $filename, \Maatwebsite\Excel\Excel::CSV);
        }

        // For other formats, return error
        return response()->json([
            'success' => false,
            'message' => 'Format ' . strtoupper($format) . ' belum didukung untuk template ini',
        ], 400);
    }

    /**
     * Export multiple predictions at once (for prediction_report template)
     */
    private function exportMultiplePredictions($filters, $filename)
    {
        $format = $filters['format'];
        $predictions = $filters['predictions'] ?? [];

        if (empty($predictions)) {
            return response()->json([
                'success' => false,
                'message' => 'Silakan pilih minimal satu prediksi untuk di-export',
            ], 400);
        }

        // Build filters for the export class
        // For multiple predictions, we pass the predictions array directly
        $exportFilters = [
            'format' => $format,
            'room_types' => $filters['room_types'] ?? null,
            'include_charts' => $filters['include_charts'] ?? false,
            'include_summary' => $filters['include_summary'] ?? false,
            'include_raw_data' => $filters['include_raw_data'] ?? false,
            'predictions' => $predictions, // Array of {month, model_type, start_date, end_date}
        ];

        if ($format === 'pdf') {
            // Build query for PDF with multiple predictions
            $query = Prediction::with('roomType')->orderBy('predicted_for_date');

            $query->where(function ($q) use ($predictions) {
                foreach ($predictions as $prediction) {
                    $q->orWhere(function ($subQ) use ($prediction) {
                        $subQ->where('predicted_for_date', '>=', $prediction['start_date'])
                             ->where('predicted_for_date', '<=', $prediction['end_date'])
                             ->where('model_type', $prediction['model_type']);
                    });
                }
            });

            if (!empty($filters['room_types'])) {
                $query->whereIn('room_type_id', $filters['room_types']);
            }

            $predictionData = $query->get();
            $pdf = Pdf::loadView('exports.predictions-pdf', ['predictions' => $predictionData]);
            return $pdf->download($filename);
        } elseif ($format === 'excel') {
            return Excel::download(new PredictionsExport($exportFilters), $filename);
        } elseif ($format === 'csv') {
            return Excel::download(new PredictionsExport($exportFilters), $filename, \Maatwebsite\Excel\Excel::CSV);
        }

        return response()->json([
            'success' => false,
            'message' => 'Format ' . strtoupper($format) . ' belum didukung untuk template ini',
        ], 400);
    }

    private function exportHistoricalData($filters, $filename)
    {
        $format = $filters['format'];

        if ($format === 'excel') {
            return Excel::download(new HistoricalDataExport($filters), $filename);
        } elseif ($format === 'csv') {
            return Excel::download(new HistoricalDataExport($filters), $filename, \Maatwebsite\Excel\Excel::CSV);
        }

        return response()->json([
            'success' => false,
            'message' => 'Format ' . strtoupper($format) . ' belum didukung untuk template ini',
        ], 400);
    }

    private function exportComparison($filters, $filename)
    {
        // For comparison report, we'll export both historical and predictions
        // This can be enhanced with multiple sheets
        $format = $filters['format'];

        if ($format === 'excel') {
            return Excel::download(new PredictionsExport($filters), $filename);
        } elseif ($format === 'csv') {
            return Excel::download(new PredictionsExport($filters), $filename, \Maatwebsite\Excel\Excel::CSV);
        }

        return response()->json([
            'success' => false,
            'message' => 'Format ' . strtoupper($format) . ' belum didukung untuk template ini',
        ], 400);
    }

    private function exportRevenueAnalysis($filters, $filename)
    {
        // Revenue analysis uses historical data
        return $this->exportHistoricalData($filters, $filename);
    }

    private function getFilename($templateId, $format, $timestamp)
    {
        $templateNames = [
            'prediction_report' => 'laporan_prediksi_lengkap',
            'prediction_single' => 'prediksi_single_output',
            'prediction_multi' => 'prediksi_multi_output',
            'custom_export' => 'export_custom',
        ];

        $name = $templateNames[$templateId] ?? 'export';
        $extension = $format === 'excel' ? 'xlsx' : $format;

        return "{$name}_{$timestamp}.{$extension}";
    }
}
