<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingUpload extends Model
{
    protected $fillable = [
        'file_name',
        'file_path',
        'original_name',
        'month_period',
        'year_period',
        'records_parsed',
        'records_inserted',
        'records_failed',
        'processing_status',
        'error_message',
        'error_details',
        'parsing_log',
        'processed_at',
    ];

    protected $casts = [
        'error_details' => 'array',
        'parsing_log' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Accessor for 'status' - maps to 'processing_status'
     */
    public function getStatusAttribute()
    {
        return $this->processing_status;
    }

    /**
     * Mutator for 'status' - maps to 'processing_status'
     */
    public function setStatusAttribute($value)
    {
        $this->attributes['processing_status'] = $value;
    }

    /**
     * Get formatted period string
     */
    public function getPeriodLabelAttribute(): string
    {
        if (!$this->month_period || !$this->year_period) {
            return 'Unknown Period';
        }

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
            4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        return ($months[$this->month_period] ?? 'Unknown') . ' ' . $this->year_period;
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->processing_status) {
            'pending' => 'gray',
            'parsing' => 'blue',
            'inserting' => 'indigo',
            'retraining' => 'purple',
            'completed' => 'green',
            'failed' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get status label in Indonesian
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->processing_status) {
            'pending' => 'Menunggu',
            'parsing' => 'Membaca File',
            'inserting' => 'Menyimpan Data',
            'retraining' => 'Melatih Model',
            'completed' => 'Selesai',
            'failed' => 'Gagal',
            default => 'Unknown',
        };
    }

    /**
     * Update processing status
     */
    public function updateStatus(string $status, ?string $errorMessage = null): void
    {
        $this->processing_status = $status;
        
        if ($errorMessage) {
            $this->error_message = $errorMessage;
        }

        if ($status === 'completed' || $status === 'failed') {
            $this->processed_at = now();
        }

        $this->save();
    }

    /**
     * Add to parsing log
     */
    public function addLog(string $message, string $level = 'info'): void
    {
        $logs = $this->parsing_log ?? [];
        $logs[] = [
            'timestamp' => now()->toDateTimeString(),
            'level' => $level,
            'message' => $message,
        ];
        $this->parsing_log = $logs;
        $this->save();
    }

    /**
     * Scope for pending uploads
     */
    public function scopePending($query)
    {
        return $query->where('processing_status', 'pending');
    }

    /**
     * Scope for failed uploads
     */
    public function scopeFailed($query)
    {
        return $query->where('processing_status', 'failed');
    }

    /**
     * Check if upload can be retried
     */
    public function canRetry(): bool
    {
        return in_array($this->processing_status, ['failed', 'pending']);
    }
}
