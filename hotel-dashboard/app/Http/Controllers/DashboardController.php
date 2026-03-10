<?php

namespace App\Http\Controllers;

use App\Models\Prediction;
use App\Models\RoomType;
use App\Models\HistoricalOccupancyData;
use App\Models\ModelVersion;
use App\Services\RetrainingScheduler;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::today();

        // Get all room types
        $roomTypes = RoomType::where('is_active', true)->get();

        // Build date filters
        $dateStart = $request->input('date_start', Carbon::now()->startOfMonth());
        $dateEnd = $request->input('date_end', Carbon::now()->addMonths(3)->endOfMonth());
        $roomTypeFilter = $request->input('room_types', null);

        // Get MONTHLY predictions (next 3 months) - Multi-Output
        $multiQuery = Prediction::with('roomType')
            ->where('model_type', 'multi')
            ->where('predicted_for_date', '>=', $dateStart)
            ->where('predicted_for_date', '<=', $dateEnd);

        if ($roomTypeFilter) {
            $multiQuery->whereIn('room_type_id', $roomTypeFilter);
        }

        $multiPredictions = $multiQuery->orderBy('predicted_for_date')->get();

        // Get Single-Output predictions (total hotel occupancy)
        $singleQuery = Prediction::where('model_type', 'single')
            ->where('predicted_for_date', '>=', $dateStart)
            ->where('predicted_for_date', '<=', $dateEnd);

        $singlePredictions = $singleQuery->orderBy('predicted_for_date')->get();

        // Calculate KPIs from Multi-Output predictions
        $avgOccupancy = $multiPredictions->avg('predicted_occupancy_rate') ?? 0;
        $predictedRevenue = $multiPredictions->sum('predicted_revenue') ?? 0;

        // Get last month's historical data for comparison
        $lastMonth = Carbon::now()->subMonth();
        $historicalData = HistoricalOccupancyData::with('roomType')
            ->whereYear('date', $lastMonth->year)
            ->whereMonth('date', $lastMonth->month)
            ->get();

        $historicalRevenue = $historicalData->sum('revenue');
        $historicalAvgOccupancy = $historicalData->avg('occupancy_rate') ?? 0;

        // Calculate trends (comparing next month prediction vs last month actual)
        $nextMonthPrediction = $multiPredictions->where('predicted_for_date', Carbon::now()->addMonth()->startOfMonth())->first();

        $revenueTrend = $historicalRevenue > 0 && $nextMonthPrediction
            ? round((($nextMonthPrediction->predicted_revenue - $historicalRevenue) / $historicalRevenue) * 100, 1)
            : 0;

        $occupancyTrend = $historicalAvgOccupancy > 0 && $avgOccupancy > 0
            ? round((($avgOccupancy - $historicalAvgOccupancy) / $historicalAvgOccupancy) * 100, 1)
            : 0;

        // Prepare chart data for MONTHLY occupancy trend
        $chartData = $this->prepareMonthlyChartData($multiPredictions, $singlePredictions);

        // Room breakdown data (next 3 months average)
        $roomBreakdown = $this->prepareRoomBreakdown($roomTypes, $multiPredictions);

        // Monthly predictions grouped by month
        $monthlyPredictions = $multiPredictions->groupBy(function ($pred) {
            return Carbon::parse($pred->predicted_for_date)->format('Y-m');
        });

        // Get REAL-TIME champion model metrics from database
        $singleChampion = $this->getChampionModelInfo('single');
        $multiChampion = $this->getChampionModelInfo('multi');

        // Model comparison data with LIVE metrics
        $modelComparison = [
            'single' => [
                'name' => 'Single-Output (Total Hotel)',
                'predictions' => $singlePredictions->map(function ($pred) {
                    return [
                        'month' => Carbon::parse($pred->predicted_for_date)->format('M Y'),
                        'occupancy' => $pred->predicted_occupancy_rate ?? 0,
                        'rooms' => $pred->predicted_rooms_occupied ?? 0,
                        'confidence' => $pred->confidence_level ?? 0,
                    ];
                })->values(),
                'avg_confidence' => round($singlePredictions->avg('confidence_level') ?? 0, 1),
                'mape' => $singleChampion['mape'] ?? null,
                'r2_score' => $singleChampion['r2_score'] ?? null,
                'version' => $singleChampion['version'] ?? 'N/A',
            ],
            'multi' => [
                'name' => 'Multi-Output (Per Room Type)',
                'predictions' => $monthlyPredictions->map(function ($monthPreds, $month) {
                    return [
                        'month' => Carbon::parse($month)->format('M Y'),
                        'occupancy' => round($monthPreds->avg('predicted_occupancy_rate') ?? 0, 1),
                        'by_room' => $monthPreds->map(function ($p) {
                            return [
                                'room_type' => optional($p->roomType)->name ?? 'Unknown',
                                'room_code' => optional($p->roomType)->code ?? 'N/A',
                                'occupancy' => $p->predicted_occupancy_rate ?? 0,
                            ];
                        })->values(),
                    ];
                })->values(),
                'avg_confidence' => round($multiPredictions->avg('confidence_level'), 1),
                'mape' => $multiChampion['mape'] ?? null,
                'r2_score' => $multiChampion['r2_score'] ?? null,
                'version' => $multiChampion['version'] ?? 'N/A',
            ],
        ];

        // Generate alerts/recommendations
        $alerts = $this->generateAlerts($multiPredictions, $singlePredictions, $avgOccupancy);

        // Get retraining status (6-month cycle tracking)
        $retrainingScheduler = app(RetrainingScheduler::class);
        $retrainingStatus = $retrainingScheduler->getAllModelsStatus();

        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'avgOccupancy' => round($avgOccupancy, 1),
                'predictedRevenue' => $predictedRevenue,
                'totalRooms' => $roomTypes->sum('total_rooms'),
                'occupancyTrend' => $occupancyTrend,
                'revenueTrend' => $revenueTrend,
                'multiPredictions' => $multiPredictions->count(),
                'singlePredictions' => $singlePredictions->count(),
            ],
            'chartData' => $chartData,
            'roomBreakdown' => $roomBreakdown,
            'monthlyPredictions' => $monthlyPredictions,
            'modelComparison' => $modelComparison,
            'alerts' => $alerts,
            'retrainingStatus' => $retrainingStatus,
            'lastUpdated' => $multiPredictions->max('prediction_date'),
            'roomTypes' => $roomTypes,
            'filters' => [
                'date_start' => $dateStart instanceof Carbon ? $dateStart->format('Y-m-d') : $dateStart,
                'date_end' => $dateEnd instanceof Carbon ? $dateEnd->format('Y-m-d') : $dateEnd,
                'room_types' => $roomTypeFilter,
            ],
        ]);
    }

    private function prepareMonthlyChartData($multiPredictions, $singlePredictions)
    {
        // Get last 6 months of historical data (monthly aggregated)
        $sixMonthsAgo = Carbon::now()->subMonths(6)->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->endOfMonth();

        $historicalData = HistoricalOccupancyData::with('roomType')
            ->whereBetween('date', [$sixMonthsAgo, $lastMonth])
            ->get();

        // Group historical by month
        $historical = $historicalData
            ->groupBy(function ($item) {
                return Carbon::parse($item->date)->format('Y-m');
            })
            ->map(function ($monthData, $month) {
                return [
                    'date' => Carbon::parse($month . '-01')->format('Y-m-d'),
                    'occupancy' => round($monthData->avg('occupancy_rate'), 1),
                    'revenue' => $monthData->sum('revenue'),
                ];
            })
            ->sortBy('date')
            ->values();

        // Prepare Multi-Output prediction data (grouped by month) - Use this as the main predicted data
        $predicted = $multiPredictions
            ->groupBy(function ($pred) {
                return Carbon::parse($pred->predicted_for_date)->format('Y-m');
            })
            ->map(function ($monthPreds, $month) {
                return [
                    'date' => Carbon::parse($month . '-01')->format('Y-m-d'),
                    'occupancy' => round($monthPreds->avg('predicted_occupancy_rate'), 1),
                    'revenue' => $monthPreds->sum('predicted_revenue'),
                ];
            })
            ->sortBy('date')
            ->values();

        return [
            'historical' => $historical,
            'predicted' => $predicted,
        ];
    }

    private function generateAlerts($multiPredictions, $singlePredictions, $avgOccupancy)
    {
        $alerts = [];

        // Check if predictions are stale (older than 1 month)
        $lastPredictionDate = $multiPredictions->max('prediction_date');
        if ($lastPredictionDate && Carbon::parse($lastPredictionDate)->lt(Carbon::now()->subMonth())) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Prediksi Perlu Diperbarui',
                'message' => 'Prediksi terakhir dibuat ' . Carbon::parse($lastPredictionDate)->diffForHumans() . '. Regenerasi prediksi untuk data terbaru.',
                'action' => 'Regenerate Predictions',
                'priority' => 'high',
            ];
        }

        // High occupancy alert (>80%)
        $highOccupancy = $multiPredictions->filter(fn($p) => $p->predicted_occupancy_rate > 80);
        if ($highOccupancy->count() > 0) {
            $alerts[] = [
                'type' => 'success',
                'title' => 'Okupansi Tinggi Diprediksi',
                'message' => $highOccupancy->count() . ' prediksi menunjukkan okupansi >80%. Pertimbangkan menaikkan harga atau menawarkan upgrade.',
                'action' => 'View Details',
                'priority' => 'medium',
            ];
        }

        // Low occupancy alert (<40%)
        $lowOccupancy = $multiPredictions->filter(fn($p) => $p->predicted_occupancy_rate < 40);
        if ($lowOccupancy->count() > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Okupansi Rendah Terdeteksi',
                'message' => $lowOccupancy->count() . ' prediksi menunjukkan okupansi <40%. Pertimbangkan promosi atau diskon untuk meningkatkan okupansi.',
                'action' => 'Plan Promotion',
                'priority' => 'high',
            ];
        }

        // Model confidence alert
        $avgConfidence = $multiPredictions->avg('confidence_level') ?? 0;
        if ($avgConfidence < 60) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Confidence Level Rendah',
                'message' => 'Rata-rata confidence model: ' . round($avgConfidence, 1) . '%. Pastikan data historis lengkap dan update.',
                'action' => 'Check Data',
                'priority' => 'medium',
            ];
        }

        // No predictions alert
        if ($multiPredictions->isEmpty() && $singlePredictions->isEmpty()) {
            $alerts[] = [
                'type' => 'error',
                'title' => 'Tidak Ada Prediksi',
                'message' => 'Belum ada prediksi tersedia. Generate prediksi untuk mendapatkan insight.',
                'action' => 'Generate Now',
                'priority' => 'high',
            ];
        }

        return $alerts;
    }

    private function prepareRoomBreakdown($roomTypes, $predictions)
    {
        return $roomTypes->map(function ($roomType) use ($predictions) {
            $roomPredictions = $predictions->where('room_type_id', $roomType->id);
            $avgOccupancy = $roomPredictions->avg('predicted_occupancy_rate') ?? 0;
            $predictedOccupied = round($roomType->total_rooms * ($avgOccupancy / 100));

            return [
                'id' => $roomType->id,
                'name' => $roomType->name ?? 'Unknown',
                'code' => $roomType->code ?? 'N/A',
                'capacity' => $roomType->total_rooms ?? 0,
                'predicted_occupied' => $predictedOccupied,
                'occupancy_rate' => round($avgOccupancy, 1),
                'base_price' => $roomType->base_price ?? 0,
                'status' => $avgOccupancy >= 80 ? 'high' : ($avgOccupancy >= 50 ? 'medium' : 'low'),
            ];
        });
    }

    /**
     * Get REAL-TIME champion model information from database
     * Returns fresh model metrics that update automatically after retraining
     */
    private function getChampionModelInfo($modelType)
    {
        // Get current champion from database
        $champion = ModelVersion::where('model_type', $modelType)
            ->where('is_champion', true)
            ->first();

        if (!$champion) {
            // Fallback if no champion exists
            return [
                'version' => 'N/A',
                'mape' => null,
                'r2_score' => null,
                'rmse' => null,
                'trained_at' => null,
                'status' => 'No champion model available',
            ];
        }

        return [
            'version' => $champion->version,
            'mape' => round($champion->mape, 2),
            'r2_score' => round($champion->r2_score, 4),
            'rmse' => round($champion->rmse, 4),
            'trained_at' => $champion->trained_at ? Carbon::parse($champion->trained_at)->format('Y-m-d H:i') : $champion->created_at->format('Y-m-d H:i'),
            'model_path' => $champion->model_path,
            'status' => 'Active Champion',
            'is_promoted' => $champion->is_promoted,
        ];
    }
}
