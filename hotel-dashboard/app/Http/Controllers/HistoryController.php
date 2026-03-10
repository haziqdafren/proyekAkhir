<?php

namespace App\Http\Controllers;

use App\Models\HistoricalOccupancyData;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        // Get filter parameters
        $roomTypeFilter = $request->get('room_type', 'all');
        $dateRange = $request->get('date_range', '30'); // 30, 60, 90, 180, 365 days
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        // Get all room types for filter dropdown
        $roomTypes = RoomType::where('is_active', true)->get();

        // Build historical data query
        $historicalQuery = HistoricalOccupancyData::with('roomType');

        // Apply date filters
        // Historical data only goes up to October 2025
        $maxHistoricalDate = '2025-10-31';

        if ($startDate && $endDate) {
            // Ensure end date doesn't exceed max historical date
            $endDate = min($endDate, $maxHistoricalDate);
            $historicalQuery->whereBetween('date', [$startDate, $endDate]);
        } else {
            // Use date range but cap at max historical date
            $calculatedEndDate = Carbon::today();
            if ($calculatedEndDate->format('Y-m-d') > $maxHistoricalDate) {
                $calculatedEndDate = Carbon::parse($maxHistoricalDate);
            }

            $historicalQuery->where('date', '>=', $calculatedEndDate->copy()->subDays($dateRange))
                ->where('date', '<=', $calculatedEndDate);
        }

        // Apply room type filter
        if ($roomTypeFilter !== 'all') {
            $historicalQuery->where('room_type_id', $roomTypeFilter);
        }

        $historicalData = $historicalQuery->orderBy('date', 'desc')
            ->orderBy('room_type_id')
            ->get();

        // Calculate overall statistics
        $stats = [
            'avgOccupancy' => round($historicalData->avg('occupancy_rate'), 1),
            'maxOccupancy' => round($historicalData->max('occupancy_rate'), 1),
            'minOccupancy' => round($historicalData->min('occupancy_rate'), 1),
            'totalRevenue' => $historicalData->sum('revenue'),
            'avgRevenue' => round($historicalData->avg('revenue'), 0),
            'totalRecords' => $historicalData->count(),
        ];

        // Group data by date for timeline chart
        $timelineData = $historicalData->groupBy(function ($item) {
            return Carbon::parse($item->date)->format('Y-m-d');
        });

        // Prepare chart data for occupancy trend
        $chartData = [
            'occupancy' => [],
            'revenue' => [],
        ];

        foreach ($roomTypes as $roomType) {
            $roomData = $historicalData->where('room_type_id', $roomType->id);

            if ($roomData->isNotEmpty()) {
                // Occupancy chart data
                $chartData['occupancy'][] = [
                    'name' => $roomType->name,
                    'data' => $roomData->map(function ($item) {
                        return [
                            'x' => Carbon::parse($item->date)->timestamp * 1000,
                            'y' => round($item->occupancy_rate, 1),
                        ];
                    })->values()->toArray(),
                ];

                // Revenue chart data
                $chartData['revenue'][] = [
                    'name' => $roomType->name,
                    'data' => $roomData->map(function ($item) {
                        return [
                            'x' => Carbon::parse($item->date)->timestamp * 1000,
                            'y' => round($item->revenue, 0),
                        ];
                    })->values()->toArray(),
                ];
            }
        }

        // Calculate performance by room type
        $performanceByRoomType = [];
        foreach ($roomTypes as $roomType) {
            $roomData = $historicalData->where('room_type_id', $roomType->id);

            if ($roomData->isNotEmpty()) {
                $avgOccupancy = round($roomData->avg('occupancy_rate'), 1);
                $totalRevenue = $roomData->sum('revenue');

                $performanceByRoomType[] = [
                    'id' => $roomType->id,
                    'name' => $roomType->name,
                    'avgOccupancy' => $avgOccupancy,
                    'maxOccupancy' => round($roomData->max('occupancy_rate'), 1),
                    'minOccupancy' => round($roomData->min('occupancy_rate'), 1),
                    'totalRevenue' => $totalRevenue,
                    'avgRevenue' => round($roomData->avg('revenue'), 0),
                    'totalDays' => $roomData->count(),
                    'color' => $this->getRoomTypeColor($roomType->id),
                    'performance' => $this->getPerformanceStatus($avgOccupancy),
                ];
            }
        }

        // Get monthly comparison
        $monthlyComparison = $this->getMonthlyComparison($historicalData);

        // Get peak and low periods
        $insights = $this->generateInsights($historicalData, $roomTypes);

        return Inertia::render('History/Index', [
            'historicalData' => $historicalData->take(100), // Limit to 100 for table display
            'stats' => $stats,
            'chartData' => $chartData,
            'performanceByRoomType' => $performanceByRoomType,
            'monthlyComparison' => $monthlyComparison,
            'insights' => $insights,
            'roomTypes' => $roomTypes,
            'filters' => [
                'room_type' => $roomTypeFilter,
                'date_range' => $dateRange,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    private function getRoomTypeColor($roomTypeId)
    {
        $colors = ['primary', 'green', 'purple', 'orange'];
        return $colors[($roomTypeId - 1) % count($colors)];
    }

    private function getPerformanceStatus($avgOccupancy)
    {
        if ($avgOccupancy >= 75) {
            return 'excellent';
        } elseif ($avgOccupancy >= 60) {
            return 'good';
        } elseif ($avgOccupancy >= 40) {
            return 'fair';
        }
        return 'poor';
    }

    private function getMonthlyComparison($historicalData)
    {
        $monthlyData = $historicalData->groupBy(function ($item) {
            return Carbon::parse($item->date)->format('Y-m');
        });

        $comparison = [];
        foreach ($monthlyData as $month => $data) {
            $comparison[] = [
                'month' => Carbon::parse($month . '-01')->format('F Y'),
                'avgOccupancy' => round($data->avg('occupancy_rate'), 1),
                'totalRevenue' => $data->sum('revenue'),
                'totalDays' => $data->count(),
            ];
        }

        return array_reverse($comparison); // Most recent first
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

        // Calculate average occupancy trend
        $avgOccupancy = $historicalData->avg('occupancy_rate');
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

        // Revenue insights
        $totalRevenue = $historicalData->sum('revenue');
        if ($totalRevenue > 0) {
            $insights[] = [
                'type' => 'info',
                'title' => 'Total Pendapatan',
                'description' => "Total pendapatan periode ini mencapai Rp " . number_format($totalRevenue, 0, ',', '.') . " dari " . $historicalData->count() . " hari operasional.",
                'icon' => 'cash',
            ];
        }

        return $insights;
    }
}
