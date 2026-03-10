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
        Schema::create('historical_occupancy_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->integer('rooms_occupied');
            $table->integer('rooms_available');
            $table->decimal('occupancy_rate', 5, 2);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->decimal('average_daily_rate', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['room_type_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historical_occupancy_data');
    }
};
