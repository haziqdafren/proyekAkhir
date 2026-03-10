<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelVersion extends Model
{
    protected $fillable = [
        'version',
        'model_type',
        'model_path',
        'is_champion',
        'is_active',
        'mape',
        'r2_score',
        'rmse',
        'trained_on_records',
        'training_duration_seconds',
        'trained_at',
        'status',
        'error_message',
        'metadata',
        'next_retraining_due',
        'new_data_months_count',
        'retraining_notes',
    ];

    protected $casts = [
        'is_champion' => 'boolean',
        'is_active' => 'boolean',
        'mape' => 'decimal:4',
        'r2_score' => 'decimal:4',
        'rmse' => 'decimal:4',
        'training_duration_seconds' => 'decimal:2',
        'metadata' => 'array',
        'trained_at' => 'datetime',
        'next_retraining_due' => 'datetime',
    ];

    /**
     * Get the current champion model for a given type
     */
    public static function getChampion(string $modelType): ?self
    {
        return static::where('model_type', $modelType)
            ->where('is_champion', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Promote this model to champion
     */
    public function promoteToChampion(): void
    {
        // Demote current champion
        static::where('model_type', $this->model_type)
            ->where('is_champion', true)
            ->update(['is_champion' => false]);

        // Promote this model
        $this->is_champion = true;
        $this->save();
        
        // Copy model file to champion.keras
        $this->copyToChampionFile();
    }
    
    /**
     * Copy this model file to champion.keras for easy access
     */
    public function copyToChampionFile(): void
    {
        if (empty($this->model_path) || !file_exists($this->model_path)) {
            return;
        }
        
        $modelDir = storage_path('app/models/' . $this->model_type);
        $championPath = $modelDir . '/champion.keras';
        
        // Ensure directory exists
        if (!is_dir($modelDir)) {
            mkdir($modelDir, 0755, true);
        }
        
        // Copy model file
        if (copy($this->model_path, $championPath)) {
            \Illuminate\Support\Facades\Log::info("Copied model to champion", [
                'source' => $this->model_path,
                'champion' => $championPath,
            ]);
        }
    }

    /**
     * Generate next version number
     */
    public static function getNextVersion(string $modelType): string
    {
        $latest = static::where('model_type', $modelType)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$latest) {
            return 'v1.0.0';
        }

        // Parse version and increment
        preg_match('/v(\d+)\.(\d+)\.(\d+)/', $latest->version, $matches);
        if ($matches) {
            $minor = (int) $matches[2] + 1;
            return "v{$matches[1]}.{$minor}.0";
        }

        return 'v1.0.0';
    }

    /**
     * Check if this model is better than the champion
     *
     * A model is considered better if:
     * 1. It has valid R² score (between 0 and 1)
     * 2. It has lower MAPE than the champion
     * 3. If both have similar MAPE (within 1%), prefer higher R²
     */
    public function isBetterThan(?self $champion): bool
    {
        // If no champion exists, this becomes champion only if it has valid R²
        if (!$champion) {
            return $this->isValidModel();
        }

        // Current model must have valid metrics
        if (!$this->isValidModel()) {
            return false;
        }

        // If champion has invalid R², replace it
        if (!$champion->isValidModel()) {
            return true;
        }

        // Both models are valid - compare by MAPE primarily
        $mapeDiff = abs($this->mape - $champion->mape);

        // If MAPE difference is significant (> 1%), use MAPE as primary metric
        if ($mapeDiff > 1.0) {
            return $this->mape < $champion->mape;
        }

        // If MAPE is similar (within 1%), prefer higher R²
        return $this->r2_score > $champion->r2_score;
    }

    /**
     * Check if this model has valid performance metrics
     *
     * Valid model criteria:
     * - R² score between 0 and 1 (negative means worse than baseline)
     * - MAPE is reasonable (< 50%)
     */
    public function isValidModel(): bool
    {
        // R² must be between 0 and 1
        if ($this->r2_score === null || $this->r2_score < 0 || $this->r2_score > 1) {
            return false;
        }

        // MAPE must be reasonable (less than 50%)
        if ($this->mape === null || $this->mape < 0 || $this->mape > 50) {
            return false;
        }

        return true;
    }

    /**
     * Get performance improvement percentage
     */
    public function getImprovementOver(?self $champion): ?float
    {
        if (!$champion || !$champion->mape || !$this->mape) {
            return null;
        }

        return round((($champion->mape - $this->mape) / $champion->mape) * 100, 2);
    }

    /**
     * Scope for completed models
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for active models
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
