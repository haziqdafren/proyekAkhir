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
        Schema::create('model_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20); // e.g., v1.0.0, v1.1.0
            $table->enum('model_type', ['single', 'multi'])->index();
            $table->string('model_path'); // Path to .h5 file
            $table->boolean('is_champion')->default(false)->index();
            $table->boolean('is_active')->default(true);
            
            // Performance metrics
            $table->decimal('mape', 8, 4)->nullable(); // Mean Absolute Percentage Error
            $table->decimal('r2_score', 8, 4)->nullable(); // R-squared
            $table->decimal('rmse', 8, 4)->nullable(); // Root Mean Square Error
            
            // Training metadata
            $table->integer('trained_on_records')->nullable();
            $table->decimal('training_duration_seconds', 10, 2)->nullable();
            $table->enum('status', ['pending', 'training', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable(); // Additional training info
            
            $table->timestamps();
            
            // Indexes
            $table->index(['model_type', 'is_champion']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_versions');
    }
};
