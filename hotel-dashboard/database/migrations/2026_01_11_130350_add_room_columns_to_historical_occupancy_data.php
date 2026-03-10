<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('historical_occupancy_data', function (Blueprint $table) {
            // Add individual room type columns
            $table->integer('kamar_std')->default(0)->after('occupancy_rate');
            $table->integer('kamar_spr')->default(0)->after('kamar_std');
            $table->integer('kamar_fmy')->default(0)->after('kamar_spr');
            $table->integer('kamar_js')->default(0)->after('kamar_fmy');
            
            // Add revenue column if not exists
            if (!Schema::hasColumn('historical_occupancy_data', 'revenue')) {
                $table->decimal('revenue', 15, 2)->default(0)->after('kamar_js');
            }
            
            // Add notes column if not exists  
            if (!Schema::hasColumn('historical_occupancy_data', 'notes')) {
                $table->text('notes')->nullable()->after('revenue');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('historical_occupancy_data', function (Blueprint $table) {
            $table->dropColumn([
                'kamar_std',
                'kamar_spr',
                'kamar_fmy',
                'kamar_js',
            ]);
        });
    }
};
