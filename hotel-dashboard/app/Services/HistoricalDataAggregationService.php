<?php

namespace App\Services;

use App\Models\HistoricalOccupancyData;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregates per-room-type historical data into monthly totals
 * for feature engineering
 */
class HistoricalDataAggregationService
{
    /**
     * Get aggregated monthly data for a date range
     * Aggregates all room types into monthly totals
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Collection Collection of aggregated monthly records
     */
    public function getAggregatedMonthlyData(Carbon $startDate, Carbon $endDate): Collection
    {
        // Get all historical data in range (daily data for all days in these months)
        $rawData = HistoricalOccupancyData::with('roomType')
            ->whereBetween('date', [
                $startDate->startOfMonth()->format('Y-m-d'),
                $endDate->endOfMonth()->format('Y-m-d')
            ])
            ->orderBy('date')
            ->get();

        // Group by month and aggregate
        $aggregated = $rawData->groupBy(function ($item) {
            // Ensure date is Carbon instance
            $date = $item->date instanceof Carbon ? $item->date : Carbon::parse($item->date);
            return $date->format('Y-m'); // Group by year-month
        })->map(function ($monthData, $yearMonth) {
            return $this->aggregateMonth($monthData, $yearMonth . '-01');
        });

        return $aggregated->values();
    }

    /**
     * Aggregate a month's data from all room types and all days
     * Daily data (30 days × 4 room types = ~120 records) → 1 monthly aggregate
     *
     * @param Collection $monthData All daily records for all room types in a month
     * @param string $date Date string (Y-m-01 format)
     * @return object Aggregated month object
     */
    private function aggregateMonth(Collection $monthData, string $date): object
    {
        // Group by day first, then by room type to get proper averages
        $dailyData = $monthData->groupBy(function ($item) {
            $itemDate = $item->date instanceof Carbon ? $item->date : Carbon::parse($item->date);
            return $itemDate->format('Y-m-d');
        });

        $daysCount = $dailyData->count();

        // Calculate monthly average rooms occupied per room type
        $roomTypeMonthlyData = $monthData->groupBy(function ($item) {
            return $item->roomType->code ?? 'UNKNOWN';
        })->map(function ($roomTypeRecords) use ($daysCount) {
            // Average rooms occupied per day for this room type
            $avgOccupied = $roomTypeRecords->avg('rooms_occupied');
            $avgRate = $roomTypeRecords->avg('occupancy_rate');
            $available = $roomTypeRecords->first()->rooms_available ?? 0;

            return [
                'avg_occupied' => $avgOccupied,
                'avg_rate' => $avgRate,
                'available' => $available,
            ];
        });

        // Get individual room type average occupancies
        $std_occupied = round($roomTypeMonthlyData['STD']['avg_occupied'] ?? 0, 1);
        $spr_occupied = round($roomTypeMonthlyData['SPR']['avg_occupied'] ?? 0, 1);
        $js_occupied = round($roomTypeMonthlyData['JS']['avg_occupied'] ?? 0, 1);
        $fmy_occupied = round($roomTypeMonthlyData['FMY']['avg_occupied'] ?? 0, 1);

        // Total average rooms occupied per day
        $totalOccupied = $std_occupied + $spr_occupied + $js_occupied + $fmy_occupied;

        // Total rooms available (56 total)
        $totalAvailable = 56;

        // Overall occupancy rate
        $overallRate = $totalAvailable > 0
            ? ($totalOccupied / $totalAvailable) * 100
            : 0;

        return (object) [
            'date' => Carbon::parse($date),
            'total_occupancy' => $totalOccupied,
            'occupancy_rate' => round($overallRate, 2),
            'kamar_std' => $std_occupied,
            'kamar_spr' => $spr_occupied,
            'kamar_js' => $js_occupied,
            'kamar_fmy' => $fmy_occupied,
            'kamar_terjual' => $totalOccupied, // Alias for total_occupancy
        ];
    }

    /**
     * Get last N months of aggregated data
     *
     * @param int $months Number of months
     * @return Collection
     */
    public function getLastNMonths(int $months = 12): Collection
    {
        $endDate = Carbon::now()->subMonth();
        $startDate = $endDate->copy()->subMonths($months - 1)->startOfMonth();

        return $this->getAggregatedMonthlyData($startDate, $endDate);
    }
}
