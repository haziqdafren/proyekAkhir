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
        Schema::table('predictions', function (Blueprint $table) {
            // Add individual indexes for frequently queried columns
            $table->index('predicted_for_date', 'idx_predicted_for_date');
            $table->index('model_type', 'idx_model_type');
            $table->index('room_type_id', 'idx_room_type_id');

            // Add composite index for common query patterns
            // This speeds up queries that filter by both model_type and predicted_for_date
            $table->index(['model_type', 'predicted_for_date'], 'idx_model_type_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            // Drop indexes in reverse order
            $table->dropIndex('idx_model_type_date');
            $table->dropIndex('idx_room_type_id');
            $table->dropIndex('idx_model_type');
            $table->dropIndex('idx_predicted_for_date');
        });
    }
};
