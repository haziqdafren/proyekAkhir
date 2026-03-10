<?php

namespace App\Http\Controllers;

use App\Models\Prediction;
use App\Models\RoomType;
use App\Models\HistoricalOccupancyData;
use App\Models\ModelVersion;
use App\Services\MLPredictionService;
use App\Services\FeatureEngineeringService;
use App\Services\HistoricalDataAggregationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Carbon\Carbon;

class PredictionController extends Controller
{
    protected MLPredictionService $mlService;
    protected FeatureEngineeringService $featureService;
    protected HistoricalDataAggregationService $aggregationService;

    public function __construct(
        MLPredictionService $mlService,
        FeatureEngineeringService $featureService,
        HistoricalDataAggregationService $aggregationService
    ) {
        $this->mlService = $mlService;
        $this->featureService = $featureService;
        $this->aggregationService = $aggregationService;
    }
    public function index(Request $request)
    {
        // Get all room types
        $roomTypes = RoomType::where('is_active', true)->get();

        // Get ONLY FUTURE predictions (from current month onwards)
        $allPredictions = Prediction::with('roomType')
            ->where('predicted_for_date', '>=', Carbon::now()->startOfMonth())
            ->orderBy('predicted_for_date', 'asc')
            ->orderBy('model_type')
            ->get();

        // Group predictions by month for timeline view
        $predictionsByMonth = $allPredictions->groupBy(function ($prediction) {
            return Carbon::parse($prediction->predicted_for_date)->format('Y-m');
        })->map(function ($monthPredictions, $monthKey) {
            $date = Carbon::createFromFormat('Y-m', $monthKey);
            return [
                'month_key' => $monthKey,
                'month_label' => $date->format('F Y'),
                'month_short' => $date->format('M Y'),
                'predictions' => $monthPredictions->values(),
                'avg_occupancy' => round($monthPredictions->avg('predicted_occupancy_rate'), 1),
                'count' => $monthPredictions->count(),
            ];
        })->values();

        // Calculate quick stats
        $stats = [
            'total_predictions' => $allPredictions->count(),
            'avg_occupancy' => $allPredictions->count() > 0 ? round($allPredictions->avg('predicted_occupancy_rate'), 1) : 0,
            'highest_month' => $predictionsByMonth->sortByDesc('avg_occupancy')->first(),
            'lowest_month' => $predictionsByMonth->sortBy('avg_occupancy')->first(),
            'single_count' => $allPredictions->where('model_type', 'single')->count(),
            'multi_count' => $allPredictions->where('model_type', 'multi')->count(),
        ];

        // Get REAL-TIME champion model information
        $singleChampion = $this->getChampionModelInfo('single');
        $multiChampion = $this->getChampionModelInfo('multi');

        return Inertia::render('Predictions/Index', [
            'roomTypes' => $roomTypes,
            'allPredictions' => $allPredictions,
            'predictionsByMonth' => $predictionsByMonth,
            'stats' => $stats,
            'singleModelInfo' => $singleChampion,
            'multiModelInfo' => $multiChampion,
        ]);
    }

    /**
     * OLD METHOD - Keep for reference but rename
     */
    public function indexOld(Request $request)
    {
        // Get filter parameters with proper type casting and validation
        $roomTypeFilter = $request->get('room_type', 'all');

        // For monthly predictions, use months instead of days
        $monthsAhead = $request->get('months_ahead', 3);
        $monthsAhead = is_numeric($monthsAhead) ? (int) $monthsAhead : 3;
        $monthsAhead = max(1, min($monthsAhead, 12)); // Between 1 and 12 months

        $modelType = $request->get('model_type', 'multi'); // Default to multi-output

        // Get all room types for filter dropdown
        $roomTypes = RoomType::where('is_active', true)->get();

        // Build predictions query for MONTHLY predictions
        $predictionsQuery = Prediction::with('roomType')
            ->where('predicted_for_date', '>=', Carbon::now()->startOfMonth())
            ->where('predicted_for_date', '<=', Carbon::now()->addMonths($monthsAhead)->endOfMonth());

        if ($roomTypeFilter !== 'all') {
            $predictionsQuery->where('room_type_id', $roomTypeFilter);
        }

        if ($modelType !== 'all') {
            $predictionsQuery->where('model_type', $modelType);
        }

        $predictions = $predictionsQuery->orderBy('predicted_for_date')
            ->orderBy('room_type_id')
            ->get();

        // Group predictions by month for the timeline
        $predictionsByMonth = $predictions->groupBy(function ($prediction) {
            return Carbon::parse($prediction->predicted_for_date)->format('Y-m');
        });

        // Calculate statistics with null safety
        $stats = [
            'avgOccupancy' => $predictions->avg('predicted_occupancy_rate') ?? 0.0,
            'maxOccupancy' => $predictions->max('predicted_occupancy_rate') ?? 0.0,
            'minOccupancy' => $predictions->min('predicted_occupancy_rate') ?? 0.0,
            'avgConfidence' => $predictions->avg('confidence_level') ?? 0.0,
            'totalPredictions' => $predictions->count(),
        ];

        // Get predictions by room type for comparison
        $predictionsByRoomType = [];
        foreach ($roomTypes as $roomType) {
            $roomPredictions = $predictions->where('room_type_id', $roomType->id);

            if ($roomPredictions->isNotEmpty()) {
                $predictionsByRoomType[] = [
                    'id' => $roomType->id,
                    'name' => $roomType->name,
                    'avgOccupancy' => round($roomPredictions->avg('predicted_occupancy_rate'), 1),
                    'avgConfidence' => round($roomPredictions->avg('confidence_level'), 1),
                    'trend' => $this->calculateTrend($roomType->id),
                    'peakDate' => $roomPredictions->sortByDesc('predicted_occupancy_rate')->first()->predicted_for_date,
                    'peakOccupancy' => round($roomPredictions->max('predicted_occupancy_rate'), 1),
                    'color' => $this->getRoomTypeColor($roomType->id),
                ];
            }
        }

        // Prepare chart data for daily predictions
        $chartData = [
            'dates' => [],
            'series' => [],
        ];

        // Group by room type for chart
        foreach ($roomTypes as $roomType) {
            $roomPredictions = $predictions->where('room_type_id', $roomType->id);

            if ($roomPredictions->isNotEmpty()) {
                $chartData['series'][] = [
                    'name' => $roomType->name,
                    'data' => $roomPredictions->map(function ($prediction) {
                        return [
                            'x' => Carbon::parse($prediction->predicted_for_date)->timestamp * 1000,
                            'y' => round($prediction->predicted_occupancy_rate, 1),
                        ];
                    })->values()->toArray(),
                ];
            }
        }

        // Get recommendations based on predictions
        $recommendations = $this->generateRecommendations($predictions, $roomTypes);

        return Inertia::render('Predictions/Index', [
            'predictions' => $predictions,
            'predictionsByMonth' => $predictionsByMonth,
            'predictionsByRoomType' => $predictionsByRoomType,
            'stats' => $stats,
            'chartData' => $chartData,
            'roomTypes' => $roomTypes,
            'recommendations' => $recommendations,
            'filters' => [
                'room_type' => $roomTypeFilter,
                'months_ahead' => $monthsAhead,
                'model_type' => $modelType,
            ],
        ]);
    }

    private function calculateTrend($roomTypeId)
    {
        // Get last 7 days historical vs next 7 days predicted
        // For demo purposes, return random trend
        return rand(-15, 25);
    }

    private function getRoomTypeColor($roomTypeId)
    {
        $colors = ['primary', 'green', 'purple', 'orange'];
        return $colors[($roomTypeId - 1) % count($colors)];
    }

    private function generateRecommendations($predictions, $roomTypes)
    {
        $recommendations = [];

        // Find peak occupancy periods
        $highOccupancyDays = $predictions->where('predicted_occupancy_rate', '>', 80);
        if ($highOccupancyDays->count() > 0) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Okupansi Tinggi Terdeteksi',
                'description' => "Terdapat {$highOccupancyDays->count()} hari dengan prediksi okupansi di atas 80%. Pertimbangkan untuk menaikkan harga atau menyiapkan staf tambahan.",
                'icon' => 'trending-up',
                'priority' => 'high',
            ];
        }

        // Find low occupancy periods
        $lowOccupancyDays = $predictions->where('predicted_occupancy_rate', '<', 40);
        if ($lowOccupancyDays->count() > 0) {
            $recommendations[] = [
                'type' => 'info',
                'title' => 'Peluang Promosi',
                'description' => "Terdapat {$lowOccupancyDays->count()} hari dengan okupansi rendah (<40%). Pertimbangkan untuk melakukan promosi atau diskon khusus.",
                'icon' => 'megaphone',
                'priority' => 'medium',
            ];
        }

        // Room type specific recommendations
        foreach ($roomTypes as $roomType) {
            $roomPredictions = $predictions->where('room_type_id', $roomType->id);
            $avgOccupancy = $roomPredictions->avg('predicted_occupancy_rate');

            if ($avgOccupancy > 85) {
                $recommendations[] = [
                    'type' => 'success',
                    'title' => "{$roomType->name} - Permintaan Tinggi",
                    'description' => "Okupansi rata-rata {$roomType->name} mencapai " . round($avgOccupancy, 1) . "%. Pertimbangkan untuk menaikkan harga atau menawarkan upgrade.",
                    'icon' => 'star',
                    'priority' => 'medium',
                ];
            } elseif ($avgOccupancy < 45) {
                $recommendations[] = [
                    'type' => 'warning',
                    'title' => "{$roomType->name} - Okupansi Rendah",
                    'description' => "Okupansi rata-rata {$roomType->name} hanya " . round($avgOccupancy, 1) . "%. Perlu strategi marketing atau penyesuaian harga.",
                    'icon' => 'alert',
                    'priority' => 'medium',
                ];
            }
        }

        return array_slice($recommendations, 0, 4); // Return max 4 recommendations
    }

    /**
     * Display single-output prediction page
     */
    public function singleOutput(Request $request)
    {
        $roomTypes = RoomType::where('is_active', true)->get();
        $totalRooms = $roomTypes->sum('total_rooms');

        // Get recent predictions for display
        $recentPredictions = Prediction::where('model_type', 'single')
            ->where('predicted_for_date', '>=', Carbon::now())
            ->orderBy('predicted_for_date')
            ->limit(30)
            ->get()
            ->map(function ($prediction) use ($totalRooms) {
                return $this->enrichPredictionWithInsights($prediction, $totalRooms, 'single');
            });

        // Get month-to-month comparison
        $comparisons = $this->getMonthComparisons($recentPredictions, 'single');

        // Get REAL-TIME champion model info from database
        $modelInfo = $this->getChampionModelInfo('single');

        return Inertia::render('Predictions/SingleOutput', [
            'roomTypes' => $roomTypes,
            'recentPredictions' => $recentPredictions,
            'comparisons' => $comparisons,
            'totalRooms' => $totalRooms,
            'modelInfo' => $modelInfo,
        ]);
    }

    /**
     * Display multi-output prediction page
     */
    public function multiOutput(Request $request)
    {
        $roomTypes = RoomType::where('is_active', true)->get();

        // Get recent predictions for display
        $recentPredictions = Prediction::where('model_type', 'multi')
            ->where('predicted_for_date', '>=', Carbon::now())
            ->with('roomType')
            ->orderBy('predicted_for_date')
            ->limit(30)
            ->get()
            ->map(function ($prediction) {
                $roomCapacity = $prediction->roomType ? $prediction->roomType->total_rooms : 0;
                return $this->enrichPredictionWithInsights($prediction, $roomCapacity, 'multi');
            });

        // Get month-to-month comparison
        $comparisons = $this->getMonthComparisons($recentPredictions, 'multi');

        // Get REAL-TIME champion model info from database
        $modelInfo = $this->getChampionModelInfo('multi');

        return Inertia::render('Predictions/MultiOutput', [
            'roomTypes' => $roomTypes,
            'recentPredictions' => $recentPredictions,
            'comparisons' => $comparisons,
            'modelInfo' => $modelInfo,
            'roomCapacities' => config('ml.room_capacities'),
        ]);
    }

    /**
     * Generate single-output prediction (total occupancy)
     */
    public function generateSingle(Request $request)
    {
        $request->validate([
            'predict_for_month' => 'required|date',
        ]);

        try {
            DB::beginTransaction();

            // Get last available historical data (model is already trained!)
            // We just need the latest 6-12 months of data for prediction
            $predictMonth = Carbon::parse($request->predict_for_month);
            $targetMonth = $predictMonth->copy()->startOfMonth();

            // Get the most recent data available in database
            $latestData = HistoricalOccupancyData::orderBy('date', 'desc')->first();
            if (!$latestData) {
                return back()->withErrors([
                    'error' => 'No historical data available in database'
                ]);
            }

            $endDate = Carbon::parse($latestData->date)->endOfMonth();
            $startDate = $endDate->copy()->subMonths(11)->startOfMonth();

            $aggregatedData = $this->aggregationService->getAggregatedMonthlyData($startDate, $endDate);

            if ($aggregatedData->count() < 6) {
                return back()->withErrors([
                    'error' => 'Insufficient historical data. Need at least 6 months of data. Found: ' . $aggregatedData->count()
                ]);
            }

            // Project forward to include synthetic months up to the target month
            // This ensures predictions for different months get different feature values
            $projectedData = $this->projectHistoricalDataForward($aggregatedData, $targetMonth);

            // Check if prediction already exists for this month
            $existingPrediction = Prediction::where('model_type', 'single')
                ->where('room_type_id', null)
                ->whereYear('predicted_for_date', $targetMonth->year)
                ->whereMonth('predicted_for_date', $targetMonth->month)
                ->first();

            // Prepare features (6 months x 15 features) using projected data
            $features = $this->featureService->prepareFeatures($projectedData);

            // Make prediction
            $result = $this->mlService->predictSingleOutput($features);

            // Get champion model for confidence calculation
            $championModel = ModelVersion::getChampion('single');
            $confidenceLevel = 100 - ($championModel?->mape ?? 5); // Use champion's MAPE or default 5%

            $predictionData = [
                'room_type_id' => null, // Single output doesn't have specific room type
                'prediction_date' => Carbon::now(),
                'predicted_for_date' => $targetMonth,
                'predicted_occupancy_rate' => $result['prediction']['occupancy_rate'],
                'predicted_rooms_occupied' => (int) round($result['prediction']['rooms_sold']),
                'predicted_revenue' => $result['prediction']['rooms_sold'] * 500000, // Estimate
                'confidence_level' => $confidenceLevel,
                'model_type' => 'single',
                'model_version' => $championModel?->version ?? 'v1.0.0',
            ];

            // Update existing or create new
            if ($existingPrediction) {
                $existingPrediction->update($predictionData);
                $prediction = $existingPrediction;
                $action = 'updated';
            } else {
                $prediction = Prediction::create($predictionData);
                $action = 'created';
            }

            DB::commit();

            Log::info('Single-output prediction ' . $action, [
                'month' => $predictMonth->format('Y-m'),
                'occupancy_rate' => $result['prediction']['occupancy_rate'],
                'rooms_sold' => $result['prediction']['rooms_sold'],
                'action' => $action,
            ]);

            $message = $action === 'updated'
                ? "Prediksi berhasil diperbarui untuk {$predictMonth->format('F Y')}: {$result['prediction']['occupancy_rate']}% okupansi ({$result['prediction']['rooms_sold']} kamar)"
                : "Prediksi berhasil dibuat untuk {$predictMonth->format('F Y')}: {$result['prediction']['occupancy_rate']}% okupansi ({$result['prediction']['rooms_sold']} kamar)";

            return redirect()->route('predictions.single')->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Single-output prediction failed', [
                'error' => $e->getMessage(),
                'month' => $request->predict_for_month
            ]);

            return back()->withErrors([
                'error' => 'Gagal membuat prediksi: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Generate multi-output prediction (per room type)
     */
    public function generateMulti(Request $request)
    {
        $request->validate([
            'predict_for_month' => 'required|date',
        ]);

        try {
            DB::beginTransaction();

            // Get last available historical data (model is already trained!)
            // We just need the latest 6-12 months of data for prediction
            $predictMonth = Carbon::parse($request->predict_for_month);
            $targetMonth = $predictMonth->copy()->startOfMonth();

            // Get the most recent data available in database
            $latestData = HistoricalOccupancyData::orderBy('date', 'desc')->first();
            if (!$latestData) {
                return back()->withErrors([
                    'error' => 'No historical data available in database'
                ]);
            }

            $endDate = Carbon::parse($latestData->date)->endOfMonth();
            $startDate = $endDate->copy()->subMonths(11)->startOfMonth();

            $aggregatedData = $this->aggregationService->getAggregatedMonthlyData($startDate, $endDate);

            if ($aggregatedData->count() < 6) {
                return back()->withErrors([
                    'error' => 'Insufficient historical data. Need at least 6 months of data. Found: ' . $aggregatedData->count()
                ]);
            }

            // Project forward to include synthetic months up to the target month
            // This ensures predictions for different months get different feature values
            $projectedData = $this->projectHistoricalDataForward($aggregatedData, $targetMonth);

            // Check if predictions already exist for this month (multi-output)
            $existingPredictions = Prediction::where('model_type', 'multi')
                ->whereYear('predicted_for_date', $targetMonth->year)
                ->whereMonth('predicted_for_date', $targetMonth->month)
                ->get()
                ->keyBy('room_type_id');

            $isUpdate = $existingPredictions->isNotEmpty();

            // Prepare features using projected data
            $features = $this->featureService->prepareFeatures($projectedData);

            // Get room capacities
            $roomCapacities = $this->mlService->getRoomCapacities();

            // Make prediction
            $result = $this->mlService->predictMultiOutput($features, $roomCapacities);

            // Get champion model for confidence calculation
            $championModel = ModelVersion::getChampion('multi');
            $confidenceLevel = 100 - ($championModel?->mape ?? 15); // Use champion's MAPE or default 15%

            // Store or update predictions for each room type
            $predictions = [];
            foreach ($result['predictions'] as $roomCode => $prediction) {
                $roomType = RoomType::where('code', $roomCode)->first();

                if ($roomType) {
                    $predictionData = [
                        'room_type_id' => $roomType->id,
                        'prediction_date' => Carbon::now(),
                        'predicted_for_date' => $targetMonth,
                        'predicted_occupancy_rate' => $prediction['occupancy_rate'],
                        'predicted_rooms_occupied' => (int) round($prediction['rooms_sold']),
                        'predicted_revenue' => $prediction['rooms_sold'] * $roomType->base_price,
                        'confidence_level' => $confidenceLevel,
                        'model_type' => 'multi',
                        'model_version' => $championModel?->version ?? 'v1.0.0',
                    ];

                    // Update existing or create new
                    if (isset($existingPredictions[$roomType->id])) {
                        $existingPredictions[$roomType->id]->update($predictionData);
                        $pred = $existingPredictions[$roomType->id];
                    } else {
                        $pred = Prediction::create($predictionData);
                    }

                    $predictions[$roomCode] = $pred;
                }
            }

            DB::commit();

            Log::info('Multi-output predictions ' . ($isUpdate ? 'updated' : 'created'), [
                'month' => $predictMonth->format('Y-m'),
                'room_types' => array_keys($predictions),
                'action' => $isUpdate ? 'updated' : 'created',
            ]);

            $action = $isUpdate ? 'diperbarui' : 'dibuat';
            $summaryMessage = "Prediksi berhasil {$action} untuk {$predictMonth->format('F Y')}: ";
            foreach ($result['predictions'] as $roomCode => $pred) {
                $summaryMessage .= "{$roomCode}: {$pred['occupancy_rate']}%, ";
            }

            return redirect()->route('predictions.multi')->with('success', trim($summaryMessage, ', '));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Multi-output prediction failed', [
                'error' => $e->getMessage(),
                'month' => $request->predict_for_month
            ]);

            return back()->withErrors([
                'error' => 'Gagal membuat prediksi: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Delete all predictions for a specific month and model type
     */
    public function destroyMonth($year, $month, $type)
    {
        try {
            $targetDate = Carbon::create($year, $month, 1)->startOfMonth();

            $query = Prediction::where('model_type', $type)
                ->whereYear('predicted_for_date', $year)
                ->whereMonth('predicted_for_date', $month);

            $count = $query->count();
            $query->delete();

            Log::info('Predictions deleted', [
                'year' => $year,
                'month' => $month,
                'type' => $type,
                'count' => $count,
            ]);

            $monthName = $targetDate->format('F Y');
            $typeName = $type === 'single' ? 'Single-Output' : 'Multi-Output';

            return back()->with('success',
                "Berhasil menghapus {$count} prediksi {$typeName} untuk {$monthName}"
            );

        } catch (\Exception $e) {
            Log::error('Failed to delete predictions', [
                'error' => $e->getMessage(),
                'year' => $year,
                'month' => $month,
                'type' => $type,
            ]);

            return back()->withErrors([
                'error' => 'Gagal menghapus prediksi: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Enrich prediction with business insights for managers
     */
    private function enrichPredictionWithInsights($prediction, $roomCapacity, $modelType)
    {
        $occupancyRate = $prediction->predicted_occupancy_rate;
        $predictedDate = Carbon::parse($prediction->predicted_for_date);
        $daysInMonth = $predictedDate->daysInMonth;

        // Calculate average rooms occupied per day
        $avgRoomsPerDay = round($roomCapacity * ($occupancyRate / 100));

        // Calculate total room-nights for the month
        $totalRoomNights = $roomCapacity * $daysInMonth;
        $occupiedRoomNights = round($totalRoomNights * ($occupancyRate / 100));

        // Estimate revenue (using average room rate)
        $avgRoomRate = $modelType === 'single' ? 450000 : ($prediction->roomType ? $prediction->roomType->base_price : 450000);
        $estimatedRevenue = $occupiedRoomNights * $avgRoomRate;

        // Staffing recommendation based on occupancy
        $staffingLevel = $this->getStaffingRecommendation($occupancyRate, $avgRoomsPerDay);

        // Marketing action based on occupancy level
        $marketingAction = $this->getMarketingAction($occupancyRate);

        // Performance category
        $performanceCategory = $this->getPerformanceCategory($occupancyRate);

        // Add all insights to the prediction object
        $predictionArray = $prediction->toArray();
        $predictionArray['insights'] = [
            'avg_rooms_per_day' => $avgRoomsPerDay,
            'total_room_nights' => $totalRoomNights,
            'occupied_room_nights' => $occupiedRoomNights,
            'estimated_revenue' => $estimatedRevenue,
            'estimated_revenue_formatted' => 'Rp ' . number_format($estimatedRevenue, 0, ',', '.'),
            'staffing' => $staffingLevel,
            'marketing' => $marketingAction,
            'performance' => $performanceCategory,
            'interpretation' => $this->getInterpretation($occupancyRate, $avgRoomsPerDay, $roomCapacity, $modelType),
        ];

        return $predictionArray;
    }

    /**
     * Get staffing recommendation based on occupancy
     */
    private function getStaffingRecommendation($occupancyRate, $avgRoomsPerDay)
    {
        if ($occupancyRate >= 75) {
            return [
                'level' => 'Penuh',
                'description' => "Butuh semua staff (housekeeping untuk ~{$avgRoomsPerDay} kamar, full front desk & F&B)",
                'priority' => 'high',
            ];
        } elseif ($occupancyRate >= 50) {
            return [
                'level' => 'Sedang',
                'description' => "Staff standar (housekeeping untuk {$avgRoomsPerDay} kamar, shift normal)",
                'priority' => 'medium',
            ];
        } else {
            return [
                'level' => 'Minimal',
                'description' => "Staff minimal (housekeeping untuk {$avgRoomsPerDay} kamar, pertimbangkan part-time)",
                'priority' => 'low',
            ];
        }
    }

    /**
     * Get marketing action recommendation
     */
    private function getMarketingAction($occupancyRate)
    {
        if ($occupancyRate >= 80) {
            return [
                'action' => 'Pertahankan & Naikkan Harga',
                'description' => 'Okupansi tinggi - fokus pada kualitas layanan, pertimbangkan kenaikan harga 5-10%',
                'urgency' => 'low',
            ];
        } elseif ($occupancyRate >= 60) {
            return [
                'action' => 'Marketing Ringan',
                'description' => 'Okupansi baik - lakukan marketing rutin, tawarkan early bird discount (5-10%)',
                'urgency' => 'medium',
            ];
        } elseif ($occupancyRate >= 40) {
            return [
                'action' => 'Promosi Aktif',
                'description' => 'Okupansi sedang - aktifkan kampanye di OTA, tawarkan paket promo (diskon 15-20%)',
                'urgency' => 'high',
            ];
        } else {
            return [
                'action' => 'Promosi Agresif',
                'description' => 'Okupansi rendah - promo besar (diskon 25-30%), partnership corporate, event marketing',
                'urgency' => 'urgent',
            ];
        }
    }

    /**
     * Get performance category
     */
    private function getPerformanceCategory($occupancyRate)
    {
        if ($occupancyRate >= 80) {
            return ['level' => 'Sangat Baik', 'color' => 'green', 'icon' => '🎉'];
        } elseif ($occupancyRate >= 60) {
            return ['level' => 'Baik', 'color' => 'blue', 'icon' => '✅'];
        } elseif ($occupancyRate >= 40) {
            return ['level' => 'Sedang', 'color' => 'yellow', 'icon' => '⚠️'];
        } else {
            return ['level' => 'Perlu Perhatian', 'color' => 'red', 'icon' => '🚨'];
        }
    }

    /**
     * Get simple interpretation for managers
     */
    private function getInterpretation($occupancyRate, $avgRoomsPerDay, $totalRooms, $modelType)
    {
        $scope = $modelType === 'single' ? 'seluruh hotel' : 'tipe kamar ini';

        return "Prediksi menunjukkan okupansi {$scope} rata-rata {$occupancyRate}% per bulan. " .
               "Artinya sekitar {$avgRoomsPerDay} dari {$totalRooms} kamar akan terisi setiap harinya.";
    }

    /**
     * Get month-to-month comparison
     */
    private function getMonthComparisons($predictions, $modelType)
    {
        $comparisons = [];
        $predictionsByMonth = collect($predictions)->groupBy(function ($p) {
            return Carbon::parse($p['predicted_for_date'])->format('Y-m');
        });

        $months = $predictionsByMonth->keys()->sort()->values();

        for ($i = 1; $i < count($months); $i++) {
            $prevMonth = $months[$i - 1];
            $currentMonth = $months[$i];

            $prevData = $predictionsByMonth[$prevMonth];
            $currentData = $predictionsByMonth[$currentMonth];

            $prevOccupancy = collect($prevData)->avg('predicted_occupancy_rate');
            $currentOccupancy = collect($currentData)->avg('predicted_occupancy_rate');

            $change = $currentOccupancy - $prevOccupancy;
            $changePercent = $prevOccupancy > 0 ? ($change / $prevOccupancy) * 100 : 0;

            $comparisons[] = [
                'from_month' => Carbon::parse($prevMonth . '-01')->format('M Y'),
                'to_month' => Carbon::parse($currentMonth . '-01')->format('M Y'),
                'prev_occupancy' => round($prevOccupancy, 1),
                'current_occupancy' => round($currentOccupancy, 1),
                'change' => round($change, 1),
                'change_percent' => round($changePercent, 1),
                'trend' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'stable'),
                'interpretation' => $this->getComparisonInterpretation($change, $changePercent),
            ];
        }

        return $comparisons;
    }

    /**
     * Get comparison interpretation
     */
    private function getComparisonInterpretation($change, $changePercent)
    {
        if (abs($change) < 2) {
            return "Okupansi stabil dari bulan sebelumnya";
        } elseif ($change > 0) {
            if ($changePercent > 20) {
                return "Peningkatan signifikan! Pertahankan momentum ini";
            } else {
                return "Tren positif, okupansi meningkat " . abs(round($change, 1)) . "%";
            }
        } else {
            if ($changePercent < -20) {
                return "Penurunan drastis! Butuh aksi marketing segera";
            } else {
                return "Okupansi menurun " . abs(round($change, 1)) . "%, perlu promosi";
            }
        }
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

    /**
     * Project historical data forward to create synthetic months up to the target month.
     * This creates a sliding window effect where the last 6 months include synthetic data
     * with proper seasonality features.
     *
     * KEY FIX: This ensures predictions for different target months receive different inputs,
     * particularly the is_peak_season feature, which allows the LSTM to differentiate between
     * predicting for June (peak) vs April (low season).
     *
     * @param Collection $historicalData Collection of aggregated monthly data
     * @param Carbon $targetMonth The month we want to predict for
     * @return Collection Extended collection with synthetic months
     */
    private function projectHistoricalDataForward(Collection $historicalData, Carbon $targetMonth): Collection
    {
        // Sort ascending by date
        $sorted = $historicalData->sortBy('date');
        $lastRealMonth = $sorted->last();

        if (!$lastRealMonth || !isset($lastRealMonth->date)) {
            return $sorted; // Return as-is if no valid data
        }

        $lastDate = Carbon::parse($lastRealMonth->date);

        // Calculate how many months ahead we're predicting
        $monthsAhead = $lastDate->copy()->startOfMonth()->diffInMonths($targetMonth->copy()->startOfMonth());

        // If predicting for the immediate next month or past, no projection needed
        if ($monthsAhead <= 1) {
            return $sorted;
        }

        Log::debug("Projecting {$monthsAhead} synthetic months forward", [
            'last_real_date' => $lastDate->format('Y-m'),
            'target_month' => $targetMonth->format('Y-m'),
        ]);

        // Create synthetic months by projecting forward based on recent trend
        $projected = $sorted->toBase();

        for ($i = 1; $i < $monthsAhead; $i++) {
            $syntheticDate = $lastDate->copy()->addMonths($i)->startOfMonth();
            $syntheticMonth = $this->createSyntheticMonth($projected, $syntheticDate);
            $projected->push($syntheticMonth);

            Log::debug("Created synthetic month", [
                'date' => $syntheticDate->format('Y-m'),
                'occupancy_rate' => $syntheticMonth->occupancy_rate,
            ]);
        }

        // Return only the last 24 months (to maintain data quality for YoY calculations)
        return $projected->sortBy('date')->take(-24);
    }

    /**
     * Create a synthetic month by projecting from recent trends with seasonal adjustment.
     *
     * @param Collection $historicalData All historical data so far (including previously generated synthetic months)
     * @param Carbon $targetDate The date for the synthetic month
     * @return object Synthetic month data matching HistoricalOccupancyData structure
     */
    private function createSyntheticMonth(Collection $historicalData, Carbon $targetDate): object
    {
        // Get last 3 months for trend calculation
        $recent = $historicalData->sortBy('date')->take(-3);

        if ($recent->count() < 3) {
            $recent = $historicalData->sortBy('date')->take(-$historicalData->count());
        }

        // Calculate average values with a slight trend
        $avgOccupancy = $recent->avg('occupancy_rate') ?? 50.0;
        $avgRooms = $recent->avg('total_occupancy') ?? $recent->avg('kamar_terjual') ?? 28.0;

        // Apply seasonal adjustment based on target month
        $seasonalFactor = $this->getSeasonalFactor($targetDate->month);
        $adjustedOccupancy = min(100, max(0, $avgOccupancy * $seasonalFactor));
        $adjustedRooms = min(56, max(0, $avgRooms * $seasonalFactor));

        // Create synthetic data object matching HistoricalOccupancyData structure
        // IMPORTANT: These fields must match what FeatureEngineeringService expects
        return (object) [
            'date' => $targetDate,
            'occupancy_rate' => round($adjustedOccupancy, 2),
            'total_occupancy' => round($adjustedRooms, 2),
            'kamar_terjual' => round($adjustedRooms, 2),
            'kamar_std' => round($adjustedRooms * 0.35, 2), // Standard: 35%
            'kamar_spr' => round($adjustedRooms * 0.30, 2), // Superior: 30%
            'kamar_fmy' => round($adjustedRooms * 0.20, 2), // Family: 20%
            'kamar_js' => round($adjustedRooms * 0.15, 2),  // Junior Suite: 15%
        ];
    }

    /**
     * Get seasonal adjustment factor based on month.
     * This is crucial for differentiating predictions across different months.
     *
     * Peak season (Jun, Jul, Dec) gets boost, low season gets reduction.
     * This ensures that the is_peak_season feature in FeatureEngineeringService
     * will have different values when predicting for June vs April.
     *
     * @param int $month Month number (1-12)
     * @return float Adjustment factor (0.9 to 1.2)
     */
    private function getSeasonalFactor(int $month): float
    {
        // Peak season (June, July, December) - high demand periods
        if (in_array($month, [6, 7, 12])) {
            return 1.20; // 20% boost
        }

        // Shoulder season (May, August, November, January) - moderate demand
        if (in_array($month, [5, 8, 11, 1])) {
            return 1.05; // 5% boost
        }

        // Low season (Feb, Mar, Apr, Sep, Oct) - lower demand
        return 0.90; // 10% reduction
    }
}
