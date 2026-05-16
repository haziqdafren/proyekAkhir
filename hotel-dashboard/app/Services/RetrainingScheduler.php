<?php

namespace App\Services;

use App\Models\ModelVersion;
use App\Models\HistoricalOccupancyData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Annual Retraining Scheduler — fixed October cycle
 *
 * Retraining happens once a year every October.
 * New data is counted from the training data cutoff (Oct 2025) onward.
 */
class RetrainingScheduler
{
    private const RETRAINING_INTERVAL_MONTHS = 12;
    private const MINIMUM_NEW_DATA_MONTHS    = 6;
    private const WARNING_THRESHOLD_MONTHS   = 10;

    // Fixed anchor: original training data ended October 2025.
    // Each year retraining is due in October.
    private const TRAINING_DATA_CUTOFF = '2025-10-31';
    private const RETRAINING_MONTH     = 10; // October

    /**
     * Get retraining status for a single model type.
     * Read-only — no side effects.
     */
    public function getRetrainingStatus(string $modelType = 'single'): array
    {
        $champion = ModelVersion::getChampion($modelType);

        if (!$champion) {
            return [
                'status'        => 'no_model',
                'urgency'       => 'critical',
                'should_retrain' => true,
                'message'       => 'Belum ada model champion. Lakukan training awal.',
                'action_text'   => 'Train Model',
                'color'         => 'red',
                'months_since_training' => null,
                'new_data_months'       => 0,
                'new_data_percentage'   => 0,
                'next_due'              => null,
            ];
        }

        // Next retraining is always in October. Find the next upcoming October.
        $now     = Carbon::now();
        $nextDue = Carbon::create($now->year, self::RETRAINING_MONTH, 1)->startOfMonth();
        if ($nextDue->lte($now)) {
            $nextDue->addYear();
        }

        // Months remaining until next October
        $monthsSinceTraining = (int) $now->diffInMonths($nextDue, false) * -1;
        // Reframe: how many months since the fixed cutoff (Oct 2025)
        $cutoff              = Carbon::parse(self::TRAINING_DATA_CUTOFF);
        $monthsSinceCutoff   = (int) $cutoff->diffInMonths($now);

        $newDataMonths       = $this->countNewDataMonths($cutoff);
        $newDataPercentage   = $this->calculateNewDataPercentage($champion, $newDataMonths);

        // Use months-since-cutoff to determine overdue/approaching/optimal
        $monthsSinceTraining = $monthsSinceCutoff;

        return $this->buildStatus(
            $monthsSinceTraining,
            $newDataMonths,
            $newDataPercentage,
            $nextDue
        );
    }

    /**
     * Count distinct months of data uploaded after the training data cutoff (Oct 2025).
     */
    private function countNewDataMonths(Carbon $cutoff): int
    {
        return HistoricalOccupancyData::where('date', '>', $cutoff->toDateString())
            ->selectRaw("strftime('%Y-%m', date) as ym")
            ->groupByRaw("strftime('%Y-%m', date)")
            ->get()
            ->count();
    }

    /**
     * New-data percentage relative to the training record count.
     */
    private function calculateNewDataPercentage(ModelVersion $champion, int $newDataMonths): float
    {
        $originalRecords = $champion->trained_on_records ?: 24;

        return round(($newDataMonths / $originalRecords) * 100, 1);
    }

    /**
     * Pure status builder — no database writes.
     */
    private function buildStatus(
        int $monthsSinceTraining,
        int $newDataMonths,
        float $newDataPercentage,
        Carbon $nextDue
    ): array {
        $base = [
            'months_since_training' => $monthsSinceTraining,
            'new_data_months'       => $newDataMonths,
            'new_data_percentage'   => $newDataPercentage,
            'next_due'              => $nextDue->format('d M Y'),
        ];

        $remaining = max(0, self::RETRAINING_INTERVAL_MONTHS - $monthsSinceTraining);
        $needed    = max(0, self::MINIMUM_NEW_DATA_MONTHS - $newDataMonths);

        // Overdue — annual retraining cycle has expired
        if ($monthsSinceTraining >= self::RETRAINING_INTERVAL_MONTHS) {
            return $base + [
                'status'         => 'overdue',
                'urgency'        => 'high',
                'should_retrain' => true,
                'message'        => "Model sudah {$monthsSinceTraining} bulan belum dilatih ulang. Siklus tahunan telah habis — segera lakukan retraining dengan data penuh yang tersedia.",
                'action_text'    => 'Retrain Sekarang',
                'color'          => 'red',
            ];
        }

        // Approaching — within 2-month warning window before annual due date
        if ($monthsSinceTraining >= self::WARNING_THRESHOLD_MONTHS) {
            $canRetrain = $newDataMonths >= self::MINIMUM_NEW_DATA_MONTHS;
            $msg = $canRetrain
                ? "{$newDataMonths} bulan data baru tersimpan. Jadwal retrain tahunan: {$nextDue->format('M Y')} ({$remaining} bulan lagi) — sudah cukup data baru, bisa retrain lebih awal."
                : "{$newDataMonths} bulan data baru tersimpan. Jadwal retrain tahunan: {$nextDue->format('M Y')} ({$remaining} bulan lagi) — upload data bulanan untuk mempersiapkan retraining.";
            return $base + [
                'status'         => 'approaching',
                'urgency'        => 'medium',
                'should_retrain' => $canRetrain,
                'message'        => $msg,
                'action_text'    => $canRetrain ? 'Retrain Lebih Awal' : 'Upload Data Dulu',
                'color'          => 'yellow',
            ];
        }

        // Optimal — within annual cycle, keep uploading monthly data
        return $base + [
            'status'         => 'optimal',
            'urgency'        => 'none',
            'should_retrain' => false,
            'message'        => "Model berjalan baik. Jadwal retraining tahunan berikutnya: {$nextDue->format('M Y')} ({$remaining} bulan lagi). Terus upload data bulanan secara rutin.",
            'action_text'    => 'Tidak Perlu Tindakan',
            'color'          => 'green',
        ];
    }

    /**
     * Mark retraining as completed and schedule the next cycle.
     * This is the only method that writes to the database.
     */
    public function markRetrainingCompleted(ModelVersion $newChampion): void
    {
        $now     = Carbon::now();
        $nextDue = Carbon::create($now->year, self::RETRAINING_MONTH, 1)->startOfMonth();
        if ($nextDue->lte($now)) {
            $nextDue->addYear();
        }

        $newChampion->update([
            'next_retraining_due'  => $nextDue,
            'new_data_months_count' => 0,
            'retraining_notes'     => 'Trained ' . now()->format('Y-m-d H:i') . '. Next due: ' . $nextDue->format('Y-m-d'),
        ]);

        Log::info('Retraining completed. Next due: ' . $nextDue->format('Y-m-d'), [
            'model_version' => $newChampion->version,
            'model_type'    => $newChampion->model_type,
        ]);
    }

    /**
     * Get status for both model types.
     */
    public function getAllModelsStatus(): array
    {
        return [
            'single' => $this->getRetrainingStatus('single'),
            'multi'  => $this->getRetrainingStatus('multi'),
        ];
    }

    /**
     * Returns true if any model type urgently needs retraining.
     */
    public function hasUrgentRetraining(): bool
    {
        $statuses = $this->getAllModelsStatus();

        return in_array($statuses['single']['urgency'], ['high', 'critical'])
            || in_array($statuses['multi']['urgency'],  ['high', 'critical']);
    }
}
