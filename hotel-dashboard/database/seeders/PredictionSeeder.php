<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PredictionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roomTypes = \App\Models\RoomType::all();
        $predictionDate = now();
        $startDate = now()->addDay();
        $endDate = now()->addDays(30);

        foreach ($roomTypes as $roomType) {
            $currentDate = $startDate->copy();

            while ($currentDate <= $endDate) {
                // Predict based on current month
                $month = $currentDate->month;
                $basePrediction = match (true) {
                    in_array($month, [6, 7, 8, 12]) => rand(75, 92), // High season
                    in_array($month, [1, 2, 3, 9, 10, 11]) => rand(45, 68), // Low season
                    default => rand(58, 78), // Mid season
                };

                // Weekend prediction boost
                if (in_array($currentDate->dayOfWeek, [5, 6, 0])) {
                    $basePrediction = min(95, $basePrediction + rand(8, 15));
                }

                $predictedOccupancyRate = $basePrediction;
                $predictedRoomsOccupied = (int) round(($roomType->total_rooms * $predictedOccupancyRate) / 100);

                // Predicted revenue with slight price increase
                $priceMultiplier = 1 + (rand(0, 15) / 100);
                $avgRate = $roomType->base_price * $priceMultiplier;
                $predictedRevenue = $predictedRoomsOccupied * $avgRate;

                // Confidence level based on how far in the future
                $daysAhead = $currentDate->diffInDays($predictionDate);
                $confidenceLevel = max(60, 95 - ($daysAhead * 1.2));

                \App\Models\Prediction::create([
                    'room_type_id' => $roomType->id,
                    'prediction_date' => $predictionDate->format('Y-m-d'),
                    'predicted_for_date' => $currentDate->format('Y-m-d'),
                    'predicted_occupancy_rate' => $predictedOccupancyRate,
                    'predicted_rooms_occupied' => $predictedRoomsOccupied,
                    'predicted_revenue' => $predictedRevenue,
                    'confidence_level' => round($confidenceLevel, 2),
                    'model_type' => rand(0, 1) ? 'single' : 'multi',
                    'model_version' => '1.0.0',
                ]);

                $currentDate->addDay();
            }
        }
    }
}
