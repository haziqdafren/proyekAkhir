<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HistoricalOccupancyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roomTypes = \App\Models\RoomType::all();
        $startDate = now()->subMonths(12);
        $endDate = now()->subDay();

        foreach ($roomTypes as $roomType) {
            $currentDate = $startDate->copy();

            while ($currentDate <= $endDate) {
                // Determine occupancy rate based on season
                $month = $currentDate->month;
                $baseOccupancy = match (true) {
                    in_array($month, [6, 7, 8, 12]) => rand(70, 90), // High season
                    in_array($month, [1, 2, 3, 9, 10, 11]) => rand(40, 65), // Low season
                    default => rand(55, 75), // Mid season
                };

                // Weekend boost
                if (in_array($currentDate->dayOfWeek, [5, 6, 0])) { // Fri, Sat, Sun
                    $baseOccupancy = min(95, $baseOccupancy + rand(10, 20));
                }

                $occupancyRate = $baseOccupancy;
                $roomsOccupied = (int) round(($roomType->total_rooms * $occupancyRate) / 100);
                $roomsAvailable = $roomType->total_rooms;

                // Calculate revenue with some price variation
                $priceMultiplier = 1 + (rand(-10, 20) / 100); // -10% to +20%
                $avgRate = $roomType->base_price * $priceMultiplier;
                $revenue = $roomsOccupied * $avgRate;

                \App\Models\HistoricalOccupancyData::create([
                    'room_type_id' => $roomType->id,
                    'date' => $currentDate->format('Y-m-d'),
                    'rooms_occupied' => $roomsOccupied,
                    'rooms_available' => $roomsAvailable,
                    'occupancy_rate' => $occupancyRate,
                    'revenue' => $revenue,
                    'average_daily_rate' => $avgRate,
                ]);

                $currentDate->addDay();
            }
        }
    }
}
