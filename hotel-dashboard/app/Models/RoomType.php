<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
     protected $fillable = [
        'code',
        'name',
        'description',
        'capacity',
        'base_price',
        'total_rooms',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'base_price' => 'decimal:2',
    ];

    public function historicalData(): HasMany
    {
        return $this->hasMany(HistoricalOccupancyData::class);
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }
}
