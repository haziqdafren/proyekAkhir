<?php

namespace App\Services;

use App\Models\ModelVersion;
use App\Models\HistoricalOccupancyData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Professional 6-Month Retraining Scheduler
 *
 * Manages intelligent model retraining recommendations based on:
 * - Time since last training (6-month cycle)
 * - Amount of new data available
 * - Data quality thresholds
 */
class RetrainingScheduler
{
    /**
     * Recommended retraining interval in months
     */
    private const RETRAINING_INTERVAL_MONTHS = 6;

    /**
     * Minimum new data months before allowing retraining
     */
    private const MINIMUM_NEW_DATA_MONTHS = 3;

    /**
     * Warning threshold (show reminder)
     */
    private const WARNING_THRESHOLD_MONTHS = 4;

    /**
     * Get comprehensive retraining status for dashboard display
     */
    public function getRetrainingStatus(string $modelType = 'single'): array
    {
        $champion = ModelVersion::getChampion($modelType);

        if (!$champion) {
            return [
                'status' => 'no_model',
                'urgency' => 'critical',
                'should_retrain' => true,
                'message' => 'No champion model exists. Please train initial model.',
                'action_text' => 'Train Initial Model',
                'icon' => '⚠️',
                'color' => 'red',
            ];
        }

        // Calculate time-based metrics
        $trainedAt = $champion->trained_at ? Carbon::parse($champion->trained_at) : $champion->created_at;
        $monthsSinceTraining = (int) $trainedAt->diffInMonths(now());
        $nextDue = $champion->next_retraining_due ? Carbon::parse($champion->next_retraining_due) : $trainedAt->copy()->addMonths(self::RETRAINING_INTERVAL_MONTHS);

        // Calculate data-based metrics
        $newDataMonths = $this->countNewDataMonths($trainedAt);
        $newDataPercentage = $this->calculateNewDataPercentage($champion, $newDataMonths);

        // Update champion with current counts
        $this->updateChampionMetrics($champion, $newDataMonths, $nextDue);

        // Determine status
        return $this->determineStatus(
            $monthsSinceTraining,
            $newDataMonths,
            $newDataPercentage,
            $nextDue,
            $modelType
        );
    }

    /**
     * Count how many new months of data available since last training
     */
    private function countNewDataMonths(Carbon $trainedAt): int
    {
        $latestData = HistoricalOccupancyData::orderBy('date', 'desc')->first();

        if (!$latestData) {
            return 0;
        }

        $latestDataDate = Carbon::parse($latestData->date);

        // Count distinct months between training date and latest data
        $monthsBetween = (int) $trainedAt->diffInMonths($latestDataDate);

        return max(0, $monthsBetween);
    }

    /**
     * Calculate percentage of new data relative to training set
     */
    private function calculateNewDataPercentage(ModelVersion $champion, int $newDataMonths): float
    {
        $originalRecords = $champion->trained_on_records ?? 48; // Default 48 months

        if ($originalRecords == 0) {
            return 0;
        }

        return ($newDataMonths / $originalRecords) * 100;
    }

    /**
     * Update champion model with current metrics
     */
    private function updateChampionMetrics(ModelVersion $champion, int $newDataMonths, Carbon $nextDue): void
    {
        $champion->update([
            'new_data_months_count' => $newDataMonths,
            'next_retraining_due' => $nextDue,
        ]);
    }

    /**
     * Determine retraining status and recommendation
     */
    private function determineStatus(
        int $monthsSinceTraining,
        int $newDataMonths,
        float $newDataPercentage,
        Carbon $nextDue,
        string $modelType
    ): array {
        // CRITICAL: Overdue
        if ($monthsSinceTraining >= self::RETRAINING_INTERVAL_MONTHS) {
            return [
                'status' => 'overdue',
                'urgency' => 'high',
                'should_retrain' => true,
                'message' => "Model retraining overdue! It's been {$monthsSinceTraining} months since last training.",
                'details' => "You have {$newDataMonths} months of new data ({$newDataPercentage}% increase).",
                'next_due' => $nextDue->format('M d, Y'),
                'months_since_training' => $monthsSinceTraining,
                'new_data_months' => $newDataMonths,
                'new_data_percentage' => round($newDataPercentage, 1),
                'action_text' => 'Retrain Now',
                'icon' => '🔴',
                'color' => 'red',
            ];
        }

        // WARNING: Approaching retraining time
        if ($monthsSinceTraining >= self::WARNING_THRESHOLD_MONTHS) {
            return [
                'status' => 'approaching',
                'urgency' => 'medium',
                'should_retrain' => $newDataMonths >= self::MINIMUM_NEW_DATA_MONTHS,
                'message' => "Retraining recommended in " . (self::RETRAINING_INTERVAL_MONTHS - $monthsSinceTraining) . " month(s).",
                'details' => "{$newDataMonths} months of new data available. Sufficient for retraining.",
                'next_due' => $nextDue->format('M d, Y'),
                'months_since_training' => $monthsSinceTraining,
                'new_data_months' => $newDataMonths,
                'new_data_percentage' => round($newDataPercentage, 1),
                'action_text' => 'Retrain Early',
                'icon' => '⚠️',
                'color' => 'yellow',
            ];
        }

        // GOOD: Recently trained, sufficient new data
        if ($newDataMonths >= self::MINIMUM_NEW_DATA_MONTHS && $monthsSinceTraining >= 3) {
            return [
                'status' => 'ready',
                'urgency' => 'low',
                'should_retrain' => false,
                'message' => "Model is current. Next retraining due {$nextDue->format('M d, Y')}.",
                'details' => "{$newDataMonths} months of new data available. You may retrain early if needed.",
                'next_due' => $nextDue->format('M d, Y'),
                'months_since_training' => $monthsSinceTraining,
                'new_data_months' => $newDataMonths,
                'new_data_percentage' => round($newDataPercentage, 1),
                'action_text' => 'Optional: Retrain Early',
                'icon' => '✅',
                'color' => 'green',
            ];
        }

        // OPTIMAL: Recently trained, not enough new data yet
        return [
            'status' => 'optimal',
            'urgency' => 'none',
            'should_retrain' => false,
            'message' => "Model is fresh and performing well.",
            'details' => "Only {$newDataMonths} months of new data. Wait for {$nextDue->diffForHumans()}.",
            'next_due' => $nextDue->format('M d, Y'),
            'months_since_training' => $monthsSinceTraining,
            'new_data_months' => $newDataMonths,
            'new_data_percentage' => round($newDataPercentage, 1),
            'action_text' => 'No Action Needed',
            'icon' => '💚',
            'color' => 'green',
        ];
    }

    /**
     * Mark retraining as completed and schedule next one
     */
    public function markRetrainingCompleted(ModelVersion $newChampion): void
    {
        $nextDue = now()->addMonths(self::RETRAINING_INTERVAL_MONTHS);

        $newChampion->update([
            'next_retraining_due' => $nextDue,
            'new_data_months_count' => 0,
            'retraining_notes' => "Trained on " . now()->format('Y-m-d H:i') . ". Next retraining due: " . $nextDue->format('Y-m-d'),
        ]);

        Log::info("Retraining completed. Next due: {$nextDue->format('Y-m-d')}", [
            'model_version' => $newChampion->version,
            'model_type' => $newChampion->model_type,
        ]);
    }

    /**
     * Get summary for all model types
     */
    public function getAllModelsStatus(): array
    {
        return [
            'single' => $this->getRetrainingStatus('single'),
            'multi' => $this->getRetrainingStatus('multi'),
        ];
    }

    /**
     * Check if any model needs urgent retraining
     */
    public function hasUrgentRetraining(): bool
    {
        $statuses = $this->getAllModelsStatus();

        return $statuses['single']['urgency'] === 'high' ||
               $statuses['single']['urgency'] === 'critical' ||
               $statuses['multi']['urgency'] === 'high' ||
               $statuses['multi']['urgency'] === 'critical';
    }
}
