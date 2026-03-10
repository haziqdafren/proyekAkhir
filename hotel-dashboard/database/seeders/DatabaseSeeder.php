<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Hotel Manager',
            'email' => 'admin@hotel.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        // Seed room types first
        $this->call([
            RoomTypeSeeder::class,
        ]);

        $this->command->info('Room types seeded!');

        // Seed historical data (this will take a while - 12 months of daily data)
        $this->command->info('Seeding historical occupancy data (this may take a moment)...');
        $this->call([
            HistoricalOccupancyDataSeeder::class,
        ]);

        $this->command->info('Historical data seeded!');

        // Seed predictions
        $this->call([
            PredictionSeeder::class,
        ]);

        $this->command->info('Predictions seeded!');
        $this->command->info('All data seeded successfully!');
    }
}
