<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoricalOccupancyData extends Model
{
    protected $fillable = [
        'room_type_id',
        'date',
        'rooms_occupied',
        'rooms_available',
        'occupancy_rate',
        'revenue',
        'average_daily_rate',
    ];

    protected $casts = [
        'date' => 'date',
        'occupancy_rate' => 'decimal:2',
        'revenue' => 'decimal:2',
        'average_daily_rate' => 'decimal:2',
    ];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
