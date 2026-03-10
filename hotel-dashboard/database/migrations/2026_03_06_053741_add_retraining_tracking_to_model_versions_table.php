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
            $table->timestamp('next_retraining_due')->nullable()->after('trained_at');
            $table->integer('new_data_months_count')->default(0)->after('next_retraining_due');
            $table->text('retraining_notes')->nullable()->after('new_data_months_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('model_versions', function (Blueprint $table) {
            $table->dropColumn(['next_retraining_due', 'new_data_months_count', 'retraining_notes']);
        });
    }
};
