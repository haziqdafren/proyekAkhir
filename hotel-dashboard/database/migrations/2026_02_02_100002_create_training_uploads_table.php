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
        Schema::create('training_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('original_name');
            
            // Period info extracted from file
            $table->unsignedTinyInteger('month_period')->nullable(); // 1-12
            $table->unsignedSmallInteger('year_period')->nullable(); // 2025, 2026, etc.
            
            // Processing stats
            $table->integer('records_parsed')->default(0);
            $table->integer('records_inserted')->default(0);
            $table->integer('records_failed')->default(0);
            
            // Processing status
            $table->enum('processing_status', [
                'pending', 
                'parsing', 
                'inserting', 
                'retraining', 
                'completed', 
                'failed'
            ])->default('pending');
            
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable(); // Detailed errors per row
            $table->json('parsing_log')->nullable(); // Log of what was parsed
            
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['processing_status', 'created_at']);
            $table->index(['year_period', 'month_period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_uploads');
    }
};
