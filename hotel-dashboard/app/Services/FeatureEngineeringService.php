<?php

namespace App\Services;

use App\Models\HistoricalOccupancyData;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FeatureEngineeringService
{
    /**
     * Feature names as defined in the model
     */
    private const REQUIRED_FEATURES = [
        'Kamar_Terjual',
        'Okupansi_Rate',
        'occ_momentum',
        'occ_trend',
        'occ_rolling_mean_3',
        'occ_yoy',
        'std_proportion',
        'is_increasing',
        'is_peak_season',
        'js_proportion',
        'fmy_proportion',
        'spr_proportion',
        'occ_rolling_mean_6',
        'occ_rolling_std_3',
        'occ_lag_1'
    ];

    /**
     * Total rooms in hotel
     */
    private const TOTAL_ROOMS = 56;

    /**
     * Extract and calculate 15 features from historical data
     *
     * @param Collection $historicalData Collection of 6+ months of historical data
     * @return array Array of shape [6, 15] ready for model input
     */
    public function prepareFeatures(Collection $historicalData): array
    {
        // Ensure we have enough data (need at least 12 months for YoY calculation)
        if ($historicalData->count() < 6) {
            throw new \InvalidArgumentException('Need at least 6 months of historical data');
        }

        // Sort by date ascending
        $sorted = $historicalData->sortBy('date');
        
        // Get last 6 months for prediction
        $recentSixMonths = $sorted->take(-6);
        
        // Calculate features for each of the 6 months
        $features = [];
        $allData = $sorted->values(); // For lookback calculations
        
        foreach ($recentSixMonths->values() as $index => $month) {
            $monthIndex = $allData->search(function ($item) use ($month) {
                return $item->date->eq($month->date);
            });
            
            $features[] = $this->calculateMonthFeatures($month, $allData, $monthIndex);
        }

        return $features;
    }

    /**
     * Calculate all 15 features for a single month
     *
     * @param mixed $current Current month data
     * @param Collection $allData All historical data for lookback
     * @param int $index Current index in allData
     * @return array Array of 15 features
     */
    private function calculateMonthFeatures($current, Collection $allData, int $index): array
    {
        return [
            $this->normalizeRooms($current->total_occupancy ?? $current->kamar_terjual ?? 0), // Kamar_Terjual
            $this->normalizeRate($current->occupancy_rate ?? 0), // Okupansi_Rate
            $this->calculateMomentum($allData, $index), // occ_momentum
            $this->calculateTrend($allData, $index), // occ_trend
            $this->calculateRollingMean($allData, $index, 3), // occ_rolling_mean_3
            $this->calculateYoY($allData, $index), // occ_yoy
            $this->calculateProportion($current, 'std') / 100, // std_proportion (normalized)
            $this->isIncreasing($allData, $index) ? 1.0 : 0.0, // is_increasing
           $this->isPeakSeason($current->date) ? 1.0 : 0.0, // is_peak_season
            $this->calculateProportion($current, 'js') / 100, // js_proportion
            $this->calculateProportion($current, 'fmy') / 100, // fmy_proportion
            $this->calculateProportion($current, 'spr') / 100, // spr_proportion
            $this->calculateRollingMean($allData, $index, 6), // occ_rolling_mean_6
            $this->calculateRollingStd($allData, $index, 3), // occ_rolling_std_3
            $this->calculateLag($allData, $index, 1), // occ_lag_1
        ];
    }

    /**
     * Normalize room count to [0, 1]
     */
    private function normalizeRooms(float $rooms): float
    {
        return $rooms / self::TOTAL_ROOMS;
    }

    /**
     * Normalize occupancy rate to [0, 1]
     */
    private function normalizeRate(float $rate): float
    {
        return $rate / 100;
    }

    /**
     * Calculate occupancy momentum (rate of change)
     */
    private function calculateMomentum(Collection $data, int $index): float
    {
        if ($index < 1) return 0.0;
        
        $current = $this->getOccupancyRate($data[$index]);
        $previous = $this->getOccupancyRate($data[$index - 1]);
        
        return ($current - $previous) / 100; // Normalized change
    }

    /**
     * Calculate trend (average change over last 3 months)
     */
    private function calculateTrend(Collection $data, int $index): float
    {
        if ($index < 2) return 0.0;
        
        $changes = [];
        for ($i = max(0, $index - 2); $i <= $index; $i++) {
            if ($i > 0) {
                $curr = $this->getOccupancyRate($data[$i]);
                $prev = $this->getOccupancyRate($data[$i - 1]);
                $changes[] = ($curr - $prev) / 100;
            }
        }
        
        return count($changes) > 0 ? array_sum($changes) / count($changes) : 0.0;
    }

    /**
     * Calculate rolling mean
     */
    private function calculateRollingMean(Collection $data, int $index, int $window): float
    {
        $start = max(0, $index - $window + 1);
        $values = [];
        
        for ($i = $start; $i <= $index; $i++) {
            $values[] = $this->getOccupancyRate($data[$i]);
        }
        
        return (array_sum($values) / count($values)) / 100; // Normalized
    }

    /**
     * Calculate rolling standard deviation
     */
    private function calculateRollingStd(Collection $data, int $index, int $window): float
    {
        $start = max(0, $index - $window + 1);
        $values = [];
        
        for ($i = $start; $i <= $index; $i++) {
            $values[] = $this->getOccupancyRate($data[$i]) / 100;
        }
        
        if (count($values) < 2) return 0.0;
        
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(function ($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values)) / count($values);
        
        return sqrt($variance);
    }

    /**
     * Calculate year-over-year change
     */
    private function calculateYoY(Collection $data, int $index): float
    {
        $yearAgoIndex = $index - 12;
        if ($yearAgoIndex < 0) return 0.0;
        
        $current = $this->getOccupancyRate($data[$index]);
        $yearAgo = $this->getOccupancyRate($data[$yearAgoIndex]);
        
        if ($yearAgo == 0) return 0.0;
        
        return ($current - $yearAgo) / $yearAgo; // Percentage change
    }

    /**
     * Calculate room type proportion
     */
    private function calculateProportion($monthData, string $roomType): float
    {
        $fieldName = "kamar_" . strtolower($roomType);
        $roomsSold = $monthData->$fieldName ?? 0;
        $total = $monthData->total_occupancy ?? $monthData->kamar_terjual ?? 1;
        
        return $total > 0 ? ($roomsSold / $total) * 100 : 0.0;
    }

    /**
     * Check if occupancy is increasing
     */
    private function isIncreasing(Collection $data, int $index): bool
    {
        if ($index < 1) return false;
        
        $current = $this->getOccupancyRate($data[$index]);
        $previous = $this->getOccupancyRate($data[$index - 1]);
        
        return $current > $previous;
    }

    /**
     * Check if month is in peak season (June, July, December)
     */
    private function isPeakSeason(Carbon $date): bool
    {
        $month = $date->month;
        return in_array($month, [6, 7, 12]); // June, July, December
    }

    /**
     * Calculate lagged value
     */
    private function calculateLag(Collection $data, int $index, int $lag): float
    {
        $lagIndex = $index - $lag;
        if ($lagIndex < 0) return 0.0;
        
        return $this->getOccupancyRate($data[$lagIndex]) / 100;
    }

    /**
     * Get occupancy rate from month data
     */
    private function getOccupancyRate($monthData): float
    {
        return $monthData->occupancy_rate ?? 0.0;
    }

    /**
     * Validate that all required features are present
     */
    public function validateFeatureArray(array $features): bool
    {
        if (count($features) !== 6) return false;
        
        foreach ($features as $month) {
            if (!is_array($month) || count($month) !== 15) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get feature names
     */
    public function getFeatureNames(): array
    {
        return self::REQUIRED_FEATURES;
    }
}
