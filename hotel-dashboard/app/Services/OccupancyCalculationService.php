<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Service for calculating capacity-weighted occupancy rates
 * Centralizes the logic to avoid duplication across controllers
 */
class OccupancyCalculationService
{
    /**
     * Calculate capacity-weighted average occupancy rate.
     *
     * Algorithm:
     *   1. Group rows by room_type_id
     *   2. Average occupancy_rate within each group (eliminates the multi-row-per-type inflation)
     *   3. Compute a weighted average across groups using each type's room capacity
     *
     * This prevents the "1923%" bug where summing rate×capacity over 30 daily rows
     * per room type produced a result ~30× larger than the true weighted average.
     *
     * @param Collection $data      Collection of predictions or historical data rows
     * @param Collection $roomTypes Collection of RoomType models with id and total_rooms
     * @return float Weighted average occupancy rate (0–100)
     */
    public function calculateWeightedOccupancy(Collection $data, Collection $roomTypes): float
    {
        $totalCapacity = $roomTypes->sum('total_rooms');

        if ($totalCapacity == 0 || $data->isEmpty()) {
            return $data->avg('occupancy_rate') ?? 0.0;
        }

        $roomCapacityMap = $roomTypes->pluck('total_rooms', 'id');

        // Step 1: Group by room type
        $grouped = $data->groupBy(function ($item) {
            return $item->room_type_id ?? 'unknown';
        });

        // Step 2 & 3: avg within group → weight by capacity
        $weightedSum   = 0.0;
        $coveredCapacity = 0.0;

        foreach ($grouped as $roomTypeId => $rows) {
            $avgRate  = $rows->avg(fn ($item) =>
                $item->occupancy_rate ?? $item->predicted_occupancy_rate ?? 0
            ) ?? 0.0;
            $capacity = (float) ($roomCapacityMap[$roomTypeId] ?? 0);

            $weightedSum     += $avgRate * $capacity;
            $coveredCapacity += $capacity;
        }

        // If data only covers a subset of room types, weight against covered capacity only
        $denominator = $coveredCapacity > 0 ? $coveredCapacity : $totalCapacity;

        return $weightedSum / $denominator;
    }

    /**
     * Calculate capacity-weighted average for grouped data (e.g., by month).
     *
     * @param Collection $groupedData Grouped collection (e.g., by month)
     * @param Collection $roomTypes   Collection of RoomType models
     * @return Collection Modified grouped data with weighted occupancy
     */
    public function calculateWeightedOccupancyByGroup(Collection $groupedData, Collection $roomTypes): Collection
    {
        return $groupedData->map(function ($items) use ($roomTypes) {
            return $this->calculateWeightedOccupancy($items, $roomTypes);
        });
    }
}
