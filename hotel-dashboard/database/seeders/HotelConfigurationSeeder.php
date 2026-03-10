<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HotelConfigurationSeeder extends Seeder
{
    /**
     * Seed hotel room types with actual capacities
     */
    public function run(): void
    {
        // Clear existing data
        DB::table('room_types')->truncate();

        // Insert actual hotel room configurations
        // Prices from Hotel Dharma Utama Room Rate card
        $roomTypes = [
            [
                'code' => 'STD',
                'name' => 'Standard Room',
                'description' => 'Kamar standar dengan fasilitas lengkap, termasuk sarapan pagi',
                'capacity' => 2,
                'total_rooms' => 27,
                'base_price' => 180000, // Rp. 180.000
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'code' => 'SPR',
                'name' => 'Superior Room',
                'description' => 'Kamar superior dengan view yang lebih baik, termasuk sarapan pagi',
                'capacity' => 2,
                'total_rooms' => 23,
                'base_price' => 220000, // Rp. 220.000
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'code' => 'STE',
                'name' => 'Suite Room',
                'description' => 'Suite dengan ruang yang lebih luas, termasuk sarapan pagi',
                'capacity' => 2,
                'total_rooms' => 2,
                'base_price' => 270000, // Rp. 270.000
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'code' => 'FMY',
                'name' => 'Family Room',
                'description' => 'Kamar keluarga yang luas, termasuk sarapan pagi',
                'capacity' => 4,
                'total_rooms' => 4,
                'base_price' => 400000, // Rp. 400.000
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        DB::table('room_types')->insert($roomTypes);

        $this->command->info('✓ Hotel room configuration seeded successfully');
        $this->command->info('  Total rooms: 56 (STD: 27, SPR: 23, STE: 2, FMY: 4)');
    }
}
