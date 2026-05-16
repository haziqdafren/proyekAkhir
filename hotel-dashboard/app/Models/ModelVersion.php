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
     * Check if this model is better than the champion.
     *
     * Primary metric: MAPE (lower is better).
     * Tiebreaker (MAPE within 1%): R² (higher is better).
     */
    public function isBetterThan(?self $champion): bool
    {
        // No champion — promote any trainable model
        if (!$champion) {
            return $this->isTrainable();
        }

        // New model must at least be trainable
        if (!$this->isTrainable()) {
            return false;
        }

        // Replace a broken champion with any trainable model
        if (!$champion->isTrainable()) {
            return true;
        }

        // Both trainable — compare MAPE (primary metric, lower is better)
        $mapeDiff = abs((float) $this->mape - (float) $champion->mape);

        if ($mapeDiff > 1.0) {
            return (float) $this->mape < (float) $champion->mape;
        }

        // MAPE within 1% — prefer higher R² as tiebreaker
        return (float) $this->r2_score > (float) $champion->r2_score;
    }

    /**
     * A trainable model has finite, non-null metrics and MAPE < 100%.
     * R² can be negative (model worse than baseline — still usable as a starting point).
     */
    public function isTrainable(): bool
    {
        if ($this->mape === null || $this->mape < 0 || $this->mape >= 100) {
            return false;
        }

        if ($this->r2_score === null || $this->r2_score < -10 || $this->r2_score > 1) {
            return false;
        }

        return true;
    }

    /**
     * A valid (good) model — R² is positive and MAPE is reasonable.
     * Used for display / quality flags, not for promotion logic.
     */
    public function isValidModel(): bool
    {
        return $this->isTrainable()
            && (float) $this->r2_score >= 0
            && (float) $this->mape < 50;
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
