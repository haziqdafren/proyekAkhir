<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoomTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roomTypes = [
            [
                'name' => 'Standard Room',
                'description' => 'Kamar standar dengan fasilitas dasar yang nyaman untuk 2 tamu',
                'capacity' => 2,
                'base_price' => 500000,
                'total_rooms' => 50,
                'is_active' => true,
            ],
            [
                'name' => 'Superior Room',
                'description' => 'Kamar superior dengan pemandangan kota dan fasilitas lebih lengkap',
                'capacity' => 2,
                'base_price' => 750000,
                'total_rooms' => 30,
                'is_active' => true,
            ],
            [
                'name' => 'Junior Suite Room',
                'description' => 'Kamar suite dengan ruang tamu terpisah dan fasilitas premium',
                'capacity' => 3,
                'base_price' => 1200000,
                'total_rooms' => 15,
                'is_active' => true,
            ],
            [
                'name' => 'Family Room',
                'description' => 'Kamar luas untuk keluarga dengan 2 tempat tidur dan area bermain anak',
                'capacity' => 4,
                'base_price' => 1500000,
                'total_rooms' => 10,
                'is_active' => true,
            ],
        ];

        foreach ($roomTypes as $roomType) {
            \App\Models\RoomType::create($roomType);
        }
    }
}
