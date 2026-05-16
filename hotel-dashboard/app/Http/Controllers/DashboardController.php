<?php

namespace App\Http\Controllers;

use App\Models\Prediction;
use App\Models\RoomType;
use App\Models\HistoricalOccupancyData;
use App\Models\ModelVersion;
use App\Services\RetrainingScheduler;
use App\Services\OccupancyCalculationService;
use App\Services\RecommendationService;
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

        // Build date filters — default = 3 bulan terakhir data yang tersedia
        $dbMinDate = HistoricalOccupancyData::min('date') ?? '2021-01-01';
        $dbMaxDate = HistoricalOccupancyData::max('date') ?? now()->toDateString();

        // Default range: last 3 months of available data (not last 3 months from today)
        $defaultStart = Carbon::parse($dbMaxDate)->subMonths(2)->startOfMonth()->toDateString();
        $dateStart = Carbon::parse($request->input('date_start', $defaultStart))->startOfMonth();
        $dateEnd   = Carbon::parse($request->input('date_end',   $dbMaxDate))->endOfMonth();

        // Cap date_end to the last date actually in DB
        $maxHistoricalDate = Carbon::parse($dbMaxDate)->endOfMonth();
        if ($dateEnd->gt($maxHistoricalDate)) {
            $dateEnd = $maxHistoricalDate;
        }

        $roomTypeFilter = $request->input('room_types', null);

        // Get HISTORICAL data for the selected period
        $historicalQuery = HistoricalOccupancyData::with('roomType')
            ->whereBetween('date', [$dateStart, $dateEnd]);

        if ($roomTypeFilter) {
            $historicalQuery->whereIn('room_type_id', $roomTypeFilter);
        }

        $historicalData = $historicalQuery->orderBy('date')->get();

        // Get latest predictions — find the most recent prediction date and fetch up to 6 months around it.
        // Do NOT filter by dbMaxDate: predictions may be for months that now fall inside historical data.
        $latestPredDate = Prediction::max('predicted_for_date');
        if ($latestPredDate) {
            $predictionEnd   = Carbon::parse($latestPredDate)->endOfMonth();
            $predictionStart = $predictionEnd->copy()->subMonths(5)->startOfMonth();
        } else {
            $predictionStart = Carbon::parse($dbMaxDate)->addDay()->startOfMonth();
            $predictionEnd   = $predictionStart->copy()->addMonths(6)->endOfMonth();
        }

        $allPredictions = Prediction::with('roomType')
            ->where('predicted_for_date', '>=', $predictionStart)
            ->where('predicted_for_date', '<=', $predictionEnd)
            ->orderBy('predicted_for_date')
            ->get();

        $multiPredictions  = $allPredictions->where('model_type', 'multi');
        $singlePredictions = $allPredictions->where('model_type', 'single');

        // Calculate KPIs from HISTORICAL data in selected period
        $occupancyService = app(OccupancyCalculationService::class);
        $totalCapacity = $roomTypes->sum('total_rooms');

        // Calculate stats from selected historical period
        if ($historicalData->isNotEmpty()) {
            // Use capacity-weighted average (not raw avg which treats all room types equally)
            $avgOccupancy = $occupancyService->calculateWeightedOccupancy($historicalData, $roomTypes);
            // Exclude revenue outlier rows (data-entry errors e.g. Rp 81B, Rp 18B)
            $actualRevenue = $historicalData->filter(fn ($r) => ($r->revenue ?? 0) <= 50_000_000)->sum('revenue');
        } else {
            $avgOccupancy = 0;
            $actualRevenue = 0;
        }

        // Get the most recent complete month for trend comparison
        $lastHistoricalMonth = Carbon::parse($dbMaxDate)->startOfMonth();
        $lastMonthData = $historicalData->filter(fn ($r) =>
            Carbon::parse($r->date)->format('Y-m') === $lastHistoricalMonth->format('Y-m')
        );

        // Calculate year-over-year trends (comparing same month previous year)
        $previousYearStart = $dateStart->copy()->subYear();
        $previousYearEnd = $dateEnd->copy()->subYear();
        $previousYearData = HistoricalOccupancyData::whereBetween('date', [$previousYearStart, $previousYearEnd])->get();

        $previousYearRevenue = $previousYearData->filter(fn ($r) => ($r->revenue ?? 0) <= 50_000_000)->sum('revenue');
        $previousYearOccupancy = $previousYearData->isNotEmpty()
            ? $occupancyService->calculateWeightedOccupancy($previousYearData, $roomTypes)
            : 0;

        $revenueTrend = $previousYearRevenue > 0 && $actualRevenue > 0
            ? round((($actualRevenue - $previousYearRevenue) / $previousYearRevenue) * 100, 1)
            : 0;

        $occupancyTrend = $previousYearOccupancy > 0 && $avgOccupancy > 0
            ? round((($avgOccupancy - $previousYearOccupancy) / $previousYearOccupancy) * 100, 1)
            : 0;

        // Prepare chart data — always uses FULL historical range for the trend chart (not filtered period)
        $allHistoricalData = HistoricalOccupancyData::with('roomType')
            ->whereBetween('date', [Carbon::parse($dbMinDate)->startOfMonth(), Carbon::parse($dbMaxDate)->endOfMonth()])
            ->orderBy('date')
            ->get();
        $chartData = $this->prepareMonthlyChartData($multiPredictions, $singlePredictions, $roomTypes, $allHistoricalData);

        $chartDateRange = [
            'start' => Carbon::parse($dbMinDate)->format('M Y'),
            'end'   => Carbon::parse($dbMaxDate)->format('M Y'),
        ];

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
        // Akurasi = 100 - MAPE, dibulatkan 1 desimal
        $singleAccuracy = $singleChampion['mape'] !== null
            ? round(100 - $singleChampion['mape'], 1)
            : null;
        $multiAccuracy = $multiChampion['mape'] !== null
            ? round(100 - $multiChampion['mape'], 1)
            : null;

        // Format tanggal update model (ganti versi teknis dengan tanggal)
        $singleUpdatedAt = $singleChampion['trained_at']
            ? Carbon::parse($singleChampion['trained_at'])->format('d F Y')
            : null;
        $multiUpdatedAt = $multiChampion['trained_at']
            ? Carbon::parse($multiChampion['trained_at'])->format('d F Y')
            : null;

        $modelComparison = [
            'single' => [
                'name' => 'Prediksi Total Hotel',
                'predictions' => $singlePredictions->map(function ($pred) {
                    return [
                        'month' => Carbon::parse($pred->predicted_for_date)->format('M Y'),
                        'occupancy' => $pred->predicted_occupancy_rate ?? 0,
                        'rooms' => $pred->predicted_rooms_occupied ?? 0,
                        'confidence' => $pred->confidence_level ?? 0,
                    ];
                })->values(),
                'avg_confidence' => round($singlePredictions->avg('confidence_level') ?? 0, 1),
                'accuracy' => $singleAccuracy,
                'updated_at' => $singleUpdatedAt,
            ],
            'multi' => [
                'name' => 'Prediksi Per Tipe Kamar',
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
                'accuracy' => $multiAccuracy,
                'updated_at' => $multiUpdatedAt,
            ],
        ];

        // Generate alerts/recommendations
        $alerts = $this->generateAlerts($multiPredictions, $singlePredictions, $avgOccupancy);

        // Generate active recommendation for dashboard (next month)
        $activeRecommendation = $this->generateActiveRecommendation($singlePredictions, $multiPredictions);

        // Get retraining status (6-month cycle tracking)
        $retrainingScheduler = app(RetrainingScheduler::class);
        $retrainingStatus = $retrainingScheduler->getAllModelsStatus();

        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'avgOccupancy' => round($avgOccupancy, 1),
                'actualRevenue' => $actualRevenue,
                'totalRooms' => $roomTypes->sum('total_rooms'),
                'occupancyTrend' => $occupancyTrend,
                'revenueTrend' => $revenueTrend,
                'historicalCount' => $historicalData->count(),
            ],
            'chartData' => $chartData,
            'roomBreakdown' => $roomBreakdown,
            'monthlyPredictions' => $monthlyPredictions,
            'modelComparison' => $modelComparison,
            'alerts' => $alerts,
            'activeRecommendation' => $activeRecommendation,
            'retrainingStatus' => $retrainingStatus,
            'lastUpdated' => $multiPredictions->max('predicted_for_date'),
            'chartDateRange' => $chartDateRange,
            'roomTypes' => $roomTypes,
            'filters' => [
                'date_start'  => $dateStart->format('Y-m-d'),
                'date_end'    => $dateEnd->format('Y-m-d'),
                'room_types'  => $roomTypeFilter,
                'db_min_date' => Carbon::parse($dbMinDate)->format('Y-m-d'),
                'db_max_date' => Carbon::parse($dbMaxDate)->format('Y-m-d'),
            ],
        ]);
    }

    private function prepareMonthlyChartData($multiPredictions, $singlePredictions, $roomTypes = null, $historicalData = null)
    {
        // Reuse passed-in data to avoid duplicate queries
        if (!$historicalData) {
            $dbMinDate = HistoricalOccupancyData::min('date') ?? '2021-01-01';
            $dbMaxDate = HistoricalOccupancyData::max('date') ?? now()->toDateString();
            $historicalData = HistoricalOccupancyData::with('roomType')
                ->whereBetween('date', [Carbon::parse($dbMinDate)->startOfMonth(), Carbon::parse($dbMaxDate)->endOfMonth()])
                ->get();
        }

        if (!$roomTypes) {
            $roomTypes = RoomType::where('is_active', true)->get();
        }

        $occupancyService = app(OccupancyCalculationService::class);

        // Group historical by month with capacity-weighted occupancy
        $historical = $historicalData
            ->groupBy(function ($item) {
                return Carbon::parse($item->date)->format('Y-m');
            })
            ->map(function ($monthData, $month) use ($roomTypes, $occupancyService) {
                $weightedOccupancy = $occupancyService->calculateWeightedOccupancy($monthData, $roomTypes);

                return [
                    'date' => Carbon::parse($month . '-01')->format('Y-m-d'),
                    'occupancy' => round($weightedOccupancy, 1),
                    'revenue' => $monthData->filter(fn ($r) => ($r->revenue ?? 0) <= 50_000_000)->sum('revenue'),
                ];
            })
            ->sortBy('date')
            ->values();

        // Prepare Multi-Output prediction data with capacity-weighted occupancy
        $predicted = $multiPredictions
            ->groupBy(function ($pred) {
                return Carbon::parse($pred->predicted_for_date)->format('Y-m');
            })
            ->map(function ($monthPreds, $month) use ($roomTypes, $occupancyService) {
                $weightedOccupancy = $occupancyService->calculateWeightedOccupancy($monthPreds, $roomTypes);

                return [
                    'date' => Carbon::parse($month . '-01')->format('Y-m-d'),
                    'occupancy' => round($weightedOccupancy, 1),
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
                'title' => 'Prediksi Belum Diperbarui',
                'message' => 'Prediksi belum diperbarui. Silakan buat prediksi terbaru agar informasi tetap akurat.',
                'action' => 'Buat Prediksi',
                'priority' => 'high',
            ];
        }

        // High occupancy alert (>=55%)
        $highOccupancy = $multiPredictions->filter(fn($p) => $p->predicted_occupancy_rate >= 55);
        if ($highOccupancy->count() > 0) {
            $alerts[] = [
                'type' => 'success',
                'title' => 'Permintaan Kamar Diprediksi Tinggi',
                'message' => 'Permintaan kamar diprediksi tinggi bulan depan. Pertimbangkan penyesuaian harga untuk memaksimalkan pendapatan.',
                'action' => 'Lihat Detail',
                'priority' => 'medium',
            ];
        }

        // Low occupancy alert (<40%)
        $lowOccupancy = $multiPredictions->filter(fn($p) => $p->predicted_occupancy_rate < 40);
        if ($lowOccupancy->count() > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Tingkat Hunian Diprediksi Rendah',
                'message' => 'Tingkat hunian diprediksi rendah. Pertimbangkan program promosi atau paket khusus untuk menarik lebih banyak tamu.',
                'action' => 'Rencanakan Promosi',
                'priority' => 'high',
            ];
        }

        // No predictions alert
        if ($multiPredictions->isEmpty() && $singlePredictions->isEmpty()) {
            $alerts[] = [
                'type' => 'error',
                'title' => 'Belum Ada Prediksi',
                'message' => 'Belum ada prediksi tersedia. Buat prediksi untuk mendapatkan rekomendasi strategi.',
                'action' => 'Buat Prediksi Sekarang',
                'priority' => 'high',
            ];
        }

        return $alerts;
    }

    private function prepareRoomBreakdown($roomTypes, $predictions)
    {
        // Tampilkan bulan terdekat yang akan datang, atau jika semua sudah lewat, bulan terbaru yang ada
        $currentYearMonth = Carbon::now()->format('Y-m');
        $upcomingPredictions = $predictions->filter(fn($p) =>
            Carbon::parse($p->predicted_for_date)->format('Y-m') >= $currentYearMonth
        );

        if ($upcomingPredictions->isNotEmpty()) {
            // Ada prediksi masa depan — ambil yang paling dekat
            $nearestMonth = $upcomingPredictions->sortBy('predicted_for_date')->first()?->predicted_for_date;
        } else {
            // Semua prediksi sudah lewat — tampilkan yang paling terbaru
            $nearestMonth = $predictions->sortByDesc('predicted_for_date')->first()?->predicted_for_date;
        }

        if ($nearestMonth) {
            $nearestYearMonth = Carbon::parse($nearestMonth)->format('Y-m');
            $predictions = $predictions->filter(fn($p) =>
                Carbon::parse($p->predicted_for_date)->format('Y-m') === $nearestYearMonth
            );
        }

        return $roomTypes->map(function ($roomType) use ($predictions) {
            $roomPredictions = $predictions->where('room_type_id', $roomType->id);
            $pred = $roomPredictions->first();
            $occupancy = $pred ? (float) $pred->predicted_occupancy_rate : 0;
            $predictedOccupied = $pred
                ? (int) ($pred->predicted_rooms_occupied ?? round($roomType->total_rooms * ($occupancy / 100)))
                : 0;

            return [
                'id'                => $roomType->id,
                'name'              => $roomType->name ?? 'Unknown',
                'code'              => $roomType->code ?? 'N/A',
                'capacity'          => $roomType->total_rooms ?? 0,
                'predicted_occupied'=> $predictedOccupied,
                'occupancy_rate'    => round($occupancy, 1),
                'base_price'        => $roomType->base_price ?? 0,
                'status'            => $occupancy >= 55 ? 'high' : ($occupancy >= 40 ? 'medium' : 'low'),
            ];
        });
    }

    /**
     * Hasilkan rekomendasi aktif untuk bulan depan (ditampilkan di dashboard utama).
     * Menggunakan single-output prediction sebagai acuan utama.
     */
    private function generateActiveRecommendation($singlePredictions, $multiPredictions): ?array
    {
        // Gunakan bulan terdekat yang upcoming (>= bulan ini) dari multi predictions
        $currentYearMonth = Carbon::now()->format('Y-m');

        $upcomingMulti = $multiPredictions->filter(fn($p) =>
            Carbon::parse($p->predicted_for_date)->format('Y-m') >= $currentYearMonth
        );
        $upcomingSingle = $singlePredictions->filter(fn($p) =>
            Carbon::parse($p->predicted_for_date)->format('Y-m') >= $currentYearMonth
        );

        $nearestMulti = $upcomingMulti->isNotEmpty()
            ? $upcomingMulti->sortBy('predicted_for_date')->first()
            : $multiPredictions->sortByDesc('predicted_for_date')->first();

        $nearestSingle = $upcomingSingle->isNotEmpty()
            ? $upcomingSingle->sortBy('predicted_for_date')->first()
            : $singlePredictions->sortByDesc('predicted_for_date')->first();

        // Tentukan bulan acuan: prioritaskan multi, fallback ke single
        if ($nearestMulti) {
            $nearestMonth = Carbon::parse($nearestMulti->predicted_for_date)->format('Y-m');
        } elseif ($nearestSingle) {
            $nearestMonth = Carbon::parse($nearestSingle->predicted_for_date)->format('Y-m');
        } else {
            return null;
        }

        // Hitung rata-rata hunian dari multi predictions bulan terdekat
        $monthMultiPreds = $multiPredictions->filter(
            fn($p) => Carbon::parse($p->predicted_for_date)->format('Y-m') === $nearestMonth
        );

        if ($monthMultiPreds->isNotEmpty()) {
            $occupancyService = app(\App\Services\OccupancyCalculationService::class);
            $roomTypes = RoomType::where('is_active', true)->get();
            $currentOcc = (float) $occupancyService->calculateWeightedOccupancy($monthMultiPreds, $roomTypes);
            $referenceDate = $nearestMulti->predicted_for_date;
        } elseif ($nearestSingle) {
            $currentOcc = (float) $nearestSingle->predicted_occupancy_rate;
            $referenceDate = $nearestSingle->predicted_for_date;
        } else {
            return null;
        }

        // Cari bulan sebelumnya untuk menentukan tren
        $predMonth = Carbon::parse($referenceDate)->startOfMonth();
        $prevMonth = $predMonth->copy()->subMonth();

        $prevMultiPreds = $multiPredictions->filter(
            fn($p) => Carbon::parse($p->predicted_for_date)->format('Y-m') === $prevMonth->format('Y-m')
        );

        if ($prevMultiPreds->isNotEmpty()) {
            $occupancyService = $occupancyService ?? app(\App\Services\OccupancyCalculationService::class);
            $roomTypes = $roomTypes ?? RoomType::where('is_active', true)->get();
            $previousOcc = (float) $occupancyService->calculateWeightedOccupancy($prevMultiPreds, $roomTypes);
        } else {
            $prevSingle = $singlePredictions->filter(
                fn($p) => Carbon::parse($p->predicted_for_date)->format('Y-m') === $prevMonth->format('Y-m')
            )->first();

            if ($prevSingle) {
                $previousOcc = (float) $prevSingle->predicted_occupancy_rate;
            } else {
                $historicalPrevData = HistoricalOccupancyData::with('roomType')
                    ->whereYear('date', $prevMonth->year)
                    ->whereMonth('date', $prevMonth->month)
                    ->get();
                $occupancyService = $occupancyService ?? app(\App\Services\OccupancyCalculationService::class);
                $roomTypes = $roomTypes ?? RoomType::where('is_active', true)->get();
                $previousOcc = $historicalPrevData->isNotEmpty()
                    ? (float) $occupancyService->calculateWeightedOccupancy($historicalPrevData, $roomTypes)
                    : 0.0;
            }
        }

        $recommendationService = app(RecommendationService::class);
        $recommendation = $recommendationService->getRecommendation($currentOcc, $previousOcc);

        return array_merge($recommendation, [
            'for_month'      => Carbon::parse($referenceDate)->isoFormat('MMMM YYYY'),
            'occupancy_rate' => round($currentOcc, 1),
        ]);
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
                'accuracy' => null,
                'trained_at' => null,
                'status' => 'Belum ada model aktif',
            ];
        }

        $mape = $champion->mape !== null ? round($champion->mape, 2) : null;
        $accuracy = $mape !== null ? round(100 - $mape, 1) : null;
        $trainedAt = $champion->trained_at
            ? Carbon::parse($champion->trained_at)->format('Y-m-d H:i')
            : $champion->created_at->format('Y-m-d H:i');

        return [
            'version' => $champion->version,
            'mape' => $mape,
            'accuracy' => $accuracy,
            'trained_at' => $trainedAt,
            'updated_label' => 'Diperbarui: ' . Carbon::parse($trainedAt)->format('d F Y'),
            'status' => 'Model Aktif',
        ];
    }
}
