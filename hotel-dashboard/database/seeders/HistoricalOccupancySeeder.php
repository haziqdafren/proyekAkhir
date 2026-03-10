<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\RoomType;
use Carbon\Carbon;

class HistoricalOccupancySeeder extends Seeder
{
    /**
     * Seed historical occupancy data - per room type per month
     */
    public function run(): void
    {
        $this->command->info('Seeding historical occupancy data...');

        // Get room types 
        $roomTypes = RoomType::all();

        if ($roomTypes->isEmpty()) {
            $this->command->error('No room types found! Run HotelConfigurationSeeder first.');
            return;
        }

        // Start from Jan 2024 (18 months of data)
        $startDate = Carbon::create(2024, 1, 1);
        $endDate = Carbon::now()->subMonth();

        $data = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $month = $currentDate->month;
            $monthName = $currentDate->format('F');
            
            // Seasonal factor
            $seasonalFactor = $this->getSeasonalFactor($month);
            
            // Base occupancy
            $baseOccupancy = 0.55 + ($seasonalFactor * 0.25) + (rand(-50, 50) / 1000);
            $baseOccupancy = max(0.25, min(0.95, $baseOccupancy));
            
            foreach ($roomTypes as $roomType) {
                // Room-specific occupancy variations
                $roomFactor = 1.0;
                switch ($roomType->code) {
                    case 'STD':
                        $roomFactor = 1 + rand(0, 100) / 1000; // Highest demand
                        break;
                    case 'SPR':
                        $roomFactor = 0.9 + rand(0, 150) / 1000;
                        break;
                    case 'STE':
                        $roomFactor = 0.6 + rand(0, 300) / 1000; // Suite - variable demand
                        break;
                    case 'FMY':
                        $roomFactor = 0.7 + rand(0, 200) / 1000; // Family - lower
                        break;
                }
                
                $roomOccupancy = $baseOccupancy * $roomFactor;
                $roomOccupancy = max(0.10, min(0.98, $roomOccupancy));
                
                $roomsAvailable = $roomType->total_rooms;
                $roomsOccupied = round($roomOccupancy * $roomsAvailable);
                $actualRate = ($roomsOccupied / $roomsAvailable) * 100;
                
                // Calculate revenue
                $revenue = round($roomsOccupied * $roomType->base_price);
                $averageDailyRate = $roomsOccupied > 0 ? round($revenue / $roomsOccupied) : 0;
                
                $data[] = [
                    'room_type_id' => $roomType->id,
                    'date' => $currentDate->format('Y-m-01'),
                    'rooms_occupied' => $roomsOccupied,
                    'rooms_available' => $roomsAvailable,
                    'occupancy_rate' => round($actualRate, 2),
                    'revenue' => $revenue,
                    'average_daily_rate' => $averageDailyRate,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }

            $this->command->info("  ✓ {$monthName} {$currentDate->year}");
            $currentDate->addMonth();
        }

        // Clear existing data
        DB::table('historical_occupancy_data')->truncate();
        
        // Insert in chunks
        collect($data)->chunk(50)->each(function ($chunk) {
            DB::table('historical_occupancy_data')->insert($chunk->toArray());
        });

        $this->command->info("✓ Seeded " . count($data) . " records (" . ($endDate->diffInMonths($startDate) + 1) . " months × " . $roomTypes->count() . " room types)");
    }

    /**
     * Get seasonal factor based on month
     */
    private function getSeasonalFactor(int $month): float
    {
        $seasonalPatterns = [
            1  => 0.2,  // January - Low
            2  => 0.2,  // February - Low
            3  => 0.4,  // March - Medium
            4  => 0.5,  // April - Medium
            5  => 0.6,  // May - Medium-High
            6  => 1.0,  // June - Peak
            7  => 1.0,  // July - Peak
            8  => 0.7,  // August - Medium-High
            9  => 0.3,  // September - Low
            10 => 0.3,  // October - Low
            11 => 0.6,  // November - Medium-High
            12 => 0.9,  // December - High
        ];

        return $seasonalPatterns[$month] ?? 0.5;
    }
}
