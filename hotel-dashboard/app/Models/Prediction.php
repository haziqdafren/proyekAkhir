<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prediction extends Model
{
    protected $fillable = [
        'room_type_id',
        'prediction_date',
        'predicted_for_date',
        'predicted_occupancy_rate',
        'predicted_rooms_occupied',
        'predicted_revenue',
        'confidence_level',
        'model_type',
        'model_version',
    ];

    protected $casts = [
        'prediction_date' => 'date',
        'predicted_for_date' => 'date',
        'predicted_occupancy_rate' => 'decimal:2',
        'predicted_revenue' => 'decimal:2',
        'confidence_level' => 'decimal:2',
    ];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
