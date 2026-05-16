<?php

namespace App\Http\Controllers;

use App\Models\Prediction;
use App\Models\RoomType;
use App\Models\HistoricalOccupancyData;
use App\Models\ModelVersion;
use App\Services\MLPredictionService;
use App\Services\FeatureEngineeringService;
use App\Services\HistoricalDataAggregationService;
use App\Services\RecommendationService;
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
    protected RecommendationService $recommendationService;

    public function __construct(
        MLPredictionService $mlService,
        FeatureEngineeringService $featureService,
        HistoricalDataAggregationService $aggregationService,
        RecommendationService $recommendationService
    ) {
        $this->mlService = $mlService;
        $this->featureService = $featureService;
        $this->aggregationService = $aggregationService;
        $this->recommendationService = $recommendationService;
    }
    public function index(Request $request)
    {
        // Get all room types
        $roomTypes = RoomType::where('is_active', true)->get();

        // Get ALL predictions (including past months for demonstration/validation)
        $allPredictions = Prediction::with('roomType')
            ->orderBy('predicted_for_date', 'asc')
            ->orderBy('model_type')
            ->get()
            ->map(function ($p) {
                $arr = $p->toArray();
                $arr['raw_date'] = $p->getRawOriginal('predicted_for_date');
                return $arr;
            });

        // Group predictions by month for timeline view
        $predictionsByMonth = $allPredictions->groupBy(function ($prediction) {
            return Carbon::parse($prediction['raw_date'] ?? $prediction['predicted_for_date'])->format('Y-m');
        })->map(function ($monthPredictions, $monthKey) {
            $date = Carbon::createFromFormat('Y-m', $monthKey);
            $avgOccupancy = round($monthPredictions->avg(fn ($p) => $p['predicted_occupancy_rate'] ?? 0), 1);
            return [
                'month_key' => $monthKey,
                'month_label' => $date->translatedFormat('F Y'),
                'month_short' => $date->format('M Y'),
                'predictions' => $monthPredictions->values(),
                'avg_occupancy' => $avgOccupancy,
                'count' => $monthPredictions->count(),
            ];
        })->values();

        // Calculate quick stats
        $avgOccupancy = $allPredictions->count() > 0
            ? round($allPredictions->avg(fn ($p) => $p['predicted_occupancy_rate'] ?? 0), 1)
            : 0;
        $stats = [
            'total_predictions' => $allPredictions->count(),
            'avg_occupancy' => $avgOccupancy,
            'highest_month' => $predictionsByMonth->sortByDesc('avg_occupancy')->first(),
            'lowest_month' => $predictionsByMonth->sortBy('avg_occupancy')->first(),
            'single_count' => $allPredictions->where('model_type', 'single')->count(),
            'multi_count' => $allPredictions->where('model_type', 'multi')->count(),
        ];

        // Get REAL-TIME champion model information
        $singleChampion = $this->getChampionModelInfo('single');
        $multiChampion = $this->getChampionModelInfo('multi');

        $dbMaxDate = HistoricalOccupancyData::max('date') ?? now()->toDateString();

        return Inertia::render('Predictions/Index', [
            'roomTypes' => $roomTypes,
            'allPredictions' => $allPredictions,
            'predictionsByMonth' => $predictionsByMonth,
            'stats' => $stats,
            'singleModelInfo' => $singleChampion,
            'multiModelInfo' => $multiChampion,
            'dbMaxDate' => Carbon::parse($dbMaxDate)->format('Y-m-d'),
        ]);
    }

    private function calculateTrend($roomTypeId)
    {
        // Calculate actual trend by comparing historical data to predictions
        $lastMonth = Carbon::now()->subMonth();

        // Get historical average occupancy for this room type in the last month
        $historicalAvg = HistoricalOccupancyData::where('room_type_id', $roomTypeId)
            ->whereYear('date', $lastMonth->year)
            ->whereMonth('date', $lastMonth->month)
            ->avg('occupancy_rate');

        // Get predicted average for this room type in the next month
        $nextMonth = Carbon::now()->addMonth()->startOfMonth();
        $predictedAvg = Prediction::where('room_type_id', $roomTypeId)
            ->whereYear('predicted_for_date', $nextMonth->year)
            ->whereMonth('predicted_for_date', $nextMonth->month)
            ->avg('predicted_occupancy_rate');

        // Calculate percentage change
        if ($historicalAvg > 0 && $predictedAvg !== null) {
            return round((($predictedAvg - $historicalAvg) / $historicalAvg) * 100, 1);
        }

        return 0;
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
        $highOccupancyDays = $predictions->where('predicted_occupancy_rate', '>=', 55);
        if ($highOccupancyDays->count() > 0) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Okupansi Tinggi Terdeteksi',
                'description' => "Terdapat {$highOccupancyDays->count()} hari dengan prediksi okupansi di atas 55%. Pertimbangkan untuk menaikkan harga atau menyiapkan staf tambahan.",
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

            if ($avgOccupancy >= 55) {
                $recommendations[] = [
                    'type' => 'success',
                    'title' => "{$roomType->name} - Permintaan Tinggi",
                    'description' => "Okupansi rata-rata {$roomType->name} mencapai " . round($avgOccupancy, 1) . "%. Pertimbangkan untuk menaikkan harga atau menawarkan upgrade.",
                    'icon' => 'star',
                    'priority' => 'medium',
                ];
            } elseif ($avgOccupancy < 40) {
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

        // Get all predictions for display (including past months for demonstration)
        $recentPredictions = Prediction::where('model_type', 'single')
            ->orderBy('predicted_for_date', 'desc')
            ->limit(30)
            ->get()
            ->map(function ($prediction) use ($totalRooms) {
                return $this->enrichPredictionWithInsights($prediction, $totalRooms, 'single');
            });

        // Get month-to-month comparison
        $comparisons = $this->getMonthComparisons($recentPredictions, 'single');

        // Get REAL-TIME champion model info from database
        $modelInfo = $this->getChampionModelInfo('single');

        // dbMaxDate drives the "next predictable month" picker.
        // Use the latest already-predicted month so the picker starts AFTER it,
        // preventing duplicate predictions for months that already exist.
        $latestPredicted = Prediction::where('model_type', 'single')
            ->max(DB::raw("DATE(predicted_for_date)"));
        $histMax = HistoricalOccupancyData::max('date') ?? now()->toDateString();
        $dbMaxDate = $latestPredicted
            ? Carbon::parse($latestPredicted)->endOfMonth()->format('Y-m-d')
            : $histMax;

        return Inertia::render('Predictions/SingleOutput', [
            'roomTypes' => $roomTypes,
            'recentPredictions' => $recentPredictions,
            'comparisons' => $comparisons,
            'totalRooms' => $totalRooms,
            'modelInfo' => $modelInfo,
            'dbMaxDate' => Carbon::parse($dbMaxDate)->format('Y-m-d'),
        ]);
    }

    /**
     * Display multi-output prediction page
     */
    public function multiOutput(Request $request)
    {
        $roomTypes = RoomType::where('is_active', true)->get();

        // Get all predictions for display (including past months for demonstration)
        $recentPredictions = Prediction::where('model_type', 'multi')
            ->with('roomType')
            ->orderBy('predicted_for_date', 'desc')
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

        // Use latest predicted month (any room type) as the base for the picker
        $latestPredicted = Prediction::where('model_type', 'multi')
            ->max(DB::raw("DATE(predicted_for_date)"));
        $histMax = HistoricalOccupancyData::max('date') ?? now()->toDateString();
        $dbMaxDate = $latestPredicted
            ? Carbon::parse($latestPredicted)->endOfMonth()->format('Y-m-d')
            : $histMax;

        return Inertia::render('Predictions/MultiOutput', [
            'roomTypes' => $roomTypes,
            'recentPredictions' => $recentPredictions,
            'comparisons' => $comparisons,
            'modelInfo' => $modelInfo,
            'roomCapacities' => config('ml.room_capacities'),
            'dbMaxDate' => Carbon::parse($dbMaxDate)->format('Y-m-d'),
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

            // Calculate capacity-weighted average price for revenue estimation
            $roomTypes = RoomType::where('is_active', true)->get();
            $totalCapacity = $roomTypes->sum('total_rooms');
            $weightedAveragePrice = 0;
            if ($totalCapacity > 0) {
                foreach ($roomTypes as $rt) {
                    $weightedAveragePrice += ($rt->base_price * $rt->total_rooms);
                }
                $weightedAveragePrice = $weightedAveragePrice / $totalCapacity;
            } else {
                // Fallback: use average of all room type prices if no capacity defined
                $weightedAveragePrice = $roomTypes->avg('base_price') ?: 500000;
            }

            $daysInMonth = $targetMonth->daysInMonth;
            $predictionData = [
                'room_type_id' => null, // Single output doesn't have specific room type
                'prediction_date' => Carbon::now(),
                'predicted_for_date' => $targetMonth,
                'predicted_occupancy_rate' => $result['prediction']['occupancy_rate'],
                'predicted_rooms_occupied' => (int) round($result['prediction']['rooms_sold']),
                // Revenue = avg rooms/day * weighted price/night * days in month
                'predicted_revenue' => round($result['prediction']['rooms_sold'] * $weightedAveragePrice * $daysInMonth),
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
                ? "Prediksi berhasil diperbarui untuk {$predictMonth->translatedFormat('F Y')}: {$result['prediction']['occupancy_rate']}% okupansi ({$result['prediction']['rooms_sold']} kamar)"
                : "Prediksi berhasil dibuat untuk {$predictMonth->translatedFormat('F Y')}: {$result['prediction']['occupancy_rate']}% okupansi ({$result['prediction']['rooms_sold']} kamar)";

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
                    $daysInMonth = $targetMonth->daysInMonth;
                    $predictionData = [
                        'room_type_id' => $roomType->id,
                        'prediction_date' => Carbon::now(),
                        'predicted_for_date' => $targetMonth,
                        'predicted_occupancy_rate' => $prediction['occupancy_rate'],
                        'predicted_rooms_occupied' => (int) round($prediction['rooms_sold']),
                        // Revenue = avg rooms/day * price/night * days in month
                        'predicted_revenue' => round($prediction['rooms_sold'] * $roomType->base_price * $daysInMonth),
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
            $summaryMessage = "Prediksi berhasil {$action} untuk {$predictMonth->translatedFormat('F Y')}: ";
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

            $monthName = $targetDate->translatedFormat('F Y');
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
        $predictedDate = Carbon::parse($prediction->getRawOriginal('predicted_for_date'));
        $daysInMonth = $predictedDate->daysInMonth;

        // Average rooms occupied per day (use stored value, fall back to calculation)
        $avgRoomsPerDay = $prediction->predicted_rooms_occupied
            ?: (int) round($roomCapacity * ($occupancyRate / 100));

        // Total room-nights available and occupied this month
        $totalRoomNights   = $roomCapacity * $daysInMonth;
        $occupiedRoomNights = $avgRoomsPerDay * $daysInMonth;

        // Use stored predicted_revenue (already computed correctly as rooms/day * price * days)
        // Fall back to recalculation if null
        $estimatedRevenue = (float) $prediction->predicted_revenue;
        if ($estimatedRevenue <= 0) {
            $roomTypes = RoomType::where('is_active', true)->get();
            $totalCap  = $roomTypes->sum('total_rooms') ?: 1;
            $weightedPrice = $roomTypes->sum(fn ($rt) => $rt->base_price * $rt->total_rooms) / $totalCap;
            $pricePerNight = $modelType === 'single'
                ? $weightedPrice
                : ($prediction->roomType?->base_price ?? $weightedPrice);
            $estimatedRevenue = $avgRoomsPerDay * $pricePerNight * $daysInMonth;
        }

        // Staffing recommendation based on occupancy
        $staffingLevel = $this->getStaffingRecommendation($occupancyRate, $avgRoomsPerDay);

        // Marketing action based on occupancy level — pass room capacity for small-type logic
        $marketingAction = $this->getMarketingAction($occupancyRate, $roomCapacity);

        // Performance category — capacity-aware so JS/FMY get fair thresholds
        $performanceCategory = $this->getPerformanceCategory($occupancyRate, $roomCapacity);

        // Yield Management recommendation (2-Factor Rule-Based)
        // Ambil okupansi bulan sebelumnya dari DB (prediksi atau historis)
        $previousOccupancy = $this->getPreviousOccupancy($prediction, $modelType);
        $yieldRecommendation = $this->recommendationService->getRecommendation(
            (float) $occupancyRate,
            (float) $previousOccupancy
        );

        // Add all insights to the prediction object
        $predictionArray = $prediction->toArray();
        // Store raw DB date string (YYYY-MM-DD HH:MM:SS) before UTC cast mangles it
        $predictionArray['raw_date'] = $prediction->getRawOriginal('predicted_for_date');
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
            'yield_recommendation' => $yieldRecommendation,
        ];

        return $predictionArray;
    }

    /**
     * Ambil okupansi bulan sebelumnya untuk perhitungan tren rekomendasi.
     * Prioritas: prediksi bulan lalu → data historis bulan lalu → 0
     */
    private function getPreviousOccupancy($prediction, string $modelType): float
    {
        $predictedDate = Carbon::parse($prediction->getRawOriginal('predicted_for_date'));
        $previousMonth = $predictedDate->copy()->subMonth();

        // Coba ambil dari prediksi bulan sebelumnya (model type & room type sama)
        $prevPrediction = Prediction::where('model_type', $modelType)
            ->whereYear('predicted_for_date', $previousMonth->year)
            ->whereMonth('predicted_for_date', $previousMonth->month)
            ->when($prediction->room_type_id, fn ($q) => $q->where('room_type_id', $prediction->room_type_id))
            ->orderByDesc('created_at')
            ->first();

        if ($prevPrediction) {
            return (float) $prevPrediction->predicted_occupancy_rate;
        }

        // Fallback: data historis bulan lalu
        $historicalData = HistoricalOccupancyData::whereYear('date', $previousMonth->year)
            ->whereMonth('date', $previousMonth->month)
            ->when($prediction->room_type_id, fn ($q) => $q->where('room_type_id', $prediction->room_type_id))
            ->get();

        if ($historicalData->isNotEmpty()) {
            $roomTypes = \App\Models\RoomType::where('is_active', true)->get();
            $occupancyService = app(\App\Services\OccupancyCalculationService::class);
            return (float) $occupancyService->calculateWeightedOccupancy($historicalData, $roomTypes);
        }

        return 0.0;
    }

    /**
     * Get staffing recommendation based on actual rooms occupied per day.
     * Uses absolute room count as primary signal — more meaningful than % for operations.
     */
    private function getStaffingRecommendation($occupancyRate, $avgRoomsPerDay)
    {
        $roomText = $avgRoomsPerDay === 1 ? '1 kamar' : "~{$avgRoomsPerDay} kamar";

        if ($avgRoomsPerDay >= 30) {
            return [
                'level'       => 'Penuh',
                'description' => "Semua staff diperlukan — housekeeping penuh untuk {$roomText}, front desk & F&B standar penuh",
                'priority'    => 'high',
            ];
        } elseif ($avgRoomsPerDay >= 15) {
            return [
                'level'       => 'Sedang',
                'description' => "Staff standar mencukupi — housekeeping untuk {$roomText}, shift normal, front desk standar",
                'priority'    => 'medium',
            ];
        } elseif ($avgRoomsPerDay >= 5) {
            return [
                'level'       => 'Terbatas',
                'description' => "Staff terbatas cukup — housekeeping untuk {$roomText}, pertimbangkan shift lebih pendek atau gabung shift",
                'priority'    => 'low',
            ];
        } elseif ($avgRoomsPerDay >= 2) {
            return [
                'level'       => 'Minimal',
                'description' => "Staff minimal — housekeeping untuk {$roomText}, 1–2 staf paruh-waktu sudah mencukupi",
                'priority'    => 'low',
            ];
        } else {
            return [
                'level'       => 'Minimal',
                'description' => "Hanya {$roomText} terisi — 1 staf housekeeping paruh-waktu sudah cukup",
                'priority'    => 'low',
            ];
        }
    }

    /**
     * Get marketing action recommendation — scaled to actual room count.
     *
     * For very small room types (JS = 2 rooms) an occupancy of 40% = 0.8 rooms.
     * "Promosi Agresif" for a 2-room type is meaningless, so we use absolute room count
     * to override % thresholds when the type is tiny.
     */
    private function getMarketingAction($occupancyRate, $totalRooms = null)
    {
        $avgRooms = $totalRooms ? round($totalRooms * ($occupancyRate / 100)) : null;

        // For very small room types (≤4 rooms), occupancy % is misleading.
        // Use absolute-room thresholds instead when we have the capacity available.
        if ($totalRooms !== null && $totalRooms <= 4) {
            if ($avgRooms >= $totalRooms * 0.75) {
                return [
                    'action'      => 'Pertahankan & Naikkan Harga',
                    'description' => 'Permintaan tinggi untuk tipe ini — pertimbangkan kenaikan tarif dan pastikan kualitas layanan premium terjaga',
                    'urgency'     => 'low',
                ];
            } elseif ($avgRooms >= 1) {
                return [
                    'action'      => 'Marketing Selektif',
                    'description' => 'Tawarkan paket khusus untuk tipe kamar ini melalui OTA dan channel direct',
                    'urgency'     => 'medium',
                ];
            } else {
                return [
                    'action'      => 'Evaluasi Tipe Kamar',
                    'description' => 'Permintaan sangat rendah — pertimbangkan bundling paket atau promosi khusus. Evaluasi apakah perlu penyesuaian harga.',
                    'urgency'     => 'high',
                ];
            }
        }

        // Standard thresholds for larger room types (STD 32, SPR 19)
        if ($occupancyRate >= 55) {
            return [
                'action'      => 'Pertahankan & Naikkan Harga',
                'description' => 'Okupansi tinggi — fokus pada kualitas layanan, pertimbangkan kenaikan harga 5–10%',
                'urgency'     => 'low',
            ];
        } elseif ($occupancyRate >= 40) {
            return [
                'action'      => 'Promosi Aktif',
                'description' => 'Okupansi sedang — aktifkan kampanye OTA, tawarkan paket promo (diskon 15–20%)',
                'urgency'     => 'high',
            ];
        } else {
            return [
                'action'      => 'Promosi Agresif',
                'description' => 'Okupansi rendah — promo besar (diskon 25–30%), partnership corporate, event marketing',
                'urgency'     => 'urgent',
            ];
        }
    }

    /**
     * Get performance category — capacity-aware thresholds.
     *
     * Small room types (JS=2, FMY=3) have high occupancy variance — even 1 room filled
     * is meaningful. We use looser thresholds so they aren't penalised unfairly.
     *
     * Thresholds by capacity:
     *   ≤4  rooms (JS=2, FMY=3): Sangat Baik ≥75%, Baik ≥50%, Sedang ≥25%, Perlu Perhatian <25%
     *   5–15 rooms (medium):    Sangat Baik ≥75%, Baik ≥55%, Sedang ≥35%, Perlu Perhatian <35%
     *   >15 rooms (STD=32, SPR=19): Sangat Baik ≥55%, Baik ≥40%, Perlu Perhatian <40%
     */
    private function getPerformanceCategory($occupancyRate, $roomCapacity = null)
    {
        // Determine threshold tier by capacity
        if ($roomCapacity !== null && $roomCapacity <= 4) {
            // Tiny types — 1 room filled out of 2 = 50%, already decent
            if ($occupancyRate >= 75) return ['level' => 'Sangat Baik', 'color' => 'primary'];
            if ($occupancyRate >= 50) return ['level' => 'Baik',        'color' => 'primary-light'];
            if ($occupancyRate >= 25) return ['level' => 'Sedang',      'color' => 'yellow'];
            return ['level' => 'Perlu Perhatian', 'color' => 'red'];
        }

        if ($roomCapacity !== null && $roomCapacity <= 15) {
            // Medium types
            if ($occupancyRate >= 75) return ['level' => 'Sangat Baik', 'color' => 'primary'];
            if ($occupancyRate >= 55) return ['level' => 'Baik',        'color' => 'primary-light'];
            if ($occupancyRate >= 35) return ['level' => 'Sedang',      'color' => 'yellow'];
            return ['level' => 'Perlu Perhatian', 'color' => 'red'];
        }

        // Large types (STD 32, SPR 19) or single-output (56 total)
        if ($occupancyRate >= 55) return ['level' => 'Sangat Baik', 'color' => 'primary'];
        if ($occupancyRate >= 40) return ['level' => 'Baik',        'color' => 'primary-light'];
        return ['level' => 'Perlu Perhatian', 'color' => 'red'];
    }

    /**
     * Get contextual interpretation for managers — personalised per room type scale.
     */
    private function getInterpretation($occupancyRate, $avgRoomsPerDay, $totalRooms, $modelType)
    {
        if ($modelType === 'single') {
            return "Prediksi menunjukkan rata-rata {$occupancyRate}% kamar hotel terisi setiap harinya. " .
                   "Dari total {$totalRooms} kamar, sekitar {$avgRoomsPerDay} kamar rata-rata akan terisi per hari.";
        }

        // Multi-output: personalise based on room type scale
        if ($totalRooms <= 4) {
            // Tiny type — use absolute room count as primary signal
            $filledText = $avgRoomsPerDay >= 1
                ? "sekitar {$avgRoomsPerDay} dari {$totalRooms} kamar terisi"
                : "kurang dari 1 kamar terisi rata-rata";
            return "Prediksi menunjukkan okupansi tipe ini rata-rata {$occupancyRate}% per bulan. " .
                   "Artinya {$filledText} setiap harinya. " .
                   "Dengan hanya {$totalRooms} kamar, setiap tamu sangat berdampak pada persentase ini.";
        }

        return "Prediksi menunjukkan okupansi tipe kamar ini rata-rata {$occupancyRate}% per bulan. " .
               "Dari {$totalRooms} kamar, sekitar {$avgRoomsPerDay} kamar rata-rata akan terisi setiap harinya.";
    }

    /**
     * Get month-to-month comparison
     */
    private function getMonthComparisons($predictions, $modelType)
    {
        $comparisons = [];
        $predictionsByMonth = collect($predictions)->groupBy(function ($p) {
            // Use the raw_date field (YYYY-MM-DD from DB) to avoid UTC cast shifting
            // midnight WIB dates to the previous month when serialized as ISO UTC.
            $raw = $p['raw_date'] ?? $p['predicted_for_date'];
            return substr($raw, 0, 7);
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
                'from_month' => Carbon::createFromFormat('Y-m', $prevMonth)->translatedFormat('M Y'),
                'to_month' => Carbon::createFromFormat('Y-m', $currentMonth)->translatedFormat('M Y'),
                'prev_occupancy' => round($prevOccupancy, 1),
                'current_occupancy' => round($currentOccupancy, 1),
                'change' => round($change, 1),
                'change_percent' => round($changePercent, 1),
                'trend' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'stable'),
                'interpretation' => $this->getComparisonInterpretation($change, $changePercent),
            ];
        }

        // Only return the most recent comparison (latest pair of months)
        // Showing all historical pairs in a "current trend" card is misleading.
        return $comparisons ? [end($comparisons)] : [];
    }

    /**
     * Get comparison interpretation
     * Uses absolute change (percentage points) as primary trigger — more meaningful
     * for occupancy data where relative % can be misleading at low base values.
     */
    private function getComparisonInterpretation($change, $changePercent)
    {
        $abs = abs($change);

        if ($abs < 2) {
            return "Okupansi relatif stabil antar bulan";
        } elseif ($change > 0) {
            if ($abs >= 15) {
                return "Kenaikan besar +" . round($abs, 1) . " poin — pertahankan momentum & pertimbangkan harga premium";
            } elseif ($abs >= 8) {
                return "Tren positif, okupansi naik " . round($abs, 1) . " poin — operasional normal";
            } else {
                return "Sedikit meningkat +" . round($abs, 1) . " poin dari bulan sebelumnya";
            }
        } else {
            if ($abs >= 15) {
                return "Penurunan besar -" . round($abs, 1) . " poin — segera jalankan promosi & diskon";
            } elseif ($abs >= 8) {
                return "Okupansi turun " . round($abs, 1) . " poin — pertimbangkan strategi marketing";
            } else {
                return "Sedikit menurun -" . round($abs, 1) . " poin dari bulan sebelumnya";
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
            'mape' => $champion->mape !== null ? round($champion->mape, 2) : null,
            'r2_score' => $champion->r2_score !== null ? round($champion->r2_score, 4) : null,
            'rmse' => $champion->rmse !== null ? round($champion->rmse, 4) : null,
            'trained_at' => $champion->trained_at ? Carbon::parse($champion->trained_at)->format('Y-m-d H:i') : $champion->created_at->format('Y-m-d H:i'),
            'model_path' => $champion->model_path,
            'status' => 'Active Champion',
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
     * @param \Illuminate\Support\Collection $historicalData Collection of aggregated monthly data
     * @param Carbon $targetMonth The month we want to predict for
     * @return \Illuminate\Support\Collection Extended collection with synthetic months
     */
    private function projectHistoricalDataForward(\Illuminate\Support\Collection $historicalData, Carbon $targetMonth): \Illuminate\Support\Collection
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

        // Skip projection for immediate next month (already has enough recent data)
        // For 2+ months ahead, use synthetic projection to vary inputs
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
     * @param \Illuminate\Support\Collection $historicalData All historical data so far (including previously generated synthetic months)
     * @param Carbon $targetDate The date for the synthetic month
     * @return object Synthetic month data matching HistoricalOccupancyData structure
     */
    private function createSyntheticMonth(\Illuminate\Support\Collection $historicalData, Carbon $targetDate): object
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
        // Use actual hotel room distribution: STD=32, SPR=19, JS=2, FMY=3 (total=56)
        $roomCapacities = config('ml.room_capacities', [
            'STD' => 32,
            'SPR' => 19,
            'JS' => 2,
            'FMY' => 3,
        ]);
        $totalCapacity = array_sum($roomCapacities);

        return (object) [
            'date' => $targetDate,
            'occupancy_rate' => round($adjustedOccupancy, 2),
            'total_occupancy' => round($adjustedRooms, 2),
            'kamar_terjual' => round($adjustedRooms, 2),
            'kamar_std' => round($adjustedRooms * ($roomCapacities['STD'] / $totalCapacity), 2),
            'kamar_spr' => round($adjustedRooms * ($roomCapacities['SPR'] / $totalCapacity), 2),
            'kamar_fmy' => round($adjustedRooms * ($roomCapacities['FMY'] / $totalCapacity), 2),
            'kamar_js' => round($adjustedRooms * ($roomCapacities['JS'] / $totalCapacity), 2),
        ];
    }

    /**
     * Get seasonal adjustment factor based on month.
     * This is crucial for differentiating predictions across different months.
     *
     * Peak season gets boost, low season gets reduction.
     * This ensures that the is_peak_season feature in FeatureEngineeringService
     * will have different values when predicting for June vs April.
     *
     * @param int $month Month number (1-12)
     * @return float Adjustment factor (0.9 to 1.2)
     */
    private function getSeasonalFactor(int $month): float
    {
        // Get peak months from configuration (allows customization per hotel)
        $peakMonths = config('ml.peak_months', [6, 7, 12]);

        // Peak season - high demand periods
        if (in_array($month, $peakMonths)) {
            return 1.20; // 20% boost
        }

        // Calculate shoulder months (one month before/after peak)
        $shoulderMonths = [];
        foreach ($peakMonths as $peak) {
            $before = $peak - 1;
            $after = $peak + 1;
            if ($before == 0) $before = 12;
            if ($after == 13) $after = 1;
            $shoulderMonths[] = $before;
            $shoulderMonths[] = $after;
        }
        $shoulderMonths = array_unique($shoulderMonths);

        // Shoulder season - moderate demand
        if (in_array($month, $shoulderMonths)) {
            return 1.05; // 5% boost
        }

        // Low season - lower demand
        return 0.90; // 10% reduction
    }
}
