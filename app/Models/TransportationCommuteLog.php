<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportationCommuteLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'commute_type',
        'origin',
        'destination',
        'distance_km',
        'consumption_value',
        'consumption_unit',
        'driven_at',
        'ended_at',
        'average_speed_kmh',
        'top_speed_kmh',
        'drive_time_minutes',
        'notes',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(TransportationVehicle::class, 'vehicle_id');
    }
}
