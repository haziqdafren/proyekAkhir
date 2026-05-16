<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('historical_occupancy_data', function (Blueprint $table) {
            // date-first index for fast WHERE date BETWEEN x AND y range scans
            $table->index('date', 'idx_historical_date');
        });
    }

    public function down(): void
    {
        Schema::table('historical_occupancy_data', function (Blueprint $table) {
            $table->dropIndex('idx_historical_date');
        });
    }
};
