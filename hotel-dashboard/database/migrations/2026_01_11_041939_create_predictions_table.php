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
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->onDelete('cascade');
            $table->date('prediction_date');
            $table->date('predicted_for_date');
            $table->decimal('predicted_occupancy_rate', 5, 2);
            $table->integer('predicted_rooms_occupied');
            $table->decimal('predicted_revenue', 12, 2);
            $table->decimal('confidence_level', 5, 2)->nullable();
            $table->string('model_type')->default('single');
            $table->string('model_version')->nullable();
            $table->timestamps();

            $table->index(['room_type_id', 'predicted_for_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
