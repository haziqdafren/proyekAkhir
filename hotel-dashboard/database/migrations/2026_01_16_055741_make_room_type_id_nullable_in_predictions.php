<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite doesn't support ALTER COLUMN, so we need to recreate the table

        // Step 1: Create new table with nullable room_type_id
        Schema::create('predictions_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->nullable()->constrained('room_types')->onDelete('cascade');
            $table->timestamp('prediction_date');
            $table->timestamp('predicted_for_date');
            $table->decimal('predicted_occupancy_rate', 5, 2);
            $table->integer('predicted_rooms_occupied');
            $table->decimal('predicted_revenue', 15, 2);
            $table->decimal('confidence_level', 5, 2);
            $table->string('model_type', 20);
            $table->string('model_version', 10);
            $table->timestamps();
        });

        // Step 2: Copy existing data
        DB::statement('INSERT INTO predictions_new SELECT * FROM predictions');

        // Step 3: Drop old table
        Schema::dropIfExists('predictions');

        // Step 4: Rename new table to original name
        Schema::rename('predictions_new', 'predictions');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate with NOT NULL constraint
        Schema::create('predictions_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained('room_types')->onDelete('cascade');
            $table->timestamp('prediction_date');
            $table->timestamp('predicted_for_date');
            $table->decimal('predicted_occupancy_rate', 5, 2);
            $table->integer('predicted_rooms_occupied');
            $table->decimal('predicted_revenue', 15, 2);
            $table->decimal('confidence_level', 5, 2);
            $table->string('model_type', 20);
            $table->string('model_version', 10);
            $table->timestamps();
        });

        DB::statement('INSERT INTO predictions_old SELECT * FROM predictions WHERE room_type_id IS NOT NULL');
        Schema::dropIfExists('predictions');
        Schema::rename('predictions_old', 'predictions');
    }
};
