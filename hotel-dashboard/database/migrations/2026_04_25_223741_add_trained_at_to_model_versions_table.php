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
        Schema::table('model_versions', function (Blueprint $table) {
            $table->timestamp('trained_at')->nullable()->after('training_duration_seconds');
        });

        // Backfill thesis models: trained on Jan 2026 data, next retrain Jul 2026
        DB::table('model_versions')
            ->whereIn('version', ['v1.0.0-thesis'])
            ->update([
                'trained_at'           => '2026-01-31 00:00:00',
                'next_retraining_due'  => '2026-07-31 00:00:00',
                'trained_on_records'   => 7400,
            ]);
    }

    public function down(): void
    {
        Schema::table('model_versions', function (Blueprint $table) {
            $table->dropColumn('trained_at');
        });
    }
};
