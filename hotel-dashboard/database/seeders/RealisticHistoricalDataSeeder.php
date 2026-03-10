<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RoomType;
use App\Models\HistoricalOccupancyData;
use Carbon\Carbon;

class RealisticHistoricalDataSeeder extends Seeder
{
    /**
     * Indonesian holidays and special dates
     */
    private array $holidays = [
        '01-01', // New Year
        '02-14', // Valentine's Day
        '03-28', // Nyepi
        '04-10', // Eid al-Fitr (approx)
        '05-01', // Labor Day
        '05-09', // Ascension Day
        '06-01', // Pancasila Day
        '06-17', // Eid al-Adha (approx)
        '08-17', // Independence Day
        '12-24', // Christmas Eve
        '12-25', // Christmas
        '12-31', // New Year's Eve
    ];

    /**
     * Run the database seeds - Generate historical data from Jan 2024 to Oct 2025
     */
    public function run(): void
    {
        $this->command->info('🏨 Generating realistic hotel occupancy data...');

        // Clear existing data
        $this->command->info('Clearing existing historical data...');
        HistoricalOccupancyData::truncate();

        // Get all room types
        $roomTypes = RoomType::all();

        if ($roomTypes->isEmpty()) {
            $this->command->error('No room types found! Please run RoomTypeSeeder first.');
            return;
        }

        // Generate data from January 2024 to October 2025 (fixed dates)
        $startDate = Carbon::parse('2024-01-01');
        $endDate = Carbon::parse('2025-10-31'); // Historical data ends October 2025

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $this->command->info("Generating data from {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')} ({$totalDays} days)");

        $records = [];
        $currentDate = $startDate->copy();
        $bar = $this->command->getOutput()->createProgressBar($totalDays * $roomTypes->count());

        while ($currentDate <= $endDate) {
            foreach ($roomTypes as $roomType) {
                $occupancyData = $this->generateRealisticOccupancy($currentDate, $roomType);

                $records[] = [
                    'room_type_id' => $roomType->id,
                    'date' => $currentDate->format('Y-m-d'),
                    'rooms_occupied' => $occupancyData['rooms_occupied'],
                    'rooms_available' => $roomType->total_rooms,
                    'occupancy_rate' => $occupancyData['occupancy_rate'],
                    'revenue' => $occupancyData['revenue'],
                    'average_daily_rate' => $occupancyData['adr'],
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $bar->advance();

                // Insert in batches of 500 for performance
                if (count($records) >= 500) {
                    HistoricalOccupancyData::insert($records);
                    $records = [];
                }
            }

            $currentDate->addDay();
        }

        // Insert remaining records
        if (!empty($records)) {
            HistoricalOccupancyData::insert($records);
        }

        $bar->finish();
        $this->command->newLine(2);

        // Display statistics
        $total = HistoricalOccupancyData::count();
        $avgOccupancy = HistoricalOccupancyData::avg('occupancy_rate');
        $totalRevenue = HistoricalOccupancyData::sum('revenue');

        $this->command->info("✅ Successfully generated {$total} historical records!");
        $this->command->info("📊 Average Occupancy: " . number_format($avgOccupancy, 2) . "%");
        $this->command->info("💰 Total Revenue: Rp " . number_format($totalRevenue, 0, ',', '.'));
    }

    /**
     * Generate realistic occupancy data for a specific date and room type
     */
    private function generateRealisticOccupancy(Carbon $date, RoomType $roomType): array
    {
        // Base occupancy rate depends on room type
        $baseOccupancy = match($roomType->code) {
            'STD' => 0.75, // Standard rooms: 75% base
            'SPR' => 0.70, // Superior rooms: 70% base
            'STE' => 0.55, // Suites: 55% base (more exclusive)
            'FMY' => 0.68, // Family rooms: 68% base
            default => 0.70,
        };

        // Seasonal adjustment
        $month = (int) $date->format('m');
        $seasonalMultiplier = $this->getSeasonalMultiplier($month);

        // Weekend boost (Friday & Saturday)
        $dayOfWeek = (int) $date->format('N'); // 1=Monday, 7=Sunday
        $weekendBoost = ($dayOfWeek >= 5 && $dayOfWeek <= 6) ? 1.15 : 1.0;

        // Holiday boost
        $holidayBoost = $this->isHoliday($date) ? 1.25 : 1.0;

        // School holiday boost for family rooms
        $schoolHolidayBoost = 1.0;
        if ($roomType->code === 'FMY' && $this->isSchoolHoliday($month)) {
            $schoolHolidayBoost = 1.20;
        }

        // Random daily variation (-5% to +5%)
        $randomVariation = 1 + (mt_rand(-5, 5) / 100);

        // Calculate final occupancy rate
        $occupancyRate = $baseOccupancy * $seasonalMultiplier * $weekendBoost
                        * $holidayBoost * $schoolHolidayBoost * $randomVariation;

        // Cap at 100%
        $occupancyRate = min($occupancyRate, 1.0);

        // Ensure minimum 20% even in lowest periods
        $occupancyRate = max($occupancyRate, 0.20);

        // Calculate rooms occupied
        $roomsOccupied = (int) round($roomType->total_rooms * $occupancyRate);

        // Calculate revenue
        $revenue = $roomsOccupied * $roomType->base_price;

        // Calculate ADR (Average Daily Rate) - only if rooms were occupied
        $adr = $roomsOccupied > 0 ? ($revenue / $roomsOccupied) : 0;

        return [
            'rooms_occupied' => $roomsOccupied,
            'occupancy_rate' => round($occupancyRate * 100, 2),
            'revenue' => $revenue,
            'adr' => round($adr, 2),
        ];
    }

    /**
     * Get seasonal multiplier based on month
     */
    private function getSeasonalMultiplier(int $month): float
    {
        return match($month) {
            12, 1 => 1.25,  // December-January: Peak season (holidays, New Year)
            6, 7, 8 => 1.20, // June-August: High season (summer vacation)
            4, 5 => 1.10,    // April-May: Mid-high season (Eid, long weekends)
            9, 10, 11 => 0.85, // Sep-Nov: Low season
            2, 3 => 0.90,    // Feb-March: Low-mid season
            default => 1.0,
        };
    }

    /**
     * Check if date is a holiday
     */
    private function isHoliday(Carbon $date): bool
    {
        $monthDay = $date->format('m-d');
        return in_array($monthDay, $this->holidays);
    }

    /**
     * Check if month has school holidays
     */
    private function isSchoolHoliday(int $month): bool
    {
        // June-July and December: Indonesian school holidays
        return in_array($month, [6, 7, 12]);
    }
}
