<?php

namespace App\Http\Controllers;

use App\Models\HistoricalOccupancyData;
use App\Models\RoomType;
use App\Services\OccupancyCalculationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        // Get filter parameters
        $roomTypeFilter = $request->get('room_type', 'all');
        // years_back: how many years of history to show in the trend charts (default 2)
        $yearsBack = (int) $request->get('years_back', 2);

        // Year-comparison filter (default: 2 most recent years with data)
        $availableYears = $this->getAvailableYears();
        $defaultYear1   = $availableYears[0] ?? now()->year;
        $defaultYear2   = $availableYears[1] ?? ($defaultYear1 - 1);
        $year1 = (int) $request->get('year1', $defaultYear1);
        $year2 = (int) $request->get('year2', $defaultYear2);

        // Get all room types for filter dropdown
        $roomTypes = RoomType::where('is_active', true)->get();

        // Determine the actual max date in DB
        $maxHistoricalDate = HistoricalOccupancyData::max('date') ?? Carbon::today()->toDateString();
        $chartEnd   = Carbon::parse($maxHistoricalDate)->endOfMonth();
        $chartStart = $chartEnd->copy()->subYears($yearsBack)->startOfMonth();

        // Build historical data query for the selected range
        $historicalQuery = HistoricalOccupancyData::with('roomType')
            ->where('date', '>=', $chartStart->toDateString())
            ->where('date', '<=', $chartEnd->toDateString());

        // Apply room type filter
        if ($roomTypeFilter !== 'all') {
            $historicalQuery->where('room_type_id', $roomTypeFilter);
        }

        $historicalData = $historicalQuery->orderBy('date', 'desc')
            ->orderBy('room_type_id')
            ->get();

        // Calculate overall statistics with capacity-weighted average occupancy using service
        $occupancyService = app(OccupancyCalculationService::class);
        $avgOccupancy = round($occupancyService->calculateWeightedOccupancy($historicalData, $roomTypes), 1);

        // Unique operational days (one date regardless of how many room types have rows for it)
        $uniqueDays = $historicalData->pluck('date')->unique()->count();

        // Exclude extreme revenue outliers (data-entry errors: e.g. Rp 81B, Rp 18B rows)
        // Normal daily total revenue is well under Rp 50 M; cap per-row at Rp 50 M to be safe.
        $revenueOutlierCap = 50_000_000;
        $cleanRevenue = $historicalData->filter(fn ($r) => ($r->revenue ?? 0) <= $revenueOutlierCap);
        $totalRevenue = $cleanRevenue->sum('revenue');
        // Only average over days that actually have revenue (exclude seeded zero-revenue days)
        $daysWithRevenue = $cleanRevenue->filter(fn ($r) => ($r->revenue ?? 0) > 0)->pluck('date')->unique()->count();
        $avgRevenue = $daysWithRevenue > 0
            ? round($totalRevenue / $daysWithRevenue, 0)
            : 0;

        $stats = [
            'avgOccupancy' => $avgOccupancy,
            'maxOccupancy' => round($historicalData->max('occupancy_rate'), 1),
            'minOccupancy' => round($historicalData->min('occupancy_rate'), 1),
            'totalRevenue' => $totalRevenue,
            'avgRevenue'   => $avgRevenue,
            'totalRecords' => $uniqueDays,   // Show unique operational days, not raw DB rows
        ];

        // Prepare MONTHLY chart data — aggregate daily rows into months per room type
        $chartData = ['occupancy' => [], 'revenue' => []];

        foreach ($roomTypes as $roomType) {
            $roomData = $historicalData->where('room_type_id', $roomType->id);
            if ($roomData->isEmpty()) continue;

            // Group by Y-m, then average occupancy and sum revenue
            $byMonth = $roomData->groupBy(fn ($r) => Carbon::parse($r->date)->format('Y-m'));

            $occPoints = [];
            $revPoints = [];

            foreach ($byMonth->sortKeys() as $ym => $rows) {
                $ts = Carbon::parse($ym . '-01')->timestamp * 1000;

                $occPoints[] = [
                    'x' => $ts,
                    'y' => round($rows->avg('occupancy_rate'), 1),
                ];

                $cleanRevenue = $rows->filter(fn ($r) => ($r->revenue ?? 0) <= 50_000_000)->sum('revenue');
                // null instead of 0 — chart will gap seeded months with no real revenue
                $revPoints[] = [
                    'x' => $ts,
                    'y' => $cleanRevenue > 0 ? round($cleanRevenue, 0) : null,
                ];
            }

            $chartData['occupancy'][] = ['name' => $roomType->name, 'data' => $occPoints];
            $chartData['revenue'][]   = ['name' => $roomType->name, 'data' => $revPoints];
        }

        // Calculate performance by room type
        $performanceByRoomType = [];
        foreach ($roomTypes as $roomType) {
            $roomData = $historicalData->where('room_type_id', $roomType->id);

            if ($roomData->isNotEmpty()) {
                $avgOccupancy = round($roomData->avg('occupancy_rate'), 1);
                $totalRevenue = $roomData->sum('revenue');

                // Exclude outlier revenue rows for per-type stats too
                $revenueOutlierCap = 50_000_000;
                $cleanRoomRevenue = $roomData->filter(fn ($r) => ($r->revenue ?? 0) <= $revenueOutlierCap);
                $roomUniqueDays = $roomData->pluck('date')->unique()->count();

                $performanceByRoomType[] = [
                    'id' => $roomType->id,
                    'name' => $roomType->name,
                    'avgOccupancy' => $avgOccupancy,
                    'maxOccupancy' => round($roomData->max('occupancy_rate'), 1),
                    'minOccupancy' => round($roomData->min('occupancy_rate'), 1),
                    'totalRevenue' => $cleanRoomRevenue->sum('revenue'),
                    'avgRevenue' => $roomUniqueDays > 0
                        ? round($cleanRoomRevenue->sum('revenue') / $roomUniqueDays, 0)
                        : round($cleanRoomRevenue->avg('revenue') ?? 0, 0),
                    'totalDays' => $roomUniqueDays,
                    'color' => $this->getRoomTypeColor($roomType->id),
                    'performance' => $this->getPerformanceStatus($avgOccupancy),
                ];
            }
        }

        // Get monthly comparison
        $monthlyComparison = $this->getMonthlyComparison($historicalData);

        // Get peak and low periods
        $insights = $this->generateInsights($historicalData, $roomTypes);

        $dbMinDate = HistoricalOccupancyData::min('date') ?? '2021-01-01';

        // Build year-on-year comparison data
        $yearComparison = $this->getYearComparisonData($year1, $year2);

        return Inertia::render('History/Index', [
            'historicalData'        => $historicalData->take(100),
            'totalRecords'          => $historicalData->count(),
            'showingRecords'        => min(100, $historicalData->count()),
            'stats'                 => $stats,
            'chartData'             => $chartData,
            'performanceByRoomType' => $performanceByRoomType,
            'monthlyComparison'     => $monthlyComparison,
            'insights'              => $insights,
            'roomTypes'             => $roomTypes,
            'yearComparison'        => $yearComparison,
            'availableYears'        => $availableYears,
            'filters'               => [
                'room_type'    => $roomTypeFilter,
                'years_back'   => $yearsBack,
                'db_min_date'  => Carbon::parse($dbMinDate)->format('Y-m-d'),
                'db_max_date'  => Carbon::parse($maxHistoricalDate)->format('Y-m-d'),
                'year1'        => $year1,
                'year2'        => $year2,
            ],
        ]);
    }

    private function getRoomTypeColor($roomTypeId)
    {
        // Distinct hex colors per room type slot (maps by position, not by ID)
        $colors = ['#3F72AF', '#10B981', '#F59E0B', '#F43F5E'];
        return $colors[($roomTypeId - 1) % count($colors)];
    }

    private function getPerformanceStatus($avgOccupancy)
    {
        if ($avgOccupancy >= 55) {
            return 'excellent';
        } elseif ($avgOccupancy >= 40) {
            return 'good';
        }
        return 'poor';
    }

    private function getMonthlyComparison($historicalData)
    {
        $monthlyData = $historicalData->groupBy(function ($item) {
            return Carbon::parse($item->date)->format('Y-m');
        });

        // Get room types for capacity weighting
        $roomTypes = RoomType::where('is_active', true)->get();
        $totalCapacity = $roomTypes->sum('total_rooms');

        $comparison = [];
        foreach ($monthlyData as $month => $data) {
            // Calculate capacity-weighted occupancy for this month
            if ($totalCapacity > 0) {
                $weightedSum = 0;
                foreach ($roomTypes as $rt) {
                    $rtData = $data->where('room_type_id', $rt->id);
                    if ($rtData->isNotEmpty()) {
                        $weightedSum += $rtData->avg('occupancy_rate') * $rt->total_rooms;
                    }
                }
                $avgOccupancy = round($weightedSum / $totalCapacity, 1);
            } else {
                $avgOccupancy = round($data->avg('occupancy_rate'), 1);
            }

            $revenueOutlierCap = 50_000_000;
            $cleanMonthRevenue = $data->filter(fn ($r) => ($r->revenue ?? 0) <= $revenueOutlierCap)->sum('revenue');
            $monthUniqueDays   = $data->pluck('date')->unique()->count();

            $comparison[] = [
                'month'        => Carbon::parse($month . '-01')->translatedFormat('F Y'),
                'avgOccupancy' => $avgOccupancy,
                'totalRevenue' => $cleanMonthRevenue > 0 ? $cleanMonthRevenue : null,
                'totalDays'    => $monthUniqueDays,
            ];
        }

        return array_reverse($comparison); // Most recent first
    }

    /**
     * Build year-on-year comparison data.
     * Returns per-year monthly series (Jan–Dec) for any 2 selected years.
     */
    private function getYearComparisonData(int $year1, int $year2): array
    {
        $roomTypes   = RoomType::where('is_active', true)->get();
        $totalCapacity = $roomTypes->sum('total_rooms');

        $results = [];
        foreach ([$year1, $year2] as $year) {
            $monthlyOccupancy = [];
            $monthlyRevenue   = [];

            for ($m = 1; $m <= 12; $m++) {
                $monthStart = Carbon::create($year, $m, 1)->startOfMonth()->toDateString();
                $monthEnd   = Carbon::create($year, $m, 1)->endOfMonth()->toDateString();

                $monthData = HistoricalOccupancyData::with('roomType')
                    ->whereBetween('date', [$monthStart, $monthEnd])
                    ->get();

                if ($monthData->isEmpty()) {
                    $monthlyOccupancy[] = null;
                    $monthlyRevenue[]   = null;
                    continue;
                }

                // Capacity-weighted occupancy
                if ($totalCapacity > 0) {
                    $weightedSum = 0;
                    foreach ($roomTypes as $rt) {
                        $rtData = $monthData->where('room_type_id', $rt->id);
                        if ($rtData->isNotEmpty()) {
                            $weightedSum += $rtData->avg('occupancy_rate') * $rt->total_rooms;
                        }
                    }
                    $monthlyOccupancy[] = round($weightedSum / $totalCapacity, 1);
                } else {
                    $monthlyOccupancy[] = round($monthData->avg('occupancy_rate'), 1);
                }

                $revenueOutlierCap = 50_000_000;
                $cleanRev = $monthData->filter(fn ($r) => ($r->revenue ?? 0) <= $revenueOutlierCap)->sum('revenue');
                // Send null instead of 0 — zero means no revenue data (seeded months have no real revenue)
                $monthlyRevenue[] = $cleanRev > 0 ? round($cleanRev, 0) : null;
            }

            $results[$year] = [
                'occupancy' => $monthlyOccupancy,
                'revenue'   => $monthlyRevenue,
            ];
        }

        return $results;
    }

    /** Return distinct years that have any historical data, sorted desc. */
    private function getAvailableYears(): array
    {
        return HistoricalOccupancyData::selectRaw("strftime('%Y', date) as year")
            ->distinct()
            ->orderByRaw('year desc')
            ->pluck('year')
            ->map(fn ($y) => (int) $y)
            ->toArray();
    }

    private function generateInsights($historicalData, $roomTypes)
    {
        $insights = [];

        // Find best performing period
        $bestDay = $historicalData->sortByDesc('occupancy_rate')->first();
        if ($bestDay) {
            $insights[] = [
                'type' => 'success',
                'title' => 'Performa Terbaik',
                'description' => "Okupansi tertinggi tercatat pada " . Carbon::parse($bestDay->date)->format('d M Y') . " dengan {$bestDay->occupancy_rate}% untuk {$bestDay->roomType->name}.",
                'icon' => 'trophy',
            ];
        }

        // Find lowest occupancy period
        $worstDay = $historicalData->sortBy('occupancy_rate')->first();
        if ($worstDay) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Periode Rendah',
                'description' => "Okupansi terendah pada " . Carbon::parse($worstDay->date)->format('d M Y') . " dengan {$worstDay->occupancy_rate}% untuk {$worstDay->roomType->name}.",
                'icon' => 'alert',
            ];
        }

        // Calculate average occupancy trend (capacity-weighted)
        $occupancyService = app(OccupancyCalculationService::class);
        $avgOccupancy = round($occupancyService->calculateWeightedOccupancy($historicalData, $roomTypes), 1);
        if ($avgOccupancy >= 70) {
            $insights[] = [
                'type' => 'success',
                'title' => 'Performa Konsisten',
                'description' => "Rata-rata okupansi {$avgOccupancy}% menunjukkan performa yang sangat baik. Pertahankan strategi marketing dan layanan saat ini.",
                'icon' => 'check',
            ];
        } elseif ($avgOccupancy < 50) {
            $insights[] = [
                'type' => 'info',
                'title' => 'Peluang Peningkatan',
                'description' => "Rata-rata okupansi {$avgOccupancy}% masih dapat ditingkatkan. Pertimbangkan strategi promosi atau penyesuaian harga.",
                'icon' => 'lightbulb',
            ];
        }

        // Revenue insights — exclude outlier rows, count unique days
        $revenueOutlierCap = 50_000_000;
        $cleanRevTotal = $historicalData->filter(fn ($r) => ($r->revenue ?? 0) <= $revenueOutlierCap)->sum('revenue');
        $uniqueDaysInsight = $historicalData->pluck('date')->unique()->count();
        if ($cleanRevTotal > 0) {
            $insights[] = [
                'type' => 'info',
                'title' => 'Total Pendapatan',
                'description' => "Total pendapatan periode ini mencapai Rp " . number_format($cleanRevTotal, 0, ',', '.') . " dari " . $uniqueDaysInsight . " hari operasional.",
                'icon' => 'cash',
            ];
        }

        return $insights;
    }
}
